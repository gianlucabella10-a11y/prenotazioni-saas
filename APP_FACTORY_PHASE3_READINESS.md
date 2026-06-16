# APP_FACTORY_PHASE3_READINESS

> Esito FASE 3 — **scala 100+ (la flotta)**. Costruisci e pubblichi N app per-tenant in un solo run, ricostruendo automaticamente quelle "stale" quando il core cambia (release train). Le parti **verificabili qui** (registry/matrice di build, release train, osservabilità in Control Room) sono fatte e **testate**; la **CI matrix** è scaffoldata e validata come YAML, ma il run reale richiede runner + segreti (come in FASE 2C).
>
> Verifiche: backend `php artisan test` **148** (+9 `BuildFleetTest`) · Pint pulito · migrazione additiva applicata · 2 workflow YAML validati (`app-factory-build`: dispatch+call · `app-factory-batch`: matrix+build).

## 1. Cosa è stato fatto (e verificato qui)
- **Registry / matrice di build** (`BuildFleet` + `php artisan app:build-matrix --platform=android [--stale-only] [--limit=N]`): stampa in JSON gli UUID dei tenant da costruire. Solo stati "costruibili" (`ready_to_build`/`published`/`failed`); esclude `draft`/`generated`/`building`. Cross-tenant via `CurrentTenant::bypass`. È l'input della CI matrix (`fromJSON`).
- **Release train del core** (additivo): `config/app_factory.core_version` (env `APP_FACTORY_CORE_VERSION`) + colonna `app_projects.built_core_version`. `app:build-record … published` fissa il core con cui l'app è stata costruita; quando il core sale, le app con `built_core_version` precedente (o nullo) diventano **stale** e rientrano nella matrice con `--stale-only`. `--limit` = **canary** (lancia prima un lotto piccolo).
- **Osservabilità in Control Room** (`/control-room/apps/flotta`, voce di menu «Flotta»): core corrente, conteggio costruibili / allineate / stale, distribuzione per stato build, ultime 15 build per-tenant. Solo super-admin (riusa guard `admin` + `EnsureSuperAdmin`).
- **CI matrix** (`.github/workflows/app-factory-batch.yml`): job `matrix` (SSH → `app:build-matrix` → JSON) → job `build` con `strategy.matrix` che **riusa** il workflow per-tenant `app-factory-build.yml` (ora `workflow_call`) un job per tenant, con `max-parallel` e `fail-fast: false`. Manuale, non parte da solo.
- **9 test** (`BuildFleetTest`): matrice solo-costruibili, filtro stale per core, limit/canary, conteggi del riepilogo, JSON pulito del comando, pin del core alla pubblicazione, dashboard visibile al super-admin / negata al guest.

## 2. Cosa richiede runner/infra reali (non eseguibile qui)
- **Esecuzione CI matrix**: come in FASE 2C servono i segreti (`APP_FACTORY_RELEASE_SECRETS.md`) e runner reali; i workflow sono validati come YAML, non come run. Primo collaudo: `app-factory-batch` con `limit=1` (canary) su track `internal`.
- **Pool runner macOS per iOS**: a flotta intera i macOS sono la risorsa scarsa → `max-parallel` basso e/o runner dedicati.
- **Treno di rilascio del core**: il *meccanismo* (versione + stale + matrice + canary) è pronto; la **policy di rollout** (cadenza, % canary, finestra di osservazione, rollback) è operativa e va decisa con i primi clienti.

## 3. Come si usa (release train)
1. Aggiorni la master app → bump `APP_FACTORY_CORE_VERSION` (es. `1.0.0` → `1.1.0`).
2. Control Room → **Flotta**: vedi quante app sono *stale*.
3. Actions → **app-factory-batch** (`platform`, `stale_only=true`, `limit=1` per il canary) → costruisce il canary; verifichi.
4. Rilanci senza `limit` → ricostruisce e pubblica il resto; `app:build-record` riallinea `built_core_version` e la dashboard torna verde.

## 4. Rischi e mitigazioni
- **Selezione platform-agnostica**: la matrice elenca i tenant a prescindere dalla piattaforma; il workflow per-tenant costruisce `android`/`ios`/`both`. Per `both` il comando matrice usa `android` solo per la selezione (lista identica).
- **Matrice vuota**: il job `build` ha `if: count != '0'` → nessun job spurio.
- **Pressione sui runner**: `max-parallel: 3` + `fail-fast: false` (un fallimento non blocca gli altri tenant).
- **Isolamento**: tutte le query della flotta sono cross-tenant **solo** in `bypass` (contesto super-admin/CLI); nessun endpoint cliente le espone.
- **Additività**: nuova colonna nullable + nuovo config + nuovi comandi/route; **nessuna modifica** a booking/auth/schema core. Ogni cliente resta una configurazione.

## 5. Definition of Done — verifica
backend `php artisan test` **148/148** · Pint pulito · migrazione applicata · `app:build-matrix` JSON valido · dashboard Flotta resa (test) · 2 workflow YAML validati. Riferimenti: `APP_FACTORY_PHASE2C_READINESS.md`, `APP_FACTORY_RELEASE_SECRETS.md`, `APP_FACTORY_MASTER_PLAN.md` (§12, FASE 3).

## 6. Cosa resta (oltre la 3, opzionale)
Non bloccante per la scala build: **`app_templates` su DB con editor** in Control Room (oggi sono in `config/app_templates.php`, già sufficienti) · **Asset Factory come microservizio** (oggi GD in-process, ok fino a volumi alti) · osservabilità build avanzata (metriche/log centralizzati). Sono ottimizzazioni, non prerequisiti.
