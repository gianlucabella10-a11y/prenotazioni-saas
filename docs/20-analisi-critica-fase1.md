# 20 — Analisi Critica Multi-Ruolo della Documentazione Fase 1

Revisione della documentazione 01-17 condotta da cinque prospettive professionali: CTO SaaS, Senior Software Architect, Senior Product Manager, Senior Flutter Architect, Senior Laravel Architect.

Scala di gravità: **CRITICA** (blocca o invalida il progetto se non risolta) / **ALTA** (danno rilevante a business o architettura) / **MEDIA** (degrada qualità o efficienza) / **BASSA** (miglioramento opportuno).

Ogni problema indica dove la soluzione è stata recepita nella documentazione tecnica di Fase 2 (documenti 21-33).

---

## A. Prospettiva CTO SaaS

### A1. Nessuno stack tecnologico era stato vincolato — ora vincolato, vanno verificate le implicazioni
- **Tipo**: lacuna | **Gravità**: ALTA
- **Impatto**: la Fase 1 lascia aperte decisioni (PWA vs nativa, RLS database) che con lo stack imposto (Flutter, Laravel, MySQL, Redis, S3, Firebase, AWS) hanno risposte obbligate diverse: MySQL **non ha Row-Level Security nativa** (citata come opzione in 12-strategia-multi-tenant.md), e Flutter rende il percorso "PWA prima, nativa poi" meno conveniente di un percorso "nativa subito con flavor per tenant".
- **Soluzione**: isolamento multi-tenant enforzato a livello applicativo Laravel (global scope + test dedicati) con difese aggiuntive (vedi [28-multi-tenant-tecnico.md](28-multi-tenant-tecnico.md)); strategia di distribuzione rivista per Flutter in [27-white-label-tecnico.md](27-white-label-tecnico.md).

### A2. Costo operativo della pipeline di build per migliaia di app sottostimato
- **Tipo**: rischio commerciale/tecnico | **Gravità**: ALTA
- **Impatto**: a 10.000 tenant, un aggiornamento del core Flutter significa 10.000 rebuild + 10.000 submission agli store. Anche con piena automazione, i tempi di revisione Apple e i limiti di submission rendono il ciclo di rilascio dell'ordine di settimane e i costi di build (minuti CI/CD) significativi.
- **Soluzione**: architettura app "thin shell + configurazione remota": la build per-tenant contiene solo branding statico (icona, nome, splash); tutto il resto (tema, contenuti, feature flag) è caricato a runtime dal backend. I rebuild massivi servono solo per aggiornamenti del motore Flutter, pianificati 3-4 volte l'anno. Dettagli in [27-white-label-tecnico.md](27-white-label-tecnico.md) §4.

### A3. Mancanza di ambiente di staging e strategia di rilascio nella documentazione
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: con un solo ambiente, ogni rilascio è un rischio per tutti i tenant simultaneamente.
- **Soluzione**: ambienti dev/staging/produzione, rilasci canary su tenant interni di test, definiti in [21-architettura-generale.md](21-architettura-generale.md) §7 e [32-qualita-aws.md](32-qualita-aws.md).

### A4. Budget infrastrutturale non stimato
- **Tipo**: lacuna commerciale | **Gravità**: ALTA
- **Impatto**: impossibile validare l'unit economics (15-strategia-monetizzazione.md) senza una stima dei costi AWS per fascia di tenant.
- **Soluzione**: modello di costo AWS per fase di crescita in [32-qualita-aws.md](32-qualita-aws.md) §3.

### A5. Vendor lock-in Firebase non discusso
- **Tipo**: rischio | **Gravità**: BASSA
- **Impatto**: FCM è gratuito e di fatto obbligatorio per push Android; il lock-in è accettabile, ma va isolato dietro un'interfaccia.
- **Soluzione**: Notification Engine con astrazione canale (FCM/APNs via FCM/email/SMS) in [29-notifiche-tecnico.md](29-notifiche-tecnico.md) §2.

---

## B. Prospettiva Senior Software Architect

### B1. "Registro tenant" descritto come servizio ma lo stack è un monolite
- **Tipo**: errore di coerenza | **Gravità**: MEDIA
- **Impatto**: 12-strategia-multi-tenant.md parla di "servizi che consultano il registro con cache locale di fallback", linguaggio da microservizi. Con Laravel monolitico il registro è una tabella + cache Redis: la mitigazione corretta è la ridondanza di MySQL/Redis, non cache di fallback per-servizio.
- **Soluzione**: ridefinito come `tenants` table + cache Redis con TTL e fallback al DB, in [28-multi-tenant-tecnico.md](28-multi-tenant-tecnico.md) §3.

### B2. Atomicità della prenotazione (RNF-20) senza strategia di locking definita
- **Tipo**: lacuna | **Gravità**: CRITICA
- **Impatto**: doppie prenotazioni = perdita di fiducia immediata del cliente finale e del tenant. Il requisito esiste ma manca il disegno.
- **Soluzione**: progettazione completa del meccanismo (transazione MySQL + vincolo di unicità sullo slot + lock Redis per UX) in [30-engine-appuntamenti.md](30-engine-appuntamenti.md) §4.

### B3. Calcolo disponibilità: nessuna analisi di complessità né strategia di caching
- **Tipo**: lacuna di scalabilità | **Gravità**: ALTA
- **Impatto**: l'endpoint disponibilità è il più chiamato della piattaforma (ogni cliente che apre il calendario). Senza caching, RNF-01 (< 1s p95) non regge a scala.
- **Soluzione**: precomputazione + cache Redis con invalidazione su scrittura, [30-engine-appuntamenti.md](30-engine-appuntamenti.md) §3.

### B4. Soft delete / immutabilità storica non specificate
- **Tipo**: lacuna | **Gravità**: ALTA
- **Impatto**: se un tenant elimina un servizio o un operatore, gli appuntamenti storici che li referenziano si rompono (reportistica, storico cliente, obblighi fiscali).
- **Soluzione**: soft delete su entità referenziate da storico + snapshot denormalizzato (nome/prezzo del servizio "congelati" sull'appuntamento), [24-database-er.md](24-database-er.md) §5.

### B5. Fusi orari e DST: il problema è più insidioso di quanto descritto
- **Tipo**: errore potenziale | **Gravità**: ALTA
- **Impatto**: la criticità 10 della Fase 1 propone "UTC + conversione". Per gli **orari di apertura ricorrenti** questo è sbagliato: "aperto 9:00-18:00" è ora locale del salone e attraversa i cambi ora legale. Memorizzare le regole ricorrenti in UTC genera errori due volte l'anno.
- **Soluzione**: regole ricorrenti (orari, turni) memorizzate in **ora locale + timezone IANA del tenant/sede**; solo gli appuntamenti concreti (istanti) memorizzati in UTC. [30-engine-appuntamenti.md](30-engine-appuntamenti.md) §2.

### B6. Versioning API non previsto
- **Tipo**: lacuna | **Gravità**: ALTA
- **Impatto**: con app mobile distribuite (aggiornamento non forzabile), il backend deve servire versioni API multiple per mesi.
- **Soluzione**: versioning `/api/v1` + politica di deprecazione + forced update minimale lato app, [25-api-rest.md](25-api-rest.md) §2.

### B7. Idempotenza delle operazioni di scrittura non prevista
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: su mobile con rete instabile, un retry della stessa richiesta di prenotazione può creare duplicati.
- **Soluzione**: header `Idempotency-Key` sulle POST critiche, [25-api-rest.md](25-api-rest.md) §6.

---

## C. Prospettiva Senior Product Manager

### C1. La promessa "app sullo store col tuo nome" inclusa nel piano Base a 250€/mese è economicamente fragile
- **Tipo**: problema commerciale | **Gravità**: ALTA
- **Impatto**: l'account Apple Developer (~99 USD/anno per tenant se account proprio del tenant), il lavoro di submission e i rischi di rejection rendono il costo reale della "app nativa per tutti" incompatibile col canone base a basso volume.
- **Soluzione**: piano Base = app nativa Android (Play Store, costi marginali bassi) + iOS tramite app su account Apple del tenant con supporto guidato, oppure add-on "iOS managed". Revisione del packaging in [27-white-label-tecnico.md](27-white-label-tecnico.md) §6 — da validare commercialmente in Fase 0.

### C2. Nessuna definizione del processo di rinnovo consenso/contratto per i clienti finali quando il tenant cessa
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: alla cessazione di un tenant, i clienti finali hanno l'app installata che smette di funzionare senza spiegazione → danno reputazionale anche per la software house.
- **Soluzione**: stato "tenant cessato" gestito dall'app con messaggio configurabile e graceful degradation, [27-white-label-tecnico.md](27-white-label-tecnico.md) §7.

### C3. Mancano stati intermedi dell'appuntamento (richiesta vs conferma)
- **Tipo**: funzionalità mancante | **Gravità**: MEDIA
- **Impatto**: alcuni professionisti (medici, consulenti) vogliono approvare manualmente le richieste; la Fase 1 assume conferma automatica per tutti.
- **Soluzione**: macchina a stati con modalità per-tenant "auto-confirm" o "request-approve", [30-engine-appuntamenti.md](30-engine-appuntamenti.md) §5.

### C4. Nessun supporto a prenotazioni multi-servizio nella stessa visita
- **Tipo**: funzionalità mancante | **Gravità**: MEDIA
- **Impatto**: "taglio + barba" come due servizi in sequenza è il caso d'uso più comune nei barbershop; la Fase 1 lo gestisce solo come "pacchetto" predefinito.
- **Soluzione**: appuntamento con righe multiple (appointment_items) e calcolo disponibilità su durata composta, [24-database-er.md](24-database-er.md) e [30-engine-appuntamenti.md](30-engine-appuntamenti.md) §6.

### C5. Onboarding senza "stato di completamento" misurabile
- **Tipo**: lacuna | **Gravità**: BASSA
- **Impatto**: il KPI "tasso di attivazione > 85%" (01-prd.md) non è misurabile senza checklist di onboarding persistita.
- **Soluzione**: campo `onboarding_state` (JSON checklist) su tenants, esposto al pannello Super Admin, [24-database-er.md](24-database-er.md).

---

## D. Prospettiva Senior Flutter Architect

### D1. "Una codebase, mille app" richiede una strategia di flavor/target esplicita
- **Tipo**: lacuna | **Gravità**: CRITICA
- **Impatto**: non si possono creare 10.000 flavor manuali; serve generazione automatica della configurazione di build (bundle id, icone, splash, nome) a partire dal tenant registry.
- **Soluzione**: pipeline che genera i file di configurazione nativi (Android/iOS) da template + un singolo "white label config" per tenant; build parametrica con `--dart-define`, [27-white-label-tecnico.md](27-white-label-tecnico.md) §3.

### D2. Tema dinamico vs tema compilato
- **Tipo**: decisione mancante | **Gravità**: ALTA
- **Impatto**: se i colori sono compilati nella build, ogni cambio colore del tenant richiede una nuova submission allo store (giorni). Inaccettabile per la promessa "configurabile da dashboard".
- **Soluzione**: tema caricato a runtime dall'endpoint di configurazione brand, con cache locale e fallback agli asset compilati per il primo avvio offline, [27-white-label-tecnico.md](27-white-label-tecnico.md) §4.

### D3. Push notification multi-app: serve un progetto Firebase per app o uno condiviso?
- **Tipo**: lacuna tecnica | **Gravità**: ALTA
- **Impatto**: ogni app iOS/Android con bundle id proprio deve essere registrata su Firebase; un progetto Firebase ha un limite pratico di app registrabili — a migliaia di tenant serve una strategia a più progetti.
- **Soluzione**: pool di progetti Firebase gestiti via API amministrativa, mapping tenant→progetto nel registry, invio FCM con credenziali per-progetto, [29-notifiche-tecnico.md](29-notifiche-tecnico.md) §4.

### D4. Offline/connettività intermittente non considerata
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: l'app cliente in negozio (Wi-Fi instabile) deve almeno mostrare gli appuntamenti già caricati.
- **Soluzione**: cache locale read-only (ultimi dati noti) + coda di retry idempotente per le scritture, [23-ddd-clean-architecture.md](23-ddd-clean-architecture.md) §6.

### D5. App staff/operatore non distinta dall'app cliente
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: la Fase 1 menziona "app/portale gestionale" senza decidere. Distribuire la dashboard come app white label per ogni tenant raddoppierebbe le build.
- **Soluzione**: **una sola app gestionale** (brand della piattaforma, non white label) per tutti i tenant — login determina il tenant; è conforme alle policy store perché è un singolo prodotto B2B. [27-white-label-tecnico.md](27-white-label-tecnico.md) §5.

---

## E. Prospettiva Senior Laravel Architect

### E1. Scelta del package multi-tenancy non guidata
- **Tipo**: decisione mancante | **Gravità**: ALTA
- **Impatto**: scegliere male tra "database-per-tenant" (es. approccio multi-database) e "singolo DB + scope" condiziona tutto il progetto; col modello a 10k tenant su MySQL, multi-database è ingestibile (migrazioni × 10k).
- **Soluzione**: singolo database + colonna `tenant_id` + global scope automatico su tutti i modelli tenant-bound + binding del tenant nel container per request, [28-multi-tenant-tecnico.md](28-multi-tenant-tecnico.md) §2.

### E2. JWT in Laravel: scelta del meccanismo e gestione refresh/revoca da progettare
- **Tipo**: lacuna | **Gravità**: ALTA
- **Impatto**: JWT stateless puro rende impossibile la revoca immediata (logout forzato, sospensione tenant).
- **Soluzione**: access token JWT a vita breve (15 min) + refresh token opaco persistito e revocabile con rotazione, denylist Redis per revoca d'emergenza, [26-autenticazione-autorizzazione.md](26-autenticazione-autorizzazione.md) §3.

### E3. Code e scheduler: i promemoria "ogni 15 minuti" della Fase 1 sono fragili
- **Tipo**: errore di design | **Gravità**: MEDIA
- **Impatto**: query periodiche su finestre temporali (Flusso 6) possono saltare promemoria a cavallo delle finestre o duplicarli su più worker.
- **Soluzione**: job **schedulati individualmente** alla creazione dell'appuntamento (delayed job per ogni promemoria), cancellati/ricreati su modifica; marcatura idempotente. [29-notifiche-tecnico.md](29-notifiche-tecnico.md) §5.

### E4. Migrazioni a zero-downtime non menzionate
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: con un DB condiviso da tutti i tenant, una migrazione bloccante (ALTER su tabella appuntamenti da milioni di righe) significa downtime per tutti.
- **Soluzione**: politica di migrazioni espandi-poi-contrai (expand/contract), uso di online DDL MySQL, finestre di manutenzione comunicate, [32-qualita-aws.md](32-qualita-aws.md) §6.

### E5. Nessuna strategia per la fatturazione ricorrente
- **Tipo**: lacuna | **Gravità**: MEDIA
- **Impatto**: il billing dei tenant (250€/mese) è core per il business ma non ha un disegno tecnico.
- **Soluzione**: integrazione con gateway abbonamenti (es. Stripe Billing) tramite Laravel Cashier o equivalente, webhook di stato pagamento → macchina a stati tenant (Flusso 9), [24-database-er.md](24-database-er.md) (tabelle subscriptions/invoices) e [25-api-rest.md](25-api-rest.md).

---

## F. Sintesi delle decisioni che modificano la Fase 1

| # | Decisione Fase 1 | Revisione Fase 2 | Documento |
|---|---|---|---|
| 1 | "PWA prima, nativa poi" | Flutter nativo da subito; thin shell + config remota; web di cortesia solo come pagina prenotazione | [27](27-white-label-tecnico.md) |
| 2 | RLS database come opzione di isolamento | Non disponibile su MySQL: enforcement applicativo multi-livello + test isolamento | [28](28-multi-tenant-tecnico.md) |
| 3 | App nativa per tutti nel piano Base | Android incluso; iOS con account tenant assistito o add-on managed | [27](27-white-label-tecnico.md) |
| 4 | Promemoria con scheduler a polling | Job ritardati per-appuntamento, idempotenti | [29](29-notifiche-tecnico.md) |
| 5 | "UTC ovunque" per i fusi orari | UTC per istanti; ora locale + IANA tz per regole ricorrenti | [30](30-engine-appuntamenti.md) |
| 6 | Dashboard operatore non specificata | App gestionale unica multi-tenant (non white label) + dashboard web Laravel | [27](27-white-label-tecnico.md), [31](31-dashboard-design.md) |
| 7 | Conferma automatica per tutti | Macchina a stati con modalità auto-confirm / request-approve per tenant | [30](30-engine-appuntamenti.md) |

Le restanti conclusioni della Fase 1 (modello di business, mercato, personas, journey, piani di crescita) restano valide e sono assunte come input vincolante della progettazione tecnica.
