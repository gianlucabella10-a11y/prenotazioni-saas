# GIUFFRIDA_FEATURE_GAP — Gap analysis funzionale

Confronto tra le funzionalità osservate negli screenshot di riferimento ([SCREEN_ANALYSIS.md](SCREEN_ANALYSIS.md)) e lo stato attuale di: backend implementato (`platform-backend/`, 64 test verdi), API esposte, documentazione di progettazione (docs/20-33).

Legenda: ✅ replicata e testata · 🔷 progettata nei docs ma non implementata · ⚠️ parziale · ❌ assente anche dalla progettazione

---

## 1. Funzionalità replicate (backend + API funzionanti)

| Funzionalità osservata | Implementazione | Verifica |
|---|---|---|
| Multi-sede per tenant | `locations` + `location_schedules`; sedi nel payload `GET /app/config` | WhiteLabelConfigTest |
| Catalogo servizi con durata e prezzo | `services`/`service_variants`; `GET /catalog/services` | Feature test |
| Dettaglio servizio (ⓘ) | `services.description` esposta | — |
| Selezione multipla servizi nella stessa visita | `variant_uuids[]` su availability e booking, item concatenati | BookingFlowTest (overlap su durata composta) |
| Scelta operatore | `GET /staff` con servizi abilitati; `staff_uuid` nel booking | AvailabilityEndpointTest |
| Slot dinamici per giorno/operatore | Availability engine timezone-aware con cache versionata | AvailabilityCalculatorTest (incl. DST), endpoint test |
| Giorni chiusi / eccezioni (ferie) | `schedule_exceptions` (closed/open_extra, sede o staff) | Test chiusura giornata |
| Doppia fascia oraria giornaliera (pausa pranzo) | Regole multiple per weekday | Unit test "lunch break" |
| Prenotazione confermata istantanea | `BookAppointment` transazionale, anti-double-booking | BookingFlowTest 409 |
| Modalità approvazione manuale | `booking_confirmation_mode=request_approve` → stato `requested` | BookingFlowTest |
| Lista prenotazioni future/passate | `GET /appointments?scope=upcoming|past` | — |
| Cancellazione con cutoff | `POST /appointments/{uuid}/cancel` (422 `cutoff_passed` oltre soglia) | BookingFlowTest |
| Orari di apertura per sede | `location_schedules` in DB | ⚠️ non ancora esposti al client (vedi §3.4) |
| Logout | `POST /auth/logout` (revoca refresh token) | AuthFlowTest |
| Splash brandizzata per tenant | Strategia thin-shell: splash compilata nella build (docs/27 §1) | 🔷 lato app Flutter |

## 2. Funzionalità progettate ma non ancora implementate (🔷)

| Funzionalità | Dove è progettata | Cosa manca |
|---|---|---|
| Profilo cliente (visualizza/modifica) | docs/25 §3 `GET/PATCH /me` | Endpoint |
| Registrazione device push | docs/25 §3 `PUT /me/devices` (tabella `devices` già migrata) | Endpoint |
| Gestione consensi dal profilo | docs/25 §3 `PUT /me/consents` (tabella `consents` già migrata) | Endpoint |
| **Cancella profilo** (obbligo Apple + GDPR) | docs/25 §3 `POST /me/gdpr/erasure`, review #11 | Endpoint + flusso anonimizzazione |
| Export dati personali | docs/25 §3 `POST /me/gdpr/export` | Endpoint + job |
| Privacy policy / Termini per tenant | docs/27 §2 (campo `legal` nella WhiteLabelConfig) | Campi su brand_profiles + payload config |
| Versione minima app | docs/25 §2 `GET /app/min-version` | Endpoint |
| Waitlist automatica | docs/30 §7 (tabella `waitlist_entries` già migrata) | Use case + endpoint |
| Foto staff | colonna `photo_path` esiste | Upload + URL firmato in `GET /staff` |

## 3. Funzionalità mancanti anche dalla progettazione (❌ — emerse SOLO dagli screenshot)

### 3.1 Richiesta di prenotazione senza slot ("INVIA RICHIESTA")
Quando il giorno scelto non ha disponibilità, l'app di riferimento permette di inviare una **richiesta libera** (giorno + operatore + note, senza orario) che lo staff gestisce manualmente. È diversa dalla nostra waitlist (notifica automatica quando si libera uno slot) e dalla modalità request_approve (che richiede comunque uno slot). Commercialmente è importante: trasforma l'agenda piena in un lead invece che in un rifiuto.
**Impatto modello dati**: una richiesta non è un appuntamento con orario → serve un'entità `booking_requests` (tenant, customer, sede, servizi, giorno preferito, operatore preferito, note, stato pending/proposta/convertita/rifiutata) con conversione in appuntamento da parte dello staff.

### 3.2 Centro avvisi in-app ("Avvisi")
Lista consultabile delle notifiche ricevute (campanella in S4 e voce in S12). Il nostro outbox (`notification_records`) contiene già tutto il necessario; manca la lettura lato cliente con stato letto/non letto.

### 3.3 Note del cliente sulla prenotazione
Campo "Note" libero presente in ogni variante dello step 2. Il nostro `appointments` non ha una colonna note cliente (abbiamo solo `customer_notes` interne dello staff, che sono un'altra cosa).

### 3.4 Scheda "Informazioni negozio" completa
- **Orari di apertura esposti al client** (con chiusi/aperto-oggi): i dati ci sono, il payload config non li include
- **Galleria foto sede**: nessun supporto asset per le sedi (brand_assets copre solo logo/icona/splash)
- **Contatti social**: telefono c'è (`locations.phone`); mancano sito web, Facebook, Instagram, WhatsApp
- **Coordinate geografiche** per "Apri sul navigatore": previste in docs/24 (lat/lng) ma non incluse nella migrazione implementata

### 3.5 Filtro staff per sede nell'API pubblica
`GET /staff` restituisce tutti gli operatori del tenant; con multi-sede serve il filtro per sede (gli orari per sede esistono già in `staff_schedules`).

### 3.6 Avatar/foto profilo del cliente
Visibile nel profilo (S12). Nessun campo avatar su users/customers.

### 3.7 Servizi "visibili ma non prenotabili"
Lo screenshot S5 mostra un servizio in griglia ma disabilitato. Il nostro `is_active=false` nasconde del tutto il servizio. Manca uno stato intermedio `is_bookable_online` (visibile a listino, prenotabile solo in negozio).

## 4. Endpoint mancanti (riepilogo operativo)

| # | Endpoint | Scopo | Stato progettazione |
|---|---|---|---|
| 1 | `GET /me`, `PATCH /me` | Profilo cliente | 🔷 docs/25 |
| 2 | `PUT /me/devices` | Token FCM (prerequisito per le push reali) | 🔷 docs/25 |
| 3 | `PUT /me/consents` | Opt-in/out granulari | 🔷 docs/25 |
| 4 | `POST /me/gdpr/erasure` | Cancella profilo (obbligo store) | 🔷 docs/25 |
| 5 | `POST /me/gdpr/export` | Export dati | 🔷 docs/25 |
| 6 | `GET /me/notifications` (+ mark-read) | Centro avvisi in-app | ❌ nuovo |
| 7 | `POST /booking-requests` (+ gestione in `/manage`) | Richiesta senza slot | ❌ nuovo |
| 8 | `GET /app/min-version` | Forced update | 🔷 docs/25 |
| 9 | Estensione `GET /app/config` | Orari apertura per sede, social/contatti, legal URLs, galleria, lat/lng | ⚠️ payload da estendere |
| 10 | Estensione `GET /staff` | `photo_url`, filtro `?location_uuid=` | ⚠️ da estendere |
| 11 | Campo `notes` su `POST /appointments` | Note cliente | ❌ nuovo (campo + validazione) |
| 12 | Upload media sede + avatar (URL pre-firmati S3) | Galleria e profilo | ❌ nuovo |
| 13 | `POST /waitlist` | Waitlist automatica | 🔷 docs/25-30 |

## 5. Priorità implementative

### P0 — Parità funzionale percepita dal cliente finale (blocca la vendibilità)
1. **Note sulla prenotazione** (#11) — campo singolo, alto valore percepito, tocca booking
2. **Profilo + devices + consensi** (#1-3) — senza `PUT /me/devices` le push non possono funzionare in produzione
3. **Richiesta senza slot** (#7) — differenziatore commerciale osservato; richiede nuova entità
4. **Config estesa: orari + contatti + legal** (#9 parte) — la scheda negozio è la vetrina del tenant
5. **Foto staff esposte** (#10) — la scelta dell'operatore con foto è centrale nel flusso osservato

### P1 — Obblighi e robustezza pre-pubblicazione store
6. **Cancella profilo** (#4) — Apple rifiuta app con account senza cancellazione self-service
7. **Centro avvisi** (#6) — lettura outbox, basso sforzo, completa il quadro notifiche
8. **min-version** (#8) — va in produzione PRIMA della prima app pubblicata, o non sarà mai affidabile
9. **lat/lng + navigatore, filtro staff per sede** (#9-10 parte)

### P2 — Completamento esperienza
10. **Galleria foto sede + avatar cliente** (#12) — richiede pipeline upload S3 con validazione
11. **Waitlist automatica** (#13) — la richiesta manuale (P0.3) ne copre il bisogno primario nel frattempo
12. **Export GDPR** (#5) — obbligo normativo con finestra di risposta (30 gg), può seguire il lancio pilota di poche settimane
13. **Stato `is_bookable_online`** sui servizi (§3.7)

### Non emerse dagli screenshot (restano da roadmap esistente)
Campagne marketing, recensioni, pagamenti/acconti, import CSV clienti, dashboard web UI: priorità invariate rispetto a docs/10 e 16.

---

**Conclusione**: il core transazionale osservato (multi-sede, multi-servizio, scelta operatore, slot dinamici, cancellazione, approvazione manuale) è **replicato e coperto da test**. I gap sono concentrati su: superficie profilo/GDPR (progettata, da implementare), scheda negozio ricca (estensioni di config), e un flusso nuovo emerso solo dagli screenshot — la **richiesta di prenotazione senza slot** — che va aggiunto alla progettazione (docs/24-25-30) prima dello sviluppo del prossimo incremento.
