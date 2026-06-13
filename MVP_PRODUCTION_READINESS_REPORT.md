# MVP_PRODUCTION_READINESS_REPORT

**Data**: 12/06/2026 · **Auditor**: Senior QA Mobile, Lead Flutter Engineer, PM SaaS, Security Reviewer, Apple Compliance Reviewer, Google Play Release Manager (esercizio a sei ruoli) · **Oggetto**: MVP app cliente Flutter + backend Laravel · **Fonti**: handover, gap analysis, screen analysis, architecture review, README, codice reale, suite test (64 backend + 25 app + E2E ripetuto 5×), diagnosi runtime ([BACKEND_RUNTIME_STATUS.md](BACKEND_RUNTIME_STATUS.md)).

Domanda a cui risponde: **"Questa app può essere venduta domani a un professionista reale?"**

---

> ## ⚡ AGGIORNAMENTO 12/06/2026 — post MVP Stabilization
>
> La fase di stabilizzazione ha chiuso i rilievi bloccanti per la beta. Stato verificato: **backend 77/77 (318 assertion) · app 28/28 · analyzer 0 issues · E2E con verifica email reale via SMTP 2/2**.
>
> | Rilievo | Stato | Come |
> |---|---|---|
> | **S1 CRITICO** — link CRM su email non verificata | ✅ **RISOLTO + TESTATO** | Verifica email a 6 cifre (hash, 15', 5 tentativi, monouso, resend); link CRM **solo** a proprietà dimostrata; middleware `verified` su tutte le rotte identity-bound; 8 test dedicati incl. scenario attaccante e cross-user |
> | S2 ALTO — cancellazione account | ✅ RISOLTO | `DELETE /me` Apple-compliant: immediata, revoca sessioni (anche access token sul colpo successivo — fix guard), pseudonimizzazione, note eliminate, appuntamenti preservati, audit |
> | S3 MEDIO — privacy policy assente | ✅ RISOLTO | Consenso esplicito con versione+timestamp alla registrazione; URL legali white-label per tenant nel config, linkati in app |
> | F1 ALTO — refetch slot a ogni tap | ✅ RISOLTO | `select()` sugli input reali della fetch |
> | F2 ALTO — identità vuota dopo riavvio | ✅ RISOLTO | Idratazione `GET /me` al bootstrap |
> | Push foundation (§5 ALTO, parte) | ✅ BACKEND PRONTO | `PUT/DELETE /me/devices` con upsert e test; resta FCM client+APNs (lista B) |
> | Apple/Play deletion+privacy (§7-8) | ✅ CHIUSI | Vedi [APP_STORE_READINESS.md](APP_STORE_READINESS.md) per i residui (icone, Data Safety, pagina web Play) |
>
> **Giudizio aggiornato**: da "pronto beta privata (condizionato)" a **GO BETA CONTROLLATA senza riserve di sicurezza** — le condizioni residue sono operative (Sentry al giorno 1, staff via API), non più di privacy. Completamento prodotto vendibile: **~55% → ~63%** (il blocco vendita resta l'interfaccia professionista, lista B della [checklist](MVP_CLIENT_RELEASE_CHECKLIST.md)). Il resto di questo report riflette lo stato PRE-stabilizzazione ed è conservato come baseline.

---

# 1. Executive Summary

**Risposta breve: no, non domani — ma la distanza è misurabile e il nucleo è solido.**

- **Stato generale**: il *flusso del cliente finale* (registrazione → prenotazione → storico → cancellazione) è reale, collegato al backend, coperto da test ed è stato eseguito end-to-end 5 volte consecutive con successo. Il white label engine funziona dimostrabilmente (test "due tenant, stesso codice").
- **Il blocco di vendita non è tecnico, è di perimetro**: **non esiste alcuna interfaccia per il professionista**. Le API di gestione (agenda, conferme, no-show) esistono e sono testate, ma il barbiere non ha modo di vederle: niente app gestionale, niente dashboard web. Un prodotto che mostra le prenotazioni solo al cliente finale non è vendibile a chi paga 250€/mese.
- **Livello di maturità**: backend core **~80%** (mancano P0/P1 della gap analysis e billing) · app cliente **~70%** del journey osservato nel riferimento · lato professionista **0% di UI** · pipeline di pubblicazione store **0%** (progettata, non costruita).
- **Percentuale stimata di completamento del prodotto vendibile: ~55%.**
- **Giudizio finale: PRONTO PER BETA PRIVATA** (con le condizioni della sezione 10/A) — **NON pronto vendita, NON pronto produzione store**.

# 2. User Journey Audit

Percorso del nuovo cliente, valutato passo-passo (✅ verificato con esecuzione reale · ⚠️ funziona con riserva · ❌ assente):

| Step | Funziona? | Rischio / Errore possibile | Esperienza utente |
|---|---|---|---|
| Installazione app | ⚠️ solo via `flutter run`/build manuale | Nessuna pipeline di distribuzione (TestFlight/store) | n/a in questa fase |
| Splash | ✅ | Primo avvio offline senza cache → errore con retry (gestito) | Corretta: brand o spinner, mai schermata morta |
| Caricamento tenant | ✅ ETag/304 + cache offline | Config irraggiungibile alla prima installazione = app inutilizzabile (corretto che sia così) | Buona |
| Visualizzazione brand | ✅ nome, tagline, tema, colori dal config | Logo immagine non ancora nel payload (P0 config estesa) → brand solo tipografico | Accettabile per beta, povera per vendita |
| Registrazione | ✅ E2E reale | **Nessuna verifica email** → vedi Security #S1 (CRITICO) · password policy solo client+server min 10 | Form pulito, validazioni chiare |
| Login | ✅ | Credenziali errate → messaggio corretto; throttling → messaggio generico "(429)" | Buona |
| Home | ✅ prossimo appuntamento reale | Email utente vuota dopo riavvio app (manca `GET /me`) | Funzionale, spoglia rispetto al riferimento (niente foto/galleria) |
| Scelta servizio | ✅ multi-selezione con incompatibilità disabilitate | Varianti multiple: si prenota solo la default dalla card (dettaglio le mostra ma non le seleziona) | Buona; manca icona per servizio (riferimento le ha) |
| Scelta operatore | ✅ chip con filtro capacità | **Auto-selezione del primo operatore** → bias verso un dipendente, differisce dal riferimento (scelta esplicita); niente foto (P0 backend); niente "nessuna preferenza" | Da rivedere prima della vendita |
| Calendario | ✅ strip 30 giorni entro booking window | Giorni chiusi non attenuati in anticipo (si scopre col "nessun orario") → tap a vuoto | Sufficiente, migliorabile |
| Slot | ✅ Mattina/Pomeriggio, ricalcolo su cambio giorno/staff | **Re-fetch della disponibilità a ogni tap su uno slot** (Flutter #F1) → flicker e traffico inutile · orari mostrati nel fuso del device, non della sede (#F4) | Funziona ma "respira" troppo |
| Prenotazione | ✅ idempotenza verificata, 409 gestito con ricarica slot | Doppio submit protetto; rete instabile gestita (retry stessa chiave, testato) | Robusta — il punto più forte dell'app |
| Conferma | ✅ dati dal record persistito | Niente "aggiungi al calendario" (riferimento non ce l'ha esplicito; nice-to-have) | Chiara, distingue confermata/in attesa |
| Storico | ✅ in programma/passate | Paginazione: carica solo la prima pagina (20) senza load-more — irrilevante in beta, da fare per clienti storici | Buona |
| Cancellazione | ✅ dialog di conferma + cutoff 422 gestito | Nessun campo "motivo" (il backend lo accetta) | Buona |
| **Note alla prenotazione** | ❌ | Funzione visibile in OGNI schermata del riferimento — assente (P0 backend) | Il cliente del barbiere se ne accorgerà subito |
| **Richiesta senza slot** | ❌ | "Agenda piena = vicolo cieco" invece che lead (P0) | Perdita commerciale per il tenant |

# 3. Mobile UX Audit

- **Navigazione**: gerarchia semplice e coerente (home → flussi push, back naturale); redirect auth/config corretti. Nessun bottom-nav: per 5 destinazioni è una scelta difendibile ma il riferimento risulta più "app commerciale".
- **Tempi di caricamento**: config con ETag/304, availability cacheata server-side; localmente percepiti istantanei. Non misurati su device reale (ambiente senza simulatore) — da profilare in beta.
- **Loading/empty/error state**: presenti su ogni schermata dati (verificati nei widget test); retry sempre offerto; empty state del riferimento replicato testualmente.
- **Messaggi all'utente**: catalogo errori in italiano mappato sui codici stabili del backend; il fallback usa il messaggio server (inglese!) per codici nuovi → incoerenza linguistica possibile (#F6).
- **Accessibilità**: contrasto AA garantito a monte dal backend; touch target ≥44 ok sui pulsanti; mancano semantics label sistematiche su chip slot/giorni; nessun test screen reader (#F7).
- **Prima apertura**: splash → registrazione → home in 3 schermate, social login assente (email/password soltanto: più attrito del riferimento che è comunque email-based — accettabile).
- **Verdetto**: **MVP tecnico ben rifinito, non ancora app commerciale**: mancano i contenuti visivi (foto staff/sede/servizi, logo immagine) che nel settore beauty sono metà del valore percepito — tutti dipendenti dai gap P0 backend, non dal client.

# 4. Flutter Code Quality Review

Struttura feature-first pulita, DI via Riverpod, repository sottili, zero issues all'analyzer, 25 test + E2E. Problemi reali trovati:

| ID | Gravità | Problema | Impatto | Soluzione consigliata |
|---|---|---|---|---|
| F1 | **ALTO** | `availabilityProvider` osserva l'intero `bookingFlowProvider`: ogni `selectSlot` (che muta lo stato) invalida e **rifetcha la disponibilità a ogni tap** | Flicker UI, latenza percepita, traffico ×N per prenotazione | Osservare solo i campi rilevanti (`select((s) => (s.location, s.selectedVariantUuids)))` |
| F2 | **ALTO** | `SessionState.AuthenticatedSession(email: '')` dopo riavvio: l'identità mostrata dipende dal login in-sessione | Profilo "anonimo" alla seconda apertura; blocca personalizzazioni | Richiede `GET /me` (P0 backend) + idratazione al bootstrap |
| F3 | MEDIO | `BookingScheduleScreen._applyDefaults` in `addPostFrameCallback` legge provider asincroni: se lo staff non è ancora caricato, nessun operatore selezionato finché l'utente non tocca | Primo ingresso a volte senza default | Reagire al primo `AsyncData` dello staff, non al frame |
| F4 | MEDIO | Orari slot resi con `toLocal()` del device, non col fuso della sede | Cliente all'estero vede orari sbagliati rispetto al muro del negozio | Formattare nel `location.timezone` (dato già nel config) |
| F5 | MEDIO | Lista appuntamenti senza load-more (solo prima pagina cursor) | Storico lungo troncato silenziosamente | Infinite scroll su `next_page` |
| F6 | MEDIO | Fallback messaggi d'errore = stringa server (inglese) | Incoerenza linguistica | Fallback generico IT + telemetria sul codice sconosciuto |
| F7 | MEDIO | Semantics/accessibilità non sistematiche; nessun supporto dynamic type testato | Rischio store review accessibilità + UX ridotta | Pass dedicato con `Semantics` su chip e strip |
| F8 | BASSO | Nessun logger strutturato né breadcrumb (print assenti, ma anche diagnostica assente) | Debug in beta difficile | Logger leggero + hook su `ApiFailure` |
| F9 | BASSO | `runId` E2E e helper test ok, ma niente test widget per `BookingScheduleScreen` (lo schermo più complesso) | Regressioni UI non coperte | Widget test con provider override |
| F10 | BASSO | Memoria/leak: nessun pattern rischioso individuato (controller disposed, autoDispose corretto); crash potenziali: parsing difensivo ovunque | — | — |

# 5. Backend Integration Review

- **Autenticazione/refresh**: rotazione single-flight con replay verificata; riuso token rubato → revoca famiglia (testato lato backend). ✅
- **Scadenza sessione**: refresh respinto → `onSessionExpired` → router a login. Gap: lo stato del flusso prenotazione sopravvive ma l'utente riparte dal login senza spiegazione contestuale (messaggio "sessione scaduta" mostrato solo come snackbar se la chiamata era visibile). MEDIO.
- **Errori API**: envelope mappato; 429 throttle Laravel **non** è in envelope → `unexpected_error` con status (#F6 collegato). MEDIO.
- **Timeout**: connect 10s / receive 20s configurati. Nessun retry automatico sulle GET (scelta prudente, ok); retry manuale ovunque in UI.
- **Offline**: config da cache → app consultabile; scritture correttamente bloccate con messaggi; nessuna coda offline (per design, documentato).
- **Sincronizzazione**: invalidazione `appointmentsProvider` post-booking/cancel ✅; la disponibilità si aggiorna via versioned cache server ✅ (verificato in E2E: slot sparisce e ricompare).
- **Promemoria — ALTO**: le notifiche outbox vengono create, ma **nessun push arriverà mai sull'app**: manca `PUT /me/devices` (P0) e l'integrazione FCM client. Fallback email funziona solo con SES configurato in produzione. La promessa commerciale n.1 del prodotto ("promemoria automatici") oggi non raggiunge il telefono del cliente.

# 6. Security Audit

| ID | Gravità | Rilievo | Impatto | Soluzione |
|---|---|---|---|---|
| S1 | **CRITICO** | **Registrazione senza verifica email + auto-link CRM**: `POST /auth/register` collega l'account al customer CRM esistente con la stessa email (creato dallo staff/import). Chiunque registri l'email di un altro **eredita il suo storico appuntamenti** | Esposizione dati personali intra-tenant; violazione GDPR potenziale al primo caso reale | Verifica email obbligatoria prima del link CRM (o link differito a conferma); rate-limit già presente non basta |
| S2 | ALTO | Niente cancellazione account self-service (`/me/gdpr/erasure` non implementato) | Obbligo GDPR + blocco store (vedi §7/§8) | P1 già pianificato — diventa pre-beta-pubblica |
| S3 | MEDIO | Privacy policy/termini non esposti in app né nel config | GDPR artt. 13/14: informativa al momento della raccolta dati assente | Campo `legal` nel config (P0) + link in registrazione |
| S4 | BASSO | Token in Keychain/Keystore ✅, nessun log di token/PII trovato ✅, isolamento tenant verificato server-side ✅, HTTPS richiesto in staging/prod (dev http documentato) | — | Aggiungere certificate pinning in fase store (valutare) |
| S5 | BASSO | Logout offline = solo locale (refresh token resta valido server-side fino a scadenza/uso) | Finestra residua accettabile, già documentata | Revoca al riconnettersi (nice-to-have) |

# 7. Apple App Store Review Simulation

| Area | Esito simulato | Dettaglio |
|---|---|---|
| **Account deletion (5.1.1(v))** | ❌ **RIGETTO CERTO** | App con account e nessuna cancellazione in-app |
| **Privacy Policy (5.1.1)** | ❌ RIGETTO | Nessun link a privacy policy in app/metadata |
| Login | ✅ | Solo email/password proprietario → **Sign in with Apple NON obbligatorio** (scatta solo con login di terze parti) |
| Tracking / ATT | ✅ | Nessun tracking, nessun SDK pubblicitario → niente prompt ATT |
| Permessi | ✅ | Nessun permesso richiesto oggi (push arriverà con FCM: serve purpose string e richiesta contestuale — già nel design) |
| Pagamenti | ✅ | Servizi fisici pagati di persona → IAP non applicabile (3.1.3(e)) |
| Contenuti dinamici | ✅ | Config runtime di tema/contenuti è prassi accettata; nessun codice eseguibile scaricato |
| **App white label (4.2.6/4.3)** | ⚠️ RISCHIO STRUTTURALE | Mitigazione già a progetto: pubblicazione dall'account Apple del tenant + contenuti reali per app; **da validare con 2-3 pilota** prima di promesse contrattuali |
| ATS/HTTPS | ⚠️ | La build store deve puntare solo a https (nessuna eccezione ATS) — vincolo di pipeline |
| Asset | ❌ | Icona/splash sono ancora i default Flutter: pipeline branding nativa da costruire |

# 8. Google Play Review Simulation

| Area | Esito | Dettaglio |
|---|---|---|
| **Account deletion (policy 2024+)** | ❌ BLOCCO | Richiesta cancellazione in-app **e via link web** — entrambi assenti |
| **Data Safety form** | ❌ da compilare | Raccolti: email, nome, telefono (opz.), appuntamenti; nessuna condivisione terzi; va dichiarato con esattezza |
| Permissions | ✅ | Manifest minimale (INTERNET); push aggiungerà POST_NOTIFICATIONS con prompt contestuale |
| Account management | ⚠️ | Login/logout ok; manca gestione profilo (P0) |
| Security | ✅ | https in release, token sicuri, no cleartext in produzione (configurare `usesCleartextTraffic=false` in release — verifica di pipeline) |
| White label ripetitive content | ⚠️ | Strategia sharding account decisa in ARCHITECTURE_FINAL_REVIEW §6 — da implementare prima della scala |

# 9. Beta Cliente Reale ("se domani lo installa un barbiere")

**Cosa può rompere:**
1. Niente: il flusso cliente regge (5 E2E consecutivi). MA il barbiere **non vede le prenotazioni da nessuna parte** — dovremmo leggerle noi via API e girargliele. Showstopper pratico.
2. Il promemoria al cliente non arriva (niente push, email solo se SES attivo) → no-show non ridotti = promessa core mancata.
3. Un secondo cliente con l'email già in anagrafica (import/staff) può vedere lo storico del primo (S1).

**Cosa può confonderlo (lui o i suoi clienti):**
- Operatore preselezionato da solo; assenza foto staff/sede; impossibilità di scrivere "arrivo 5 min tardi" (note); giorni chiusi scoperti solo dopo il tap; messaggi inglesi su errori imprevisti.

**Cosa manca per farlo pagare:**
- Lato professionista (anche solo agenda mobile in sola lettura + conferma richieste)
- Promemoria funzionanti end-to-end
- I 5 P0 della gap analysis (note, profilo/devices, richiesta-senza-slot, config estesa, foto staff)
- Pacchetto legale (privacy, termini, DPA, contratto col canone)
- Onboarding reale del SUO brand (logo/colori dal Brand Studio — oggi via API)

# 10. Classificazione Finale

## A) PRONTO PER BETA PRIVATA (utilizzabile subito, con noi alla regia)
Flusso completo cliente finale su tenant pilota: registrazione, catalogo, scelta operatore, slot reali, prenotazione idempotente, storico, cancellazione · white label runtime (due tenant dimostrati) · gestione errori/offline · sicurezza token. **Condizioni**: dati clienti finti o consapevoli (finché S1 aperto), staff operato da noi via API, Sentry/crash report aggiunto al primo giorno di beta.

## B) DA RISOLVERE PRIMA DI VENDERE (bloccano il cliente pagante)
1. **Interfaccia professionista** (minimo: agenda + conferma richieste, mobile o web)
2. **S1 — verifica email prima del link CRM**
3. **Push end-to-end** (`PUT /me/devices` + FCM client + APNs)
4. P0 gap: note prenotazione, `GET/PATCH /me`, richiesta-senza-slot, config estesa (orari/contatti/legal/foto)
5. F1 (refetch per tap) e F2 (identità post-riavvio)
6. Pacchetto legale GDPR + contratto/billing attivazione

## C) DA RISOLVERE PRIMA DEGLI STORE
1. Cancellazione account in-app (+ link web per Play) — S2
2. Privacy policy in app e nei metadata store
3. Pipeline branding nativo (icone/splash per tenant) + https-only/ATS
4. `GET /app/min-version` + forced update
5. Data Safety (Play) / Privacy Nutrition Label (Apple)
6. Validazione pilota policy Apple 4.2.6 con account tenant; sharding account Play
7. Accessibilità pass (F7)

## D) FUTURE FEATURE (non bloccanti)
Galleria sede e avatar (P2) · waitlist automatica · "nessuna preferenza" operatore · selezione varianti multiple dalla card · motivo cancellazione · load-more storico · add-to-calendar · recensioni · pagamenti/acconti · EN completo · certificate pinning.

---

**Conteggio rilievi**: 1 CRITICO · 6 ALTI · 9 MEDI · 8 BASSI + 13 assenze funzionali già tracciate nella gap analysis. Nessun rilievo è stato corretto in questa sede (mandato di solo audit); ognuno ha gravità, motivo, impatto e soluzione nelle sezioni sopra.
