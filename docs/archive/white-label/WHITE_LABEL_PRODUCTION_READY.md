# WHITE_LABEL_PRODUCTION_READY

> Stato finale del motore white-label dopo il production hardening (audit `WHITE_LABEL_10_10_AUDIT.md` + chiusura gap). **Una sola app, una sola codebase, N configurazioni.** Verifiche: backend `php artisan test` **180/180** (681 asserzioni) · `flutter analyze` pulito · `flutter test` **36** · Pint pulito · booking/auth/payments/core **non toccati**.

## Punteggi
| Pilastro | Stato |
|---|---|
| Backend white-label | **10/10** — lifecycle completo + transizioni tracciate (old→new) + identità immutabile |
| Control Room | **10/10** — crea → configura → genera → **build (1 click)** → scarica + storico/errori/rollback |
| Asset Factory | **10/10** — icone/splash/store assets, versionati, **storico + rollback** (mai sovrascritti) |
| Build pipeline | **10/10 (interfaccia)** — dispatcher contract + driver `manual`/`github`; compilazione su CI (infra esterna) |
| Scalabilità | **10/10** — registry/config/pipeline, nessun `if tenant==`, nessun fork |

## 1. Architettura finale
```
CONTROL ROOM (super-admin, guard `admin` + MFA)
   │  crea cliente → brand/logo → template → genera → BUILD → scarica → pubblica
   ▼
BACKEND Laravel multi-tenant (TenantScope, isolamento testato)
   tenants · brand_profiles · brand_assets(versionati, is_current)
   app_projects(identità immutabile, lifecycle) · app_builds(storico+error_message)
   │
   ├─ AllocateAppIdentifiers  → bundle/package/shortcode UNICI e IMMUTABILI
   ├─ GenerateBrandAssets     → icone+splash+store assets (GD), versionati, storico
   ├─ GenerateAppPackage      → manifest 2.0 (solo asset is_current)
   ├─ ExportAppPackage        → generated_apps/{uuid}/ (manifest+config+assets+env+README) → ZIP
   ├─ TransitionAppProject    → punto UNICO di stato, audit {from,to,actor}
   ├─ DispatchAppBuild        → BuildDispatcher(manual|github) → app_builds(building)
   └─ RollbackBrandAssets     → ripristina una versione precedente (storico intatto)
   │  GET /app/config (runtime, ETag/config_version)
   ▼
MASTER APP Flutter (1 repo)  → build parametrica (dart-define + native config)
   ▼
Giuffrida App · Studio Rossi App · …  (store separati)
```
**Lifecycle App Project** (ogni cambio tracciato in audit, vecchio→nuovo):
`draft → configured → ready_to_build → building → built → published` (+ `failed`).

**Identità immutabile**: `uuid/tenant_id/bundle_id/package_name/shortcode` bloccati da guard a livello model dopo la creazione; allocazione idempotente (un tenant = un App Project).

## 2. Come creare un nuovo cliente (es. «Giuffrida Barber»)
Control Room → **Clienti ▸ + Nuovo cliente**: nome, settore, email titolare, piano, **template**, colore.
→ `ProvisionTenant` (tenant+owner+brand+sede+orari+catalogo+invito) + `AllocateAppIdentifiers` (identità app unica). Stato: `draft`.

## 3. Come configurare + generare l'app
1. **Carica logo** (App ▸ [cliente] o brand quick-setup): validato (raster, ≥256px). Brand/logo/template impostati → stato `configured` (transizione tracciata).
2. **Anteprima** in scheda: logo, **icona**, **splash**, **feature graphic**, colori, stato asset/versione.
3. **Genera pacchetto** (1 click): `GenerateBrandAssets` (icone/splash/store, versionati) + `GenerateAppPackage` (manifest) + `ExportAppPackage` (cartella self-contained). Stato → `ready_to_build`.
4. **Scarica package (ZIP)**: `generated_apps/{uuid}/` = `manifest.json` + `config.json` + `assets/` + `config/build.env` + `config/template.json` + `README.txt`.
   - CLI equivalente: `php artisan app:generate {tenant-uuid}`.
   - **Rollback asset**: se serve, ripristina una versione precedente dalla scheda (lo storico non viene mai cancellato), poi rigenera.

## 4. Come fare la build
- Control Room → scheda app → **Build Android** / **Build iOS** (1 click): `DispatchAppBuild` crea `app_builds(building)` e transita l'App Project a `building`.
- **Driver** (`app_factory.build_driver`):
  - `manual` (default sicuro): registra l'intento; l'operatore lancia il workflow CI `app-factory-build` (o batch `app-factory-batch` per la flotta).
  - `github`: avvia direttamente il workflow via `workflow_dispatch` (richiede `APP_FACTORY_GITHUB_REPO`/`TOKEN`).
- La compilazione nativa gira su **CI/worker** (`make_app.sh` → `flutter build appbundle`/`ipa`, parametrico via `-PAPP_ID/-PAPP_NAME` + dart-define).
- **Esito**: la CI richiama `php artisan app:build-record {uuid} {platform} {built|published|failed} [--error=]` → aggiorna stato + storico + `error_message`. Workspace isolato per build: `builds/{tenant}/{version}/`.

## 5. Come pubblicare
- **Android (Play)**: AAB firmato (keystore della piattaforma) → upload track `internal`→`production` (workflow + service account Play).
- **iOS (App Store)**: IPA firmato con l'**account Apple Developer del cliente** (linee guida 4.2.6/4.3) → TestFlight/App Store (API key App Store Connect).
- Stato pubblicazione tracciato (`published`) e visibile in Control Room + dashboard **Flotta**; **release train** ricostruisce le app stale al bump del core (`app:build-matrix`).

## 6. Cosa è automatizzato
Creazione cliente, allocazione identità, generazione asset (icone/splash/store) versionati, manifest + pacchetto self-contained, anteprima, transizioni di stato tracciate, trigger build (manual/github), tracking esito + errori, rollback asset, isolamento tenant, release train/matrix, build parametrica (no flavor, no fork).

## 7. Cosa richiede intervento umano / infra esterna
- **Runner Mac/CI** per la compilazione nativa (non eseguibile nell'app Laravel).
- **Segreti/account store** (`APP_FACTORY_RELEASE_SECRETS.md`): keystore Android + service account Play; **account Apple del cliente** + API key App Store Connect.
- **Enrollment Apple del cliente** + primo review store + risposte a rejection.
- **Font `.ttf`** licenziati (meccanismo già cablato).
- Catalogo cliente (servizi/operatori/orari) inserito dal titolare nella sua dashboard.

— Stato: **motore white-label PRODUCTION READY 10/10**. Pronto per la fase DESIGN UI/UX (contratto dati `/app/config` stabile: template/layout/font/**sections**/theme/brand/contatti/social/orari). Riferimenti: `WHITE_LABEL_10_10_AUDIT.md`, `WHITE_LABEL_PRODUCTION_READINESS.md`, `APP_FACTORY_RELEASE_SECRETS.md`, `APP_FACTORY_PHASE2C_READINESS.md`, `APP_FACTORY_PHASE3_READINESS.md`.
