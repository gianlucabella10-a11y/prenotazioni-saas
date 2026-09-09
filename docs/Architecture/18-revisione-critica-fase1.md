# 18 — Revisione Critica Multi-Ruolo della Documentazione Fase 1

Analisi della documentazione 01-17 condotta da cinque prospettive professionali. Ogni problema è classificato per **Gravità** (Critica / Alta / Media / Bassa), **Impatto** e **Soluzione proposta**. Le soluzioni sono recepite nei documenti tecnici 19-27 (la "versione migliorata" della documentazione).

## A. Prospettiva CTO SaaS

### A1. Assenza totale di stack tecnologico nella Fase 1
- **Gravità**: Alta · **Impatto**: impossibile stimare costi, team, tempi senza scelte tecnologiche
- **Soluzione**: stack definito in questa fase (Flutter, Laravel, MySQL, Redis, S3, Firebase, AWS) — vedi [19-architettura-tecnica.md](19-architettura-tecnica.md)

### A2. Nessuna stima di costo infrastrutturale
- **Gravità**: Alta · **Impatto**: l'unit economics di [15-strategia-monetizzazione.md](../15-strategia-monetizzazione.md) resta non calcolabile; rischio di scoprire margini negativi a piattaforma costruita
- **Soluzione**: modello di costo AWS per fascia di tenant in [26-qualita-operazioni.md](26-qualita-operazioni.md)

### A3. KPI uptime 99.9% dichiarato senza architettura HA corrispondente
- **Gravità**: Alta · **Impatto**: 99.9% = max 43 min/mese di downtime; senza multi-AZ, health check e failover è una promessa contrattuale insostenibile
- **Soluzione**: architettura multi-AZ con RDS failover automatico definita in [19-architettura-tecnica.md](19-architettura-tecnica.md); SLA contrattuale da allineare (99.5% contrattuale, 99.9% obiettivo interno)

### A4. La pipeline di build mobile è il vero collo di bottiglia industriale, sottovalutato
- **Gravità**: Critica · **Impatto**: a 10.000 tenant con build native, ogni release del core = 10.000 build iOS+Android + 10.000 sottomissioni store. Insostenibile economicamente e operativamente con qualunque CI/CD
- **Soluzione**: ridisegno della strategia di distribuzione: PWA/architettura "runtime-branded" come default, build native riservate a fascia premium con limite numerico esplicito; uso di code-push/remote config per ridurre le ri-sottomissioni — vedi [24-white-label-multitenant-tech.md](24-white-label-multitenant-tech.md)

### A5. Nessun ambiente definito (dev/staging/prod) né strategia di rilascio
- **Gravità**: Media · **Impatto**: rischio di rilasci non testati su piattaforma che serve tutti i tenant simultaneamente
- **Soluzione**: ambienti e politica di deploy (blue/green, canary su tenant pilota) in [19-architettura-tecnica.md](19-architettura-tecnica.md)

## B. Prospettiva Senior Software Architect

### B1. "Schema condiviso con tenant_id" raccomandato senza meccanismo di enforcement concreto
- **Gravità**: Critica · **Impatto**: MySQL non ha Row-Level Security nativa (a differenza di PostgreSQL): l'isolamento dipende interamente dalla disciplina applicativa. Un singolo `where` dimenticato = data breach cross-tenant
- **Soluzione**: enforcement a tre livelli definito in [23-multitenant](24-white-label-multitenant-tech.md): (1) Global Scope Eloquent automatico su tutti i modelli tenant-aware, (2) `tenant_id` derivato esclusivamente dal token/contesto, mai dal payload client, (3) suite di test di isolamento obbligatoria in CI (RNF-21). Valutata e documentata l'alternativa PostgreSQL con RLS (scartata per vincolo di stack, mitigazioni compensative definite)

### B2. Engine disponibilità: requisito < 1s P95 senza progettazione dell'algoritmo
- **Gravità**: Alta · **Impatto**: il calcolo slot è O(operatori × giorni × prenotazioni); senza caching e precomputazione il requisito non regge sotto carico
- **Soluzione**: progettazione dedicata dell'Availability Engine con cache Redis e invalidazione su eventi in [25-sistemi-core.md](25-sistemi-core.md)

### B3. Atomicità prenotazione (RNF-20) dichiarata ma non progettata
- **Gravità**: Critica · **Impatto**: race condition = doppie prenotazioni = perdita di fiducia immediata del tenant
- **Soluzione**: lock pessimistico con vincolo di unicità a livello DB + lock distribuito Redis per il flusso di checkout, progettato in [25-sistemi-core.md](25-sistemi-core.md)

### B4. Nessuna gestione di idempotenza delle API
- **Gravità**: Alta · **Impatto**: retry di rete da mobile (frequenti) possono creare prenotazioni duplicate anche senza race condition
- **Soluzione**: header `Idempotency-Key` obbligatorio sulle mutazioni critiche — [22-api-rest.md](22-api-rest.md)

### B5. Fusi orari e DST trattati come "criticità pianificata" ma architetturalmente strutturali
- **Gravità**: Alta · **Impatto**: il passaggio ora legale/solare con orari memorizzati male produce slot sbagliati due volte l'anno per tutti i tenant
- **Soluzione**: regola architetturale vincolante: istanti in UTC, regole di disponibilità in ora locale del tenant con timezone IANA esplicita, conversione solo al boundary — [21-database-er.md](21-database-er.md), [25-sistemi-core.md](25-sistemi-core.md)

### B6. Nessun versionamento API definito
- **Gravità**: Media · **Impatto**: con app mobile distribuite (aggiornamento non forzabile), breaking change = app rotte sui dispositivi degli utenti finali
- **Soluzione**: versioning `/v1/` + politica di deprecazione minima 12 mesi + endpoint di minimum-supported-version per forzare aggiornamento controllato — [22-api-rest.md](22-api-rest.md)

### B7. Eventi di dominio assenti: i flussi 3-7 di Fase 1 sono impliciti orchestrazioni sincrone
- **Gravità**: Media · **Impatto**: accoppiamento forte (prenotazione → notifiche → waitlist → statistiche tutte in transazione) = latenza e fragilità
- **Soluzione**: catalogo eventi di dominio (BookingCreated, BookingCancelled, ...) con dispatch asincrono via code — [20-ddd-clean-architecture.md](20-ddd-clean-architecture.md)

## C. Prospettiva Senior Product Manager

### C1. Nessuna definizione di MVP tecnico misurabile: lo scope MVP di Fase 1 è ancora troppo ampio
- **Gravità**: Alta · **Impatto**: time-to-market dilatato; il valore si valida con prenotazione+notifiche, non con waitlist/recensioni/report
- **Soluzione**: ri-prioritizzazione in tre release (R1 core booking, R2 engagement, R3 monetizzazione add-on) in [19-architettura-tecnica.md](19-architettura-tecnica.md) §9

### C2. Offline/connettività degradata mai considerata per l'app mobile
- **Gravità**: Media · **Impatto**: target = negozi fisici, connettività variabile; un'app che mostra schermata bianca senza rete danneggia la percezione white label
- **Soluzione**: strategia offline-first parziale (cache locale catalogo/appuntamenti, coda azioni) nella progettazione Flutter — [20-ddd-clean-architecture.md](20-ddd-clean-architecture.md) §6

### C3. Il "gestionale operatore" non ha forma definita (app? web?)
- **Gravità**: Media · **Impatto**: il barbiere lavora in piedi col telefono: senza app operatore mobile il prodotto è incompleto rispetto al riferimento funzionale
- **Soluzione**: seconda app Flutter "Business" (unica per tutti i tenant, brand piattaforma, login per tenant — non richiede white label) — [19-architettura-tecnica.md](19-architettura-tecnica.md)

### C4. Cancellazione account in-app: requisito obbligatorio degli store, assente
- **Gravità**: Alta · **Impatto**: Apple richiede la cancellazione account in-app per app con registrazione: il rigetto in review è certo
- **Soluzione**: aggiunto endpoint e flusso di cancellazione account self-service — [22-api-rest.md](22-api-rest.md), allineato a GDPR

### C5. Nessun flusso di consenso/verifica per telefono (OTP) nonostante login via telefono dichiarato (RF-29)
- **Gravità**: Media · **Impatto**: login telefonico senza OTP = account takeover banale; OTP SMS ha costi non modellati
- **Soluzione**: OTP via SMS con rate limiting severo e costi inclusi nel modello — [23-auth-sicurezza.md](23-auth-sicurezza.md)

## D. Prospettiva Senior Flutter Architect

### D1. "Un'app per tenant" in Flutter = flavor per tenant: ingestibile oltre poche decine
- **Gravità**: Critica · **Impatto**: i flavor/bundle id per tenant esplodono in complessità di firma, provisioning profile iOS, gestione certificati
- **Soluzione**: progetto Flutter unico con **theming runtime guidato da configurazione remota** (design token JSON per tenant); la "build dedicata" inietta solo: bundle id, icona, splash, nome — generati da tool di build automatizzato, non flavor manuali — [24-white-label-multitenant-tech.md](24-white-label-multitenant-tech.md)

### D2. Firebase: un progetto FCM unico o per tenant? Non definito
- **Gravità**: Alta · **Impatto**: ogni app iOS con bundle id proprio richiede registrazione app nel progetto Firebase + chiave APNs; un progetto Firebase ha limiti di app registrabili
- **Soluzione**: progetto Firebase unico per le app PWA/container + provisioning automatizzato di app Firebase per le build native premium, con sharding su più progetti Firebase oltre soglia — [25-sistemi-core.md](25-sistemi-core.md) §3

### D3. Nessuna strategia di aggiornamento forzato dell'app
- **Gravità**: Media · **Impatto**: versioni vecchie con bug o API deprecate restano in uso indefinitamente
- **Soluzione**: endpoint `GET /v1/app-config` con `min_supported_version` e flusso di force-update in app — [22-api-rest.md](22-api-rest.md)

### D4. Deep linking non progettato (necessario per marketplace futuro, notifiche, kit di lancio QR)
- **Gravità**: Bassa · **Impatto**: rework futuro
- **Soluzione**: universal links/app links previsti fin dall'inizio nella struttura di navigazione Flutter

## E. Prospettiva Senior Laravel Architect

### E1. Nessuna scelta sul pacchetto/approccio multi-tenancy Laravel
- **Gravità**: Alta · **Impatto**: retrofitting della tenancy su un codebase avviato è costosissimo
- **Soluzione**: tenancy single-database con Global Scopes + middleware di risoluzione tenant (da subdominio/header/token), architettura definita prima della prima riga di codice — [24-white-label-multitenant-tech.md](24-white-label-multitenant-tech.md)

### E2. Code e job: Redis come unico broker senza progettazione di code separate
- **Gravità**: Media · **Impatto**: un broadcast da 50k notifiche in coda unica ritarda i promemoria transazionali (noisy neighbor interno)
- **Soluzione**: code separate per priorità (`critical`, `default`, `bulk`) con worker dedicati e rate limiting per tenant sulle bulk — [25-sistemi-core.md](25-sistemi-core.md)

### E3. Scheduler: promemoria basati su polling ogni 15 min (Flusso 6) ha edge case
- **Gravità**: Bassa · **Impatto**: finestre di polling perdono promemoria se il job salta; doppio invio se sovrapposto
- **Soluzione**: job schedulati per-appuntamento (delayed jobs) creati alla conferma + riconciliazione periodica idempotente come safety net

### E4. MySQL: nessuna strategia per indici e per le query "hot" (disponibilità, agenda)
- **Gravità**: Alta · **Impatto**: full scan su tabella appuntamenti condivisa fra tutti i tenant = degrado globale
- **Soluzione**: indici compositi `(tenant_id, ...)` come prefisso obbligatorio su ogni tabella tenant-aware, definiti in [21-database-er.md](21-database-er.md)

### E5. Migrazioni a singolo schema condiviso: nessuna politica di migrazione online
- **Gravità**: Media · **Impatto**: `ALTER TABLE` bloccante su tabella prenotazioni grande = downtime per tutti
- **Soluzione**: politica di migrazione online (algoritmi INSTANT/INPLACE, gh-ost per le pesanti, finestre programmate) — [26-qualita-operazioni.md](26-qualita-operazioni.md)

## F. Problemi commerciali (sintesi cross-ruolo)

### F1. Il prezzo 250€/mese impone un costo infrastrutturale per tenant < ~15-20€ per margini SaaS sani
- **Gravità**: Alta · **Impatto**: con build native + SMS + supporto, il costo per tenant può superare la soglia in fascia bassa di volume
- **Soluzione**: modello di costo per tenant con budget esplicito in [26-qualita-operazioni.md](26-qualita-operazioni.md); SMS sempre a consumo rifatturato; build native solo fascia premium

### F2. La promessa "app sullo store col tuo nome" inclusa nel piano Base (Fase 1, doc 10) è in conflitto con A4/D1
- **Gravità**: Alta · **Impatto**: promessa commerciale che non scala
- **Soluzione**: revisione del packaging: Base = PWA brandizzata + presenza nel container; "App Store dedicata" = add-on con prezzo dedicato che copre i costi reali (account developer, build, mantenimento). Questa modifica al posizionamento commerciale è la correzione più importante della Fase 2 e va recepita nel contratto

### F3. Nessuna definizione di SLA contrattuale e penali
- **Gravità**: Media · **Impatto**: dispute con tenant in caso di disservizio
- **Soluzione**: SLA contrattuale 99.5% con crediti di servizio; obiettivo interno 99.9%

## Esito

Tutti i problemi sopra elencati hanno soluzione progettata nei documenti 19-27. L'audit tecnico finale ([27-audit-tecnico.md](27-audit-tecnico.md)) verifica la copertura e aggiunge ulteriori criticità di dettaglio fino a superare le 50 richieste.
