# SCALABILITY_REPORT — 10 / 100 / 1.000 / 10.000 / 100.000 clienti

> Estende `REAL_PROJECT_STATE.md` §Fase 9 (infrastruttura) e `PROJECT_DEPENDENCIES.md` §7 (repository/processo) fino a 100.000 clienti, esplicitamente richiesto in questa sessione. Dati reali, non stimati: 1 istanza EC2, 1 RDS `db.t4g.micro` (2 vCPU condivisi, 1GB RAM), storage locale, driver di build `local`/`manual` di default — verificato in `platform-infra/terraform/pilot/main.tf`.

## 10 clienti — ✅ Nessun problema

Il modello dati (row-level multi-tenancy) e l'infrastruttura attuale reggono comodamente. Nessun collo di bottiglia misurabile a questa scala con l'hardware oggi provisionato.

## 100 clienti — ✅ Ancora reggibile, primi segnali

- Il calcolo di disponibilità (`AvailabilityCalculator`) è puro/senza I/O pesante — nessun problema.
- Le build APK (processo `flutter build apk` reale, se il driver è `local`) iniziano a competere per CPU con il traffico web **se lanciate sulla stessa istanza** — a 100 clienti con build occasionali questo resta un fastidio, non un'interruzione.
- La coda `database` (polling sulla tabella `jobs`) regge senza problemi a questo volume di job.

**Nessuna azione richiesta a 100 clienti.**

## 1.000 clienti — 🟡 Collo di bottiglia strutturale, non solo di capacità

- **Singola istanza EC2, nessun autoscaling**: un solo processo PHP-FPM serve tutto il traffico di 1.000 tenant — nessun meccanismo di scalata orizzontale esiste nel Terraform.
- **RDS `t4g.micro` condiviso da tutti i tenant**: a 1.000 tenant con appuntamenti/notifiche attivi, il carico di scrittura su `appointments`, `appointment_events`, `notification_records` (quest'ultima già senza foreign key nel codice, con un commento esplicito che la segnala "candidata a partizionamento") diventa rilevante.
- **Storage locale come default**: gli artefatti APK/asset vivono sul disco dell'istanza — non scalano orizzontalmente senza un disco condiviso o S3 realmente popolato (oggi configurato ma con credenziali vuote).
- **Scheduler**: un solo comando schedulato (`notifications:dispatch-due`), nessuna pulizia automatica di dati storici — a 1.000 tenant le tabelle di log/notifiche crescono senza manutenzione.

**Azione minima per sostenere 1.000 clienti**: RDS di dimensione superiore, S3 realmente popolato, pulizia dati schedulata. Nessuna di queste è una riscrittura architetturale.

## 10.000 clienti — 🔴 L'architettura applicativa regge, l'infrastruttura definita nel repository no

Il pattern row-level multi-tenancy con scope Eloquent è, di per sé, compatibile con questa scala — è lo stesso pattern di SaaS multi-tenant di grande dimensione. **Ma l'infrastruttura concretamente definita in questo repository (1 EC2 + 1 RDS micro, niente Redis, niente autoscaling, niente CDN)** non la sosterrebbe senza lavoro infrastrutturale sostanziale:

- RDS più grande con almeno una read replica.
- Cache Redis (oggi configurata nel codice ma non la connessione di default).
- Coda su Redis/SQS invece di `database` (il polling su tabella diventa un collo di bottiglia di scrittura).
- Storage S3 realmente attivo con CDN per gli asset brand/APK.
- Separazione fisica della build machine dal server applicativo (le build reali competono per risorse con il traffico — a 10.000 clienti con build frequenti, inaccettabile sulla stessa istanza).

## 100.000 clienti — 🔴 Richiede una revisione infrastrutturale completa, non solo scaling verticale

A questa scala, oltre a tutto quanto sopra:

- **Partizionamento/sharding** delle tabelle ad alto volume (`appointments`, `appointment_events`, `notification_records`) diventa necessario, non opzionale — un singolo database, per quanto grande, ha un limite di throughput di scrittura che 100.000 tenant attivi con prenotazioni concorrenti raggiungerebbero.
- **Isolamento del Build Engine su infrastruttura dedicata**, con code di build vere (non il polling `database` attuale) e concorrenza reale — il codice ha già l'astrazione (`BuildDispatcher`), manca l'infrastruttura sottostante.
- **Multi-regione** se la base clienti è geograficamente distribuita (oggi tutto in una singola regione AWS, singola istanza).
- **Un vero contratto API versionato** (`v2`) diventa quasi inevitabile a questa scala — troppi consumer (100.000 app installate) per permettersi breaking change su `v1` senza un percorso di migrazione.

## Tabella riassuntiva

| Clienti | Modello dati | Infrastruttura attuale | Azione richiesta |
|---|---|---|---|
| 10 | ✅ | ✅ | Nessuna |
| 100 | ✅ | ✅ | Nessuna |
| 1.000 | ✅ | 🟡 | RDS più grande, S3 popolato, pulizia dati schedulata |
| 10.000 | ✅ | 🔴 | Redis (cache+coda), read replica, CDN, build machine separata |
| 100.000 | 🟡 (richiede partizionamento) | 🔴 | Tutto il punto precedente + sharding, multi-regione, API v2 |

## Collo di bottiglia principale, in ordine di impatto crescente con la scala

1. **Topologia a istanza singola senza autoscaling/failover** — il primo limite che si incontra, già a 1.000 clienti.
2. **Build APK reali eseguibili sulla stessa macchina che serve il traffico web** (driver `local`) — secondo limite, aggravato linearmente col numero di build.
3. **Storage locale non condiviso** — terzo limite, blocca la scalata orizzontale del server applicativo stesso.
4. **Assenza di partizionamento dati** — rilevante solo oltre le decine di migliaia di clienti, ma va pianificato prima di raggiungerle, non dopo.

Nessuno di questi 4 punti richiede di riscrivere il modello applicativo (tenancy, moduli, API) — sono tutti interventi infrastrutturali, coerenti con quanto già concluso in `PROJECT_MATURITY.md` (lo stadio "Enterprise" manca soprattutto di maturità operativa, non di design).
