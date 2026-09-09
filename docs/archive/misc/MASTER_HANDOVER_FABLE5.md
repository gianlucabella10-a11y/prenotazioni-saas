# MASTER_HANDOVER_FABLE5

**Documento di handover per la prossima sessione Claude Code (Fable 5).**
Data: 12/06/2026 · Autore: sessione Fable 5 precedente · Lingua di lavoro col cliente: **italiano**.
Scopo: riprendere il progetto da qui senza rileggere la cronologia. Ogni affermazione di stato in questo documento è stata **verificata** (test eseguiti, file presenti su disco).

---

## 1. Executive Summary

Piattaforma SaaS **white label multi-tenant** che genera app mobili di prenotazione brandizzate per professionisti su appuntamento (barbieri, parrucchieri, estetisti, dentisti, fisioterapisti, consulenti). Modello: **990€ attivazione + 250€/mese + IVA** per tenant. Riferimento funzionale: l'app "Giuffrida Barber" (solo funzionalità; vietato replicare grafica/marchio/testi).

Il progetto è stato condotto in 3 fasi, tutte su questa macchina, nella cartella `/Users/gianlucabella/Desktop/app prenotazioni progetto/`:

1. **Fase 1 — Documentazione prodotto/business** (`docs/00-17`): PRD, mercato, concorrenti, SWOT, SRS, personas, journey, flussi, moduli, strategie (white label, multi-tenant, scalabilità, sicurezza, monetizzazione), piano crescita 10k tenant, audit con 32 criticità.
2. **Fase 2 — Progettazione tecnica** (`docs/20-33`): stack vincolato **Flutter + Laravel + MySQL + Redis + S3 + Firebase + AWS**; architettura, repository, DDD/Clean, database+ER, API, auth, white label, multi-tenant, notifiche, engine appuntamenti, dashboard, qualità/costi AWS, audit con 56 criticità.
3. **Fase 3 — Implementazione, incremento 1** (`platform-backend/`): backend Laravel **funzionante e testato** — **64 test, 216 assertion, tutti verdi**. Code review con 24 rilievi (`docs/34`), 11 applicati.

Ultimo lavoro svolto: analisi dei **17 screenshot** dell'app di riferimento (mai analizzati prima) → `SCREEN_ANALYSIS.md` + `GIUFFRIDA_FEATURE_GAP.md` con 13 endpoint mancanti e priorità P0-P2. **Lo sviluppo è fermo per ordine del cliente**: il prossimo incremento parte dalle priorità della gap analysis.

## 2. Obiettivo del prodotto

- Ogni cliente (tenant) ottiene un'**app mobile a proprio marchio** (nome, logo, icona, splash, colori) configurabile da dashboard **senza toccare codice**, più una dashboard gestionale.
- I clienti finali del tenant prenotano self-service: servizi (anche multipli), operatore, sede, slot; ricevono conferme e promemoria push.
- La software house gestisce tutto centralmente: un solo codebase, provisioning automatizzato in secondi, pipeline di build white label.
- Decisione architetturale cardine (docs/27): **thin shell + configurazione runtime** — nella build sono compilati solo bundle id, nome, icona, splash; tema/contenuti/feature flag arrivano da `GET /app/config` con ETag. I rebuild massivi servono solo per aggiornare il motore Flutter (3-4 treni/anno).
- Distribuzione store (docs/27 §6, rischio Apple 4.2.6): Android pubblicato dall'account piattaforma; **iOS sull'account Apple del tenant** con onboarding assistito. Da validare con tenant pilota prima di promesse contrattuali.

## 3. Stato attuale reale

### 3.1 Cosa esiste e funziona (verificato)
- `platform-backend/`: Laravel 13.15, PHP 8.4.22, ~10.500 righe (89 file app + 14 test).
- `php artisan test` → **64 passed (216 assertions)**. Nessun test skippato di default (uno solo ha skip condizionale su orari notturni).
- Migrazioni complete ed eseguibili (SQLite e MySQL-compatibili).
- Seeder piani commerciali (`PlanSeeder`: base/pro/enterprise, idempotente).

### 3.2 Cosa NON esiste ancora
- **App Flutter** (cliente white label + gestionale): zero codice. Flutter SDK **non installato** sulla macchina.
- **UI dashboard web** (tenant + super admin): zero codice. Le API che le servono esistono in parte.
- Endpoint elencati in §9/§11-14 (gap P0-P2).
- Repository `platform-infra` (Terraform, pipeline build white label): solo progettato (docs/21-22, 27).
- Misurazione coverage: il runtime locale non ha pcov/xdebug; il comando CI di riferimento è `php artisan test --coverage --min=90` su immagine con pcov.

### 3.3 Ambiente di sviluppo (CRITICO da sapere)
- macOS senza Homebrew, senza sudo passwordless, senza PHP/Node/Flutter di sistema.
- Toolchain user-space installato in **`~/.local/php-toolchain/bin`**: PHP 8.4.22 statico (static-php.dev, include pdo_sqlite, pdo_mysql, openssl, redis, mbstring, gd…) + `composer` (wrapper su composer.phar).
- **Prima di ogni comando**: `export PATH="$HOME/.local/php-toolchain/bin:$PATH"`.
- Test: SQLite `:memory:` (phpunit.xml già configurato, include `RATE_LIMIT_AUTH=100` per non sbattere sul rate limiter nei test). Le chiavi JWT di test sono generate una volta per processo in `tests/TestCase.php`.
- Gli screenshot convertiti in JPEG sono in `.screens-analysis/` (gli originali HEIC in root).

### 3.4 Convenzioni e deviazioni consapevoli (non "sistemarle" senza motivo)
- **uuid `CHAR(36)`** (HasUuids, ordered) invece di BINARY(16) del doc 24: scelta deliberata per portabilità/semplicità; documentata.
- **Enum come stringhe** + PHP backed enum (niente colonne ENUM MySQL): evoluzione senza ALTER.
- **Niente FK** su `notification_records` e `audit_logs`: candidate al partizionamento MySQL (FK incompatibili) — integrità applicativa (docs/33 #15).
- **Date di calendario** (`schedule_exceptions.date_*`, `*_schedules.valid_*`) come **stringhe `Y-m-d` senza cast**: i cast `date` rompono i confronti su SQLite e forzano funzioni index-hostile su MySQL (bug trovato dai test, vedi docs/34 #2).
- Ora locale + timezone IANA per le **regole ricorrenti**; **UTC** per gli istanti (appuntamenti). Mai invertire (docs/30 §2).
- `is_blocking` su `appointment_items`: 1 = blocca agenda, **NULL** = non blocca (NULL sfugge all'indice UNIQUE anti-doppia-prenotazione).
- Formato errori API: `{"error":{"code","message","details?"}}` con codici stabili. Codici esistenti: `invalid_credentials, invalid_token, invalid_refresh_token, refresh_token_reused, invalid_mfa_code, mfa_required, mfa_token_required, missing_tenant_key, invalid_tenant_key, tenant_not_operating, feature_not_available, quota_exceeded, slot_unavailable, cutoff_passed, too_late_to_book, beyond_booking_window, unknown_service_variant, staff_not_enabled, no_services_selected, missing_idempotency_key, insufficient_contrast, email_taken, health_module_required, invalid_tenant_transition, unknown_plan, customer_profile_missing, no_staff_profile, account_disabled, validation_failed, unauthenticated, not_found, invalid_status_transition`.

## 4. Architettura

- **Monolite modulare Laravel** (no microservizi — docs/21 §3.1). Bounded context DDD in `app/Modules/<Context>/{Domain,Application,Infrastructure,Presentation}`; trasversali in `app/Foundation/{Tenancy,Auth,Audit,Http,Enums}`.
- Compromesso dichiarato (docs/23 §4): layer Domain rigoroso solo per **Scheduling** (core domain); i moduli CRUD usano Eloquent direttamente nei controller/use case.
- **Multi-tenant a 5 livelli** (docs/28): (1) tenant solo dal JWT (`tid`) o `X-Tenant-Key`, mai da input; (2) global scope automatico via trait `BelongsToTenant` + `TenantScope`, fail-closed (`TenantContextMissing`) senza contesto; (3) `CurrentTenant::bypass()` esplicito per codice piattaforma; (4) policy/RBAC; (5) suite test isolamento (cross-tenant ⇒ **404**, mai 403).
- `TenantRegistry`: risoluzione cacheata (TTL 300s) con chiave api-key **hashata SHA-256**; `forget()` a ogni cambio stato/brand.
- **Eventi di dominio** per la comunicazione tra moduli (registrati in `AppServiceProvider::registerDomainEventListeners`): `AppointmentBooked`/`AppointmentCancelled` → `ScheduleAppointmentNotifications`.
- Job tenant-aware: trait `TenantAwareJob` (serializza tenant_id, ricostruisce il contesto nel worker).
- Tre superfici client previste: app cliente Flutter (white label), app gestionale Flutter (unica, brand piattaforma), dashboard web Laravel. Solo le API esistono oggi.
- Produzione prevista (docs/21, 32): ECS Fargate (web + worker Horizon + scheduler singleton), RDS MySQL Multi-AZ, ElastiCache Redis, S3+CloudFront, SES, WAF/ALB. Costi stimati: ~465€/mese a 50 tenant → ~1€/tenant a 10k.

## 5. Database

Migrazioni in `database/migrations/` (ordine: tenancy → identity → branding → catalog → scheduling → messaging):

| Migrazione | Tabelle |
|---|---|
| `2026_06_12_000010_create_tenancy_tables` | `tenants` (status, sector, api_key, settings JSON con `reminder_offsets_hours` e `booking_confirmation_mode`, onboarding_state, health_data_enabled), `tenant_domains`, `plans` (features/quotas JSON), `subscriptions`, `tenant_features` |
| `…000020_create_identity_tables` | `users` (tenant_id NULL=piattaforma, type, UNIQUE(tenant_id,email)), `password_reset_tokens`, `sessions`, `mfa_credentials` (secret encrypted), `refresh_tokens` (token_hash, family_uuid, rotazione), `devices` (fcm_token) |
| `…000030_create_branding_tables` | `brand_profiles` (theme JSON, config_version), `brand_assets` |
| `…000040_create_catalog_tables` | `locations` (timezone, booking_window_days, cancellation_cutoff_minutes, min_notice_minutes, slot_granularity_minutes), `service_categories`, `services`, `service_variants` (durata/buffer/prezzo — **ogni servizio ha ≥1 variante**), `staff_members`, `staff_services`, `location_services` |
| `…000050_create_scheduling_tables` | `location_schedules`/`staff_schedules` (weekday ISO 0=lunedì, TIME locali), `schedule_exceptions` (closed/open_extra, scope location/staff), `customers` (user_id NULL per walk-in/import, no_show_count), `customer_notes` (body encrypted, visibility internal/clinical), `consents` (append-only), `appointments` (status, starts/ends UTC, idempotency_key UNIQUE per tenant, snapshot totale), `appointment_items` (snapshot servizio/prezzo/durata, **UNIQUE(tenant,staff,starts_at,is_blocking)**), `appointment_events`, `waitlist_entries` (tabella pronta, nessuna API) |
| `…000060_create_messaging_tables` | `notification_records` (outbox: status/scheduled_for/attempts), `campaigns` (tabella pronta, nessuna API), `audit_logs` (append-only) |

Macchine a stati: `TenantStatus` (onboarding→active→at_risk/suspended→terminated) e `AppointmentStatus` (requested→confirmed→completed/no_show/cancelled_*; no_show→completed entro 7gg) — transizioni validate in enum, uniche vie di modifica.

## 6. API implementate (prefisso `/api/v1`, definite in `routes/api.php`)

| Gruppo | Middleware | Endpoint |
|---|---|---|
| Config pubblica | `tenant.key, throttle:api` | `GET /app/config` (ETag/304; payload courtesy per sospesi/cessati) |
| Catalogo pubblico | `tenant.key, tenant.operating` | `GET /catalog/services`, `GET /staff`, `GET /availability` (throttle dedicato) |
| Auth | `tenant.key, tenant.operating, throttle:auth` | `POST /auth/register` (collega/crea Customer CRM), `POST /auth/login` (può rispondere `mfa: setup_required|verification_required` con `mfa_token`) |
| Token | `throttle:auth` | `POST /auth/refresh` (rotazione + rilevamento riuso→revoca famiglia), `POST /auth/logout` |
| MFA | `auth:api` (accetta SOLO token scope-mfa) | `POST /auth/mfa/setup`, `/confirm`, `/verify` (TOTP RFC 6238) |
| Cliente | `auth:api, auth.full, user.type:customer, tenant.operating` | `GET/POST /appointments` (POST richiede header `Idempotency-Key`), `GET /appointments/{uuid}`, `POST /appointments/{uuid}/cancel` |
| Gestione | `auth:api, auth.full, user.type:tenant_admin,staff` | `GET /manage/agenda?date=` (giorno locale tenant; staff vede solo sé), `POST /manage/appointments/{uuid}/confirm|complete|no-show|cancel` |
| Gestione (solo admin) | + `user.type:tenant_admin` | CRUD `/manage/services` (+`/variants`), CRUD `/manage/staff` (+`PUT /{uuid}/schedules` con invalidazione cache), `GET/PUT /manage/brand` (validazione contrasto WCAG, bump config_version) |
| Piattaforma | `user.type:super_admin` | `GET/POST /admin/tenants` (provisioning completo: piano+brand+sede+orari+catalogo settore da `config/sector_presets.php`+admin MFA-enforced; ritorna `invite_token` e `tenant_api_key`), `POST /admin/tenants/{uuid}/activate|suspend|reactivate` |

Comandi artisan: `jwt:generate-keys`, `notifications:dispatch-due` (schedulato ogni 15 min, `onOneServer`).

## 7. Test implementati (`tests/`)

- **Unit** (31): `TimeIntervalTest` (semantica half-open, sottrazioni), `AvailabilityCalculatorTest` (griglia, busy, chiusure, open_extra, pausa pranzo, **DST 29/03 e 25/10 Europe/Rome**, bound minimi), `AppointmentStatusTest` (tutte le transizioni), `TotpTest` (**vettori RFC 6238**), `ContrastValidatorTest` (WCAG).
- **Feature** (33): `AuthFlowTest` (register+CRM link, credenziali errate, **rotazione refresh + riuso→revoca famiglia**, tenant sospeso), `MfaFlowTest` (enrollment completo e verify, token mfa rifiutato sugli endpoint normali), `BookingFlowTest` (booking+outbox promemoria, **doppia prenotazione 409**, **overlap parziale 409**, idempotenza, cancel entro/oltre cutoff con riapertura slot e promemoria obsoleti, request_approve, finestra), `AvailabilityEndpointTest` (slot, **slot sparisce dopo booking** via cache versionata, chiusura ferie), `TenantIsolationTest` (**RNF-21**: listing, uuid probing→404, booking cross-tenant→404, manage cross→404, JWT+chiave altrui), `WhiteLabelConfigTest` (ETag/304, bump versione, contrasto rifiutato, **courtesy payload sospeso**), `ProvisioningAndRbacTest` (provisioning completo verificato, settore sanitario richiede modulo, transizioni tenant, confini RBAC, **quote piano**).
- Helper: `tests/Concerns/InteractsWithTenancy.php` — `provisionBookableTenant()` (tenant pronto: piano pro, brand, sede 7gg 9-19, servizio+variante, staff con orari), `bindTenant`, `bypassTenancy`, `createCustomerUser`, `createTenantAdmin`, `authHeaders`, `tenantKeyHeaders`. **Usalo sempre nei nuovi feature test.**

## 8. Funzionalità completate

Multi-tenant (isolamento 5 livelli) · Provisioning tenant one-shot · Piani+feature flag+quote · White label config runtime con ETag e versioning · Brand update con validazione contrasto · Auth JWT RS256 15min + refresh rotation con anti-furto · MFA TOTP (enrollment+verify, enforced per tenant admin) · RBAC 4 ruoli + permessi route-level · Rate limiting configurabile · Catalogo servizi/varianti/categorie con soft-delete e snapshot · Staff con servizi abilitati e orari settimanali per sede · Eccezioni orario (ferie/chiusure/extra) · Availability engine timezone-aware con cache versionata O(1) · Booking atomico multi-servizio con idempotenza · Macchina a stati appuntamento (confirm/complete/no-show con contatore cliente/cancel con cutoff) · Agenda staff per giorno locale · Notifiche outbox (conferma+promemoria offset configurabili, obsolescenza su cancel, canali FCM v1 reale + email fallback, consenso al send, sweep) · Audit log · Stati tenant con courtesy screen.

## 9. Funzionalità mancanti

1. **App Flutter cliente** (white label) e **app gestionale** — l'intero monorepo `platform-mobile` (struttura già progettata in docs/22 §3).
2. **Dashboard web UI** (wizard onboarding tenant, Brand Studio, agenda, super admin console — docs/31).
3. Endpoint gap (dettaglio §11-14): profilo `/me`, devices FCM, consensi, cancella profilo/GDPR, avvisi in-app, **booking requests senza slot**, note prenotazione, config estesa (orari/social/geo/legal/galleria), foto staff, min-version, waitlist, campagne, import CSV clienti, report/KPI tenant.
4. `platform-infra`: Terraform, pipeline build white label per-tenant, pool progetti Firebase.
5. Items "PIANIFICATO" della code review (docs/34): test architetturali automatici, ChannelResolver iniettabile, request-id middleware, contratto OpenAPI generato, audit asincrono.

## 10. Analisi screenshot Giuffrida Barber

Vedi `SCREEN_ANALYSIS.md` (12 schermate da 17 file; JPEG in `.screens-analysis/`). Schermate: splash 2 stadi; selezione tra 3 sedi con foto; scheda negozio (galleria, campanella avvisi, telefono/sito/FB/IG/WhatsApp, "apri sul navigatore", staff con foto, orari con oggi evidenziato e doppia fascia, listino); selezione servizi a griglia con multipla e stati disabilitati; step data/operatore/ora (strip calendario mensile, chip servizio modificabile, 6 barbieri scrollabili con foto, slot raggruppati Mattina/Pomeriggio, campo Note); **fallback "INVIA RICHIESTA"** quando il giorno è pieno; lista prenotazioni con toggle storico e cancellazione X; profilo (modifica, avvisi, privacy, termini, **cancella profilo**, esci, versione app).

## 11. GAP Analysis completa

Vedi `GIUFFRIDA_FEATURE_GAP.md` per la tabella integrale. Sintesi: il **core transazionale è replicato e testato**; i gap sono (a) superficie profilo/GDPR progettata ma non implementata, (b) scheda negozio ricca (estensioni del payload config + media), (c) **un flusso nuovo non previsto da alcun documento**: la richiesta di prenotazione senza slot, che richiede una nuova entità `booking_requests` (tenant, customer, sede, servizi, giorno preferito, staff preferito, note, stato pending/proposed/converted/rejected) con conversione in appuntamento dallo staff. **Prima di implementarla va recepita nei docs 24-25-30.**

## 12. Priorità P0 (parità funzionale percepita — blocca la vendibilità)

1. Campo **note del cliente** su `POST /appointments` (+ colonna `customer_note` su appointments, esposizione in agenda staff).
2. **`GET/PATCH /me`**, **`PUT /me/devices`** (token FCM — prerequisito per push reali), **`PUT /me/consents`**.
3. **`POST /booking-requests`** + lista/azioni in `/manage` (accetta→propone slot/converte, rifiuta) + notifiche relative.
4. **Estensione `GET /app/config`**: orari apertura per sede (regole + eccezioni prossime), contatti/social per sede, URL privacy/termini, lat/lng.
5. **Foto staff**: upload (URL pre-firmati S3) + `photo_url` in `GET /staff` + filtro `?location_uuid=`.

## 13. Priorità P1 (obblighi store e robustezza pre-pubblicazione)

6. **`POST /me/gdpr/erasure`** (cancella profilo — Apple rifiuta app senza; pseudonimizzazione preservando storico contabile, docs/33 #53).
7. **`GET /me/notifications`** + mark-read (centro avvisi: lettura dell'outbox esistente).
8. **`GET /app/min-version`** (da mettere in produzione PRIMA della prima app pubblicata).
9. lat/lng su locations (migrazione additiva) e galleria: `brand_assets`/asset sede kind `gallery`.

## 14. Priorità P2 (completamento esperienza)

10. Pipeline upload media completa (validazioni docs/14 §5) + avatar cliente.
11. Waitlist automatica (`POST /waitlist`, matching su `AppointmentCancelled` — tabella già pronta).
12. `POST /me/gdpr/export` (job + S3 link a scadenza).
13. Stato `is_bookable_online` sui servizi (visibile ma non prenotabile online).
14. Campagne broadcast (`campaigns` ha già la tabella), import CSV clienti (RF-53), report/KPI tenant.

## 15. Ordine corretto di implementazione (prossimi incrementi)

1. **Incremento 2 — Backend P0+P1** (1 sessione): prima aggiornare docs/24-25-30 con `booking_requests` e le estensioni config (il cliente esige progettazione→codice); poi TDD sugli endpoint §12-13 (migrazioni **solo additive**); rieseguire l'intera suite + aggiornare README e docs/34.
2. **Incremento 3 — Flutter monorepo, app cliente** (2-3 sessioni): richiede **installazione Flutter SDK user-space** (zip ufficiale in `~/.local/flutter`, niente sudo; `flutter test` funziona senza Xcode per unit/widget test). Struttura docs/22 §3: `packages/{core_domain,api_client,design_system,white_label,notifications}` + `apps/client_app`. Tema da config runtime, fallback compilato. Widget test inclusi.
3. **Incremento 4 — App gestionale Flutter** (agenda, transizioni, richieste da approvare).
4. **Incremento 5 — Dashboard web** (wizard onboarding + Brand Studio con anteprima, console super admin — docs/31).
5. **Incremento 6 — platform-infra**: Terraform (docs/21 §5, 32), pipeline build white label parametrica (docs/27 §3), pool Firebase (docs/29 §4).
6. **Spike obbligatori prima della scala** (docs/33): gap lock MySQL sotto carico concorrente reale (#33), pubblicazione iOS con account tenant pilota (#41), orizzonte job ritardati con Horizon (#50).

## 16. Rischi tecnici

| Rischio | Dettaglio | Mitigazione |
|---|---|---|
| **Policy Apple 4.2.6/4.3** (template apps) | Il rischio singolo più alto: rigetto sistemico delle app white label | iOS su account del tenant; validare con 2-3 pilota PRIMA del contratto standard (docs/27 §6, 33 #41) |
| Gap/next-key lock MySQL su booking concorrente | `SELECT…FOR UPDATE` su range + REPEATABLE READ ⇒ possibili deadlock sotto carico | Spike con MySQL reale; valutare READ COMMITTED per la transazione; retry su deadlock (docs/33 #33) |
| Lock SQLite ≠ MySQL nei test | `lockForUpdate` è no-op su SQLite: la concorrenza vera non è testata localmente | Il vincolo UNIQUE copre gli start identici; test di carico su MySQL in CI (pendente) |
| Job ritardati lunghi su Redis | Promemoria oltre 48h NON vanno in coda subito: ibrido DB+sweep già implementato | Mantenere l'ibrido; mai alzare l'orizzonte oltre 48h senza Horizon in produzione |
| Toolchain locale anomalo | PHP statico senza pcov; niente sudo | Coverage solo in CI; non tentare brew/sudo |
| Identity/contesto in runtime long-lived | Già corretto (guard per-token, scoped CurrentTenant) ma OGNI nuovo singleton va valutato per Octane/worker | Review checklist docs/34 |
| Payload config in crescita | Aggiungere orari/social/galleria aumenta il payload del config endpoint | Mantenere ETag; valutare split `GET /app/config` vs `GET /locations/{uuid}/details` se >50KB |

## 17. Rischi commerciali

- **Unit economics in fase pilota negativa** (docs/15 §6): break-even da stimare; il pilota serve a misurare CAC reale (docs/17 criticità 1, 7).
- **Promessa "app iOS inclusa"** fragile a 250€/mese: account Apple ~99$/anno a carico tenant; il contratto deve distinguere Android incluso / iOS assistito (docs/20 C1).
- **Adozione clientela finale** = valore percepito dal tenant: i materiali di lancio (kit QR/locandine) e il follow-up CS a 30gg sono nel piano (docs/16 Fase 1) ma non ancora prodotti.
- **Stime di mercato non validate** con dati primari (docs/02): da verificare in Fase 0 commerciale.
- Concorrenza white label con pricing aggressivo: la difesa è il time-to-onboarding (<5gg) e la qualità del branding (KPI >70% approvazione al primo colpo).

## 18. Dipendenze future

- **Account/servizi da creare** (nessuno esiste): AWS (org + ambienti), progetti Firebase (pool, docs/29 §4), Apple Developer (piattaforma + processo account tenant), Google Play Console, gateway abbonamenti (Stripe Billing o equivalente — il modulo Billing ha solo tabelle), provider SMS (a consumo), dominio piattaforma + sottodomini tenant.
- **Decisioni aperte**: nome del prodotto/piattaforma (mai scelto); pricing add-on definitivi; testo contratti (titolarità app/dati alla cessazione — docs/17 criticità 17); DPA per tenant sanitari.
- **Tecniche**: scelta state management Flutter (docs suggeriscono stati espliciti, non vincolato); Octane sì/no in produzione; strumento ticketing supporto (build vs buy: deciso buy, docs/23 §7 A5).

## 19. Roadmap completa

| Orizzonte | Obiettivo | Riferimento |
|---|---|---|
| Adesso → Incremento 2 | Backend P0+P1 (gap screenshot) con aggiornamento docs | §12-13, GIUFFRIDA_FEATURE_GAP |
| +1 | App Flutter cliente white label (MVP flusso: config→catalogo→booking→prenotazioni→profilo) | docs/22-23, 27 |
| +2 | App gestionale + dashboard web onboarding/Brand Studio | docs/31 |
| +3 | Infra AWS + pipeline build + 2-3 tenant pilota REALI (Fase 0 commerciale, 0→30 tenant) | docs/16, 21, 32 |
| +4 | Spike scala (lock, iOS, Horizon) + billing gateway + campagne/report | docs/33, 15 |
| 6-12 mesi | Fase 1 commerciale (30→300): automazione onboarding completa, add-on SMS/multi-sede | docs/16 |
| 12-36 mesi | Verticali sanitari (DPIA, note cliniche), espansione, marketplace come canale discovery | docs/16 Fasi 2-3 |

KPI gate tra fasi (docs/16 §5): churn <3%/mese, onboarding <5gg, NPS >40, traiettoria break-even.

## 20. Prompt ideale per la prossima sessione Fable 5

```text
SEI CLAUDE CODE CON MODELLO FABLE 5.

Lavora nel progetto: /Users/gianlucabella/Desktop/app prenotazioni progetto

PRIMA DI TUTTO leggi, in quest'ordine:
1. MASTER_HANDOVER_FABLE5.md   (stato completo del progetto — fonte di verità)
2. GIUFFRIDA_FEATURE_GAP.md    (gap e priorità P0/P1/P2)
3. docs/34-code-review-backend.md (rilievi pianificati)

Poi verifica l'ambiente:
- export PATH="$HOME/.local/php-toolchain/bin:$PATH"
- cd platform-backend && php artisan test
  → DEVONO risultare 64+ test verdi prima di toccare qualsiasi cosa.

OBIETTIVO DELLA SESSIONE: Incremento 2 — chiudere i gap P0 e P1 del backend.

ORDINE OBBLIGATORIO:
1. Aggiorna la progettazione (docs/24, 25, 30) con: entità booking_requests,
   estensioni di GET /app/config (orari, contatti/social, legal, geo),
   campo note cliente sugli appuntamenti. NIENTE codice prima dei docs.
2. Implementa in TDD (test prima o insieme al codice, mai dopo):
   P0: note prenotazione · GET/PATCH /me · PUT /me/devices · PUT /me/consents ·
       POST /booking-requests + gestione staff · config estesa · foto staff + filtro sede
   P1: POST /me/gdpr/erasure · GET /me/notifications + mark-read · GET /app/min-version · lat/lng
3. Migrazioni SOLO additive (expand/contract, docs/32 §6).
4. Rispetta le convenzioni della sezione 3.4 dell'handover (tenancy fail-closed,
   date locali vs UTC, codici errore stabili, helper InteractsWithTenancy nei test).
5. Al termine: intera suite verde, code review con almeno 20 rilievi di cui i
   critici applicati, aggiorna README del backend, docs/34 e l'indice docs/00.

VINCOLI:
- Non installare nulla con sudo/Homebrew (non disponibili).
- Non riscrivere componenti funzionanti senza un rilievo motivato.
- Non promettere coverage misurata localmente (manca pcov: solo in CI).
- Lingua di lavoro: italiano. Niente mockup/demo/placeholder.

Se il cliente chiede invece l'app Flutter: leggi prima la sezione 15 punto 2
dell'handover (installazione SDK user-space) e docs/22 §3 + docs/27.
```

---

### Mappa rapida dei file

```
app prenotazioni progetto/
├── MASTER_HANDOVER_FABLE5.md      ← questo file
├── SCREEN_ANALYSIS.md             ← analisi 12 schermate riferimento
├── GIUFFRIDA_FEATURE_GAP.md       ← gap + priorità
├── IMG_97*.HEIC                   ← screenshot originali
├── .screens-analysis/*.jpg        ← conversioni leggibili
├── docs/
│   ├── 00-indice.md               ← indice generale (tutte le fasi)
│   ├── 01-17 …                    ← Fase 1 (prodotto/business)
│   ├── 20-33 …                    ← Fase 2 (progettazione tecnica)
│   └── 34-code-review-backend.md  ← review incremento 1
└── platform-backend/              ← Laravel: vedi README.md interno
    ├── app/Foundation/…           ← Tenancy, Auth(JWT/MFA/refresh), Audit, Http
    ├── app/Modules/…              ← TenantManagement, Branding, Catalog, Staff,
    │                                 Customers, Scheduling, Notifications
    ├── config/{jwt,api,booking,branding,sector_presets}.php
    ├── database/{migrations,factories,seeders}/
    ├── routes/api.php             ← tutte le route v1
    └── tests/{Unit,Feature,Concerns}/
```

Memoria persistente della sessione (auto-memory Claude): contiene già il modello a fasi del progetto e il toolchain locale — verrà caricata automaticamente.
