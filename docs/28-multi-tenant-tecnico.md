# 28 — Sistema Multi-Tenant (progettazione tecnica Laravel)

## 1. Modello: singolo database, scoping applicativo multi-livello

Conferma della scelta Fase 1 (schema condiviso + `tenant_id`), adattata allo stack: **MySQL non offre Row-Level Security nativa**, quindi l'enforcement è applicativo, con difese ridondanti.

## 2. I cinque livelli di difesa dell'isolamento

| # | Livello | Meccanismo |
|---|---|---|
| 1 | Risoluzione tenant | Il tenant deriva esclusivamente dal JWT (`tid`) o da `X-Tenant-Key` validata; mai da input utente. Middleware dedicato costruisce un **TenantContext immutabile** e lo binda nel container per la durata della request |
| 2 | Global scope automatico | Trait `BelongsToTenant` su ogni Eloquent model tenant-bound: aggiunge `WHERE tenant_id = ?` a ogni query e valorizza `tenant_id` a ogni insert. I model senza trait né marcatura esplicita `platform-level` falliscono un test di architettura in CI |
| 3 | Guard runtime | In ambienti non-prod: eccezione se una query su tabella tenant-bound parte senza TenantContext; in prod: log + metrica di allarme |
| 4 | Autorizzazione | Policy per-risorsa: anche dentro il tenant, lo staff vede solo ciò che i permessi consentono ([26](26-autenticazione-autorizzazione.md)) |
| 5 | Test di isolamento (RNF-21) | Suite `tests/TenantIsolation`: per ogni endpoint, fixture con due tenant e asserzione che il tenant A non legga/scriva/enumeri risorse del tenant B (inclusi uuid noti: deve rispondere 404, non 403) |

Regole complementari:
- **Job e comandi**: ogni job serializza il `tenant_id` e ricostruisce il TenantContext all'esecuzione; i comandi cross-tenant (es. retention) iterano esplicitamente per tenant
- **Cache Redis**: chiavi sempre prefissate `t:{tenant_id}:…` tramite repository di cache centralizzato (mai chiavi costruite a mano)
- **S3**: prefissi per tenant (`tenants/{uuid}/…`); URL firmati a scadenza, mai bucket pubblici
- **Query raw**: vietate fuori da repository dedicati; le eccezioni passano revisione obbligatoria

## 3. Tenant registry

- Tabella `tenants` + cache Redis (`t:{id}:meta`, TTL 5 min, invalidazione su evento `TenantUpdated`)
- Letture in hot path (middleware): cache-first con fallback DB; l'indisponibilità di Redis degrada a letture DB (più lente ma corrette), non a errore — risponde alla criticità B1 di [20-analisi-critica-fase1.md](20-analisi-critica-fase1.md)
- Lo **stato tenant** (active/suspended/…) è verificato a ogni richiesta dal middleware: la sospensione è effettiva entro il TTL della cache (≤ 5 min) o immediatamente con invalidazione esplicita

## 4. Feature flag e quote

- Risoluzione a runtime: flag = default del piano (`plans.features`) sovrascritti da `tenant_features`; materializzati nel TenantContext (e nel WhiteLabelConfig per il client)
- Enforcement backend con middleware `RequiresFeature:{code}` sugli endpoint gated + verifica nelle policy dove serve granularità
- Quote (`plans.quotas`): contatori Redis con riconciliazione periodica da DB; superamento ⇒ 422 con codice errore dedicato e CTA commerciale (upsell), mai blocco silenzioso

## 5. Migrazioni e dati di default

- Migrazioni standard Laravel: **una sola esecuzione** per tutti i tenant (vantaggio chiave del singolo DB)
- Politica zero-downtime espand/contract ([32-qualita-aws.md](32-qualita-aws.md) §6)
- Provisioning nuovo tenant = inserimenti (tenants, brand_profiles placeholder, location di default, template servizi per settore, orari standard): transazione applicativa, nessuna DDL ⇒ provisioning in secondi

## 6. Percorso di estrazione per tenant enterprise (opzione ibrida)

Predisposizione senza implementazione anticipata:
- Tutte le FK passano da `tenant_id` espliciti (nessuna assunzione di co-residenza globale nel codice di dominio)
- Connessione DB risolta dal TenantContext (default: connessione condivisa); un tenant enterprise può essere puntato a un database dedicato cambiando la risoluzione, con migrazione dati una tantum
- Trigger contrattuale, non tecnico: richieste di isolamento fisico/data residency

## 7. Noisy neighbor

- Rate limiting per-tenant a livello API ([25-api-rest.md](25-api-rest.md) §6)
- Code Horizon con fairness: i job `bulk` (campagne) di un singolo tenant non saturano i worker (limite di job concorrenti per tenant sulla coda bulk)
- Metriche per-tenant (richieste, job, righe) per individuare anomalie ([32-qualita-aws.md](32-qualita-aws.md) §7)
