# TECH STATUS REPORT — Stato avanzamento (Backend Laravel + Frontend Flutter)

> Report tecnico di ispezione del codice reale, da condividere con un collaboratore AI per pianificare le funzioni Premium di lancio.
> Data: 2026-06-14 · Stack: **Laravel 11 + MySQL/SQLite** (no Firebase/Supabase) · **Flutter 3 / Riverpod / go_router / Dio**.
> Test backend: **104/104 verdi**. Flutter: `analyze` pulito + 28 test. Marcatori: ✅ FATTO / 🟡 PARZIALE / ❌ MANCA.

---

## 1. ARCHITETTURA WHITE-LABEL & TENANCY

### Caricamento configurazione in Flutter (build per cliente)
Due livelli netti — decisione architetturale centrale (`docs/27-white-label-tecnico.md` §1):

| Cosa | Quando | Come | Stato |
|---|---|---|---|
| `TENANT_KEY`, `API_BASE_URL`, `ENV`, `SENTRY_DSN` | **Compile-time** | `--dart-define` → `lib/core/env/app_environment.dart` (`String.fromEnvironment`). `AppEnvironment.validate()` in `main.dart` **fallisce all'avvio** se manca l'identità tenant | ✅ |
| Nome app, tagline, **colori/tema** (token), legal, sedi, feature flag | **Runtime** | `GET /app/config` via `WhiteLabelRepository` → cache `shared_preferences` + **revalidation ETag** + fallback offline. `AppThemeBuilder` costruisce il `ThemeData` dai token | ✅ |
| Icona nativa, splash nativa, **bundle_id**, nome sotto l'icona | **Compile-time, per-tenant** | — | ❌ **non ancora per-tenant**: l'app è `com.platform.client_app` (singola). È l'App Factory progettata ma non costruita |

**Conseguenza pratica:** cambi colore/nome/logo → effetto in **minuti senza rebuild** (runtime). Cambi icona/bundle → serve la **App Factory** (oggi assente). Per i primi clienti il "white-label a runtime" funziona; la "app separata nello store" no.

### Isolamento `BelongsToTenant` (Laravel) — ✅ robusto
`app/Foundation/Tenancy/BelongsToTenant.php`:
- aggiunge un **global scope** (`TenantScope`) → ogni query è filtrata sul tenant corrente in automatico;
- su `creating` **auto-compila `tenant_id`**; un `tenant_id` pre-impostato è lecito **solo** sotto `bypass()` esplicito (provisioning), altrimenti **lancia eccezione** → impossibile dimenticare o falsificare il tenant;
- un **architecture test** impone che ogni modello sia o tenant-bound o esplicitamente platform-level.

### Control Room vs Dashboard — separazione rotte + auth → ✅ separate
| | Dashboard esercente | Control Room (proprietaria) |
|---|---|---|
| Rotte | `/dashboard/*` | `/control-room/*` |
| Guard | `web` (sessione) | **`admin`** (sessione dedicata, separata) |
| Middleware | `tenant.dashboard` (tenant dalla sessione) + `owner` | **`control.admin`** (`EnsureSuperAdmin`) |
| Ruoli | tenant_admin / staff | **solo** super_admin |
| Isolamento | — | testato: titolare/staff/cliente/guest → 403/redirect; la sessione `web` **non** concede la guard `admin` |

---

## 2. STATO DELLE INTERFACCE (UI/UX)

### App Flutter (12 schermate)
| Schermata | Stato | Note |
|---|---|---|
| Splash / Tenant-unavailable | ✅ | testo + spinner (no immagine logo) |
| Login / Registrazione / Verifica email | ✅ | validazione, consenso GDPR, OTP con cooldown reinvio |
| Home | ✅ | CTA "Prenota", prossimo appuntamento, quick links — visivamente essenziale |
| **Scheda attività** (business_info) | 🟡 **PARZIALE** | mostra **solo** le sedi (nome/indirizzo/telefono). **Mancano** orari, galleria foto, social, lista staff, listino (commento nel file: "arrivano con l'estensione P0 della config backend") |
| Servizi (step 1) | ✅ | multiselezione, disabilita incompatibili, dettaglio variante |
| Calendario/slot (step 2) | ✅ | day-strip, chip operatore, slot Mattina/Pomeriggio, conferma — 🟡 manca campo **note** e fallback **richiesta-senza-slot** |
| Riepilogo/successo | ✅ | recap dall'appuntamento reale persistito |
| Storico appuntamenti | ✅ | tab in-programma/passate, annulla, badge stato |
| Profilo | ✅ | modifica, link legali, logout, **cancella account** (Apple-compliant) — no centro avvisi, no versione app |
| **Recupero password** | ❌ **MANCA** | nessun flusso forgot-password |

### Dashboard Web esercente (Laravel Blade, server-rendered) — tutte usabili **oggi**
| Vista | Stato | Gestisce |
|---|---|---|
| Login + MFA + accettazione invito | ✅ | |
| Home | ✅ | KPI (oggi / in attesa / 7gg), agenda del giorno, servizi top |
| **Servizi** | ✅ | nome, categoria, **prezzo, durata, buffer**, attivo/non prenotabile |
| **Operatori** | ✅ | nome, ruolo, prenotabile, **servizi eseguiti (M:N)**, **orari settimanali** |
| **Disponibilità** | ✅ | orari sede + **chiusure/ferie** (scope singolo operatore o intera sede) |
| Personalizzazione (brand) | 🟡 | nome, tagline, 2 colori, URL legali, contatti — ❌ **manca upload logo lato esercente** (oggi solo super-admin in Control Room) |

→ **"Un esercente può gestire Staff, Orari, Listino senza chiamarti?" → SÌ.** Manca solo il self-service del logo/immagini.

---

## 3. FLUSSO DI PRENOTAZIONE E SINCRONIZZAZIONE

### Click slot → salvataggio con lock → ✅ COMPLETO e TESTATO
`app/Modules/Scheduling/Application/BookAppointment.php`:
1. **Idempotency-Key**: replay → ritorna l'appuntamento originale (no doppioni da retry di rete).
2. Validazione: varianti attive, staff abilitato a **tutti** i servizi, finestra di prenotazione (min-notice / window).
3. **UNA transazione**: `assertIntervalFree()` con **`lockForUpdate()`** (lock pessimistico sugli item bloccanti dello staff che intersecano l'intervallo) → `SlotUnavailable` se conflitto → crea appointment + items (con snapshot di prezzo/durata) + evento.
4. **Indice `UNIQUE(tenant, staff, starts_at, is_blocking)`** = rete di sicurezza finale contro start identici concorrenti.
5. Post-commit: bump cache disponibilità + dispatch evento `AppointmentBooked`.

**Test che lo coprono** (`tests/Feature/BookingFlowTest.php` 8 + `tests/Unit/AvailabilityCalculatorTest.php` 10): doppia prenotazione stesso slot, slot sovrapposto, idempotency replay, cutoff cancellazione, oltre la finestra, tenant ad approvazione; e DST spring/fall, pausa pranzo, intervallo occupato, servizio troppo lungo. → **concorrenza e lock verificati**.

### Notifiche / conferme → ✅ pipeline completa lato backend, 🟡 push-to-phone manca lato client
Architettura **outbox event-driven** (`ScheduleAppointmentNotifications` → `SendNotificationJob`):
- Su `AppointmentBooked` → crea record outbox `NotificationRecord`: **conferma immediata** (`booking_confirmed`/`booking_requested`) + **promemoria** per ogni offset configurato dal tenant.
- `SendNotificationJob` (in coda, rispetta `scheduled_for`): **channel chain per i booking = `[FcmPush, Email]`** → prova push, e **se non ci sono device registrati fa fallback su EMAIL** (mailer reale → visibile in Mailpit). Consenso marketing ri-controllato **al momento dell'invio** (GDPR).
- `FcmPushChannel`: **completo** (FCM HTTP v1 + OAuth service-account + pulizia token scaduti).
- In-app: la schermata di **successo** conferma subito.
- Cancellazione: rende obsoleti i promemoria; se annulla il tenant, avvisa il cliente.

**Conclusione onesta:** conferme e promemoria oggi **arrivano via email** (fallback). Il **push sul telefono non è end-to-end** perché manca `firebase_messaging` nell'app Flutter (nessun token device registrato). Inoltre serve un **queue worker attivo** (`queue:work`) in produzione per processare i job.

---

## 4. GAP ANALYSIS — cosa manca per i primi 5 clienti (onesto)

**Premessa:** **non ci sono file "scheletro" o mockup vuoti.** Il codice è reale e testato. I gap sono **schermate sottili + integrazioni mancanti**, non stub.

| # | Gap | Gravità | Impatto sul lancio |
|---|---|---|---|
| 1 | **App Factory** (bundle_id/icona/splash/nome nativo per-tenant) | 🔴 P0 | Per "5 app separate nello store" serve la FASE 1 progettata (o build manuali). Colori/nome/logo runtime funzionano già |
| 2 | **Push client Flutter** (`firebase_messaging`) | 🟠 P1 | Conferme/promemoria oggi solo via email; il push sul telefono non arriva |
| 3 | **Scheda attività in app** (orari/foto/social/staff/listino) | 🟠 P1 | La `business_info_screen` è sottile vs un benchmark premium; richiede estendere la config backend |
| 4 | **Upload logo/immagini lato esercente** | 🟠 P1 | Promessa "250€/mese self-service": oggi il logo lo carica solo il super-admin |
| 5 | **Recupero password** | 🟠 P1 | Flusso forgot-password assente (table-stakes) |
| 6 | **Deployment produzione + HTTPS** | 🔴 P0 | Mai deployato: gira solo in locale |
| 7 | **Billing** | 🔴 P0 | Nessun incasso automatico |
| 8 | Nota su prenotazione + richiesta-senza-slot | 🟡 P2 | Gap funzionali (presenti nel benchmark) |
| 9 | Centro avvisi in-app / onboarding guidato dashboard | 🟡 P2 | Rifinitura premium |
| 10 | Asset store (icona/splash brandizzate) | 🔴 P0 store | Default Flutter; legato alla App Factory |

**Sintesi:** il **core è production-grade e testato** (tenancy, auth, booking engine atomico, notifiche outbox, Control Room). Per consegnare 5 app reali i due colli di bottiglia veri sono **(a) App Factory** (identità nativa per-tenant) e **(b) deployment + billing**; i restanti (push client, scheda attività, self-service logo, recupero password) sono rifiniture P1 ben circoscritte.

---

## Riferimenti incrociati
- Audit di produzione: `FINAL_TECHNICAL_FREEZE_REPORT.md` / `FINAL_PRODUCT_READINESS_AUDIT.md`
- Roadmap UX/UI: `PRODUCT_DESIGN_ROADMAP.md`
- Control Room: `CONTROL_ROOM_READINESS.md`
- App Factory (design): `APP_FACTORY_ROADMAP.md`

> Report generato da ispezione diretta del codice (lettura riga per riga di `main.dart`, `app_environment.dart`, `WhiteLabelRepository`, `BelongsToTenant`, `BookAppointment`, pipeline Notifications) e dai test reali. Nessun file applicativo modificato per produrre questo report.
