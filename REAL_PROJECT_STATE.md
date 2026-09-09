# REAL_PROJECT_STATE — Inventario tecnico verificato da codice

> Ogni affermazione in questo documento è stata verificata leggendo direttamente il codice sorgente del repository (non i file `.md` esistenti nella root, che sono report di sessioni precedenti e non sono stati usati come fonte). Dove qualcosa non esiste, è scritto esplicitamente **NON IMPLEMENTATO** / **NOT FOUND**. Nessuna modifica è stata fatta al codice.

Repo: `/Users/gianlucabella/Desktop/app prenotazioni progetto`
Branch: `docs/production-readiness-audit`
Stato di lavoro: 2 file con modifiche non committate (`LocalBuildDispatcher.php`, `BuildPipelineTest.php` — un fix di un bug sull'UUID usato per risolvere il manifest, discusso in Fase 7).

---

## FASE 1 — Panoramica del progetto

**Tipo di software**: piattaforma SaaS white-label multi-tenant per la gestione di prenotazioni/appuntamenti (booking), con un motore interno ("App Factory") che genera per ogni cliente (tenant) un'app mobile Android brandizzata.

**Repository — 3 componenti principali**:
```
platform-backend/    Laravel 13 (PHP 8.3) — API + dashboard + Control Room + App Factory
platform-mobile/apps/client_app/   Flutter — unica app cliente, condivisa da tutti i tenant
platform-infra/      Terraform + script bash — infrastruttura AWS di un ambiente "pilota"
```

**Stack backend** (verificato in `platform-backend/composer.json`):
- Laravel Framework `^13.8`, PHP `^8.3`
- Autenticazione: **non Sanctum, non Passport** — guard JWT custom (`firebase/php-jwt ^7.1`)
- Crash reporting: `sentry/sentry-laravel ^4.26`
- Frontend build: Vite 8 + `laravel-vite-plugin` + Tailwind CSS 4 (`package.json`, 5 dipendenze dev)
- `node_modules/` e `public/build/` — **NOT FOUND**: il frontend non è mai stato compilato in questo checkout

**Database**: SQLite in locale (`DB_CONNECTION=sqlite`), MySQL previsto in produzione (Terraform istanzia RDS MySQL 8). Nessun database separato per tenant (dettagli in Fase 4/8).

**Storage**: dischi Laravel standard `local` (privato) e `public` (via symlink `storage:link`), più `s3` configurato ma con credenziali AWS vuote in `.env`/`.env.example` — non utilizzato oggi.

**Queue**: driver `database` (tabella `jobs`), non Redis/SQS in uso di default.

**Build system (App Factory)**: modulo dedicato che genera manifest JSON, asset brandizzati e — con il driver `local` — esegue realmente `flutter build apk`.

**CI**: 3 workflow GitHub Actions (`ci.yml`, `app-factory-build.yml`, `app-factory-batch.yml`) — dettagli in Fase 4/CI più sotto.

**Frontend/Flutter**: una singola app Flutter (`platform-mobile/apps/client_app`), non un progetto per tenant — la personalizzazione avviene tramite manifest/config a runtime, non tramite fork del codice (dettagli Fase 5).

---

## FASE 2 — Architettura

Flusso verificato nel codice (non teorico):

```
Control Room (routes/web.php, prefix /control-room, guard "admin")
   │  TenantsController::store()
   ▼
ProvisionTenant::execute()  — 1 transazione DB, sotto CurrentTenant::bypass()
   │  crea: tenants, subscriptions, brand_profiles, locations, location_schedules,
   │        service_categories/services/service_variants, users (tenant_admin), invito
   ▼
Database (SQLite/MySQL condiviso, riga-per-riga isolato da tenant_id + TenantScope)
   │
   ▼
AllocateAppIdentifiers::execute()  — crea app_projects (bundle_id = com.platform.t{shortcode})
   │
   ▼
Asset pipeline (Branding module)
   │  StoreBrandLogo → GenerateBrandAssets (GD, 9 icone + 3 splash + 2 store asset)
   ▼
GenerateAppPackage::execute()  — scrive manifest-latest.json su disco (non nel DB soltanto)
   │  { app_identity, runtime.dart_define, branding, template, metadata.version }
   ▼
App Factory — BuildService::request() → coda → RunAppBuildJob → BuildDispatcher
   │  driver "local": LocalBuildDispatcher esegue REALMENTE
   │  flutter clean → pub get → analyze → test → build apk --release
   │                                        -PAPP_ID=... -PAPP_NAME=...
   ▼
Flutter (platform-mobile/apps/client_app — CODICE UNICO, condiviso da tutti i tenant)
   │  legge -PAPP_ID/-PAPP_NAME come Gradle property, --dart-define per branding/API URL
   ▼
APK (build/app/outputs/flutter-apk/app-release.apk)
   │  copiato su storage/app/private/builds/{tenant_id}/{version}/app-release.apk
   ▼
Beta download token (revocabile, 7gg, max download) → BetaDownloadController
```

Punto architetturale chiave, verificato nel codice: **il Flutter non viene mai clonato, forkato o modificato per tenant**. È un unico progetto sorgente che riceve, per ogni build, un `applicationId` diverso via Gradle property e valori di branding via `--dart-define` (iniettati a build-time nel binario, non letti a runtime da un server — vedi Fase 5). Il manifest generato dal backend serve a **generare i dart-define della build**, non a essere scaricato/letto dall'app in esecuzione (non esiste un endpoint "config manifest" consumato a runtime con questo scopo — l'unico endpoint runtime affine è `GET /api/v1/app/config`, che restituisce branding/tema alla singola app installata, tramite `X-Tenant-Key`).

---

## FASE 3 — Funzionalità implementate (per modulo)

| Modulo | Verdetto | Nota |
|---|---|---|
| White Label Engine | **ESISTE** | vedi Fase 5 |
| Multi Tenant (isolamento dati) | **ESISTE** | riga-per-riga, DB condiviso — vedi Fase 8 |
| Control Room | **ESISTE** | vedi Fase 6 |
| App Factory (orchestrazione) | **ESISTE** | vedi Fase 7 |
| Asset Pipeline | **ESISTE** | GD puro, 14 varianti generate per logo caricato |
| Manifest | **ESISTE** | JSON versionato su disco, schema `2.0` |
| Build Engine | **ESISTE** (driver `local`) | esegue realmente `flutter build apk`, ma solo testato fino al controllo "cartella mancante" — nessun test esercita l'intera pipeline reale |
| Build Queue | **ESISTE** | `RunAppBuildJob implements ShouldQueue`, driver queue `database` |
| Android Build | **ESISTE** | via Gradle property `-PAPP_ID`, non modifica del sorgente |
| Artifact Management | **ESISTE** | path `builds/{tenant_id}/{version}/app-release.apk`, checksum sha256 |
| APK Generation | **ESISTE** ma **PARZIALE** | versionCode/versionName **non** parametrizzati (fissi da `pubspec.yaml`) |
| Signing | **PARZIALE** | keystore reale supportato via env Gradle, ma `LocalBuildDispatcher` non imposta mai quelle env → build `local` è firmata in **debug**, non release |
| Beta Download | **ESISTE** | token revocabile, scadenza 7gg fissa, limite download |
| Feedback | **ESISTE** (raccolta) / **PARZIALE** (gestione) | l'app invia feedback via API; Control Room lo mostra in sola lettura, non lo gestisce |
| Analytics | **PARZIALE** | interfaccia + eventi definiti lato Flutter, ma sink di default è `NoopAnalyticsSink` — nessun provider esterno collegato |
| Crash (Sentry) | **ESISTE** | wiring reale backend+Flutter, DSN vuoto in questo ambiente (non è un gap di codice) |
| Notifications (push) | **ESISTE** | canale FCM v1 reale con fallback email, non uno stub |
| Authentication | **ESISTE** | guard JWT custom (non Sanctum), 3 guard (`web`, `admin`, `api`), MFA TOTP, verifica email |
| Booking | **ESISTE** | slot calc, lock di concorrenza (`lockForUpdate` + unique index), cancellazione, no-show, idempotenza |
| Payments | **NON IMPLEMENTATO** | zero occorrenze di Stripe/payment/checkout in tutto il repo |
| Push | **ESISTE** | vedi Notifications |
| Versioning | **ESISTE** ma **scollegato dall'APK reale** | tabella `app_versions` esiste ma nessun codice la alimenta automaticamente da una build reale; l'APK stesso non riceve `--build-name`/`--build-number` |
| Tenant Isolation | **ESISTE** | scope globale Eloquent + convenzioni — vedi Fase 8 per i limiti |
| Storage | **PARZIALE** | solo disco locale in uso; S3 configurato ma non popolato |
| Media | **ESISTE** | pipeline immagini via GD, checksum, versionamento con rollback |
| Environment | **ESISTE** | `.env`/`.env.example`/`.env.production.example` presenti e coerenti |
| API | **ESISTE** | REST sotto `/api/v1`, nessuna GraphQL |
| Scheduler | **PARZIALE** | un solo task registrato (`notifications:dispatch-due` ogni 15 min) |
| Logging | **ESISTE** | canali Laravel standard + Sentry sull'exception handler |
| Monitoring | **PARZIALE** | nessun APM oltre Sentry (niente New Relic/Datadog/health dashboard) |

---

## FASE 4 — Database

**Un unico database condiviso, schema condiviso.** Nessun database-per-tenant, nessuno schema-per-tenant. Verificato: `config/database.php` non contiene logica di connessione dinamica per tenant; `grep -rn "DB::connection("` su tutto `app/` → **zero risultati**.

**31 tabelle** create da **23 file di migration** (elenco completo, in ordine):

| Migration | Tabelle create/alterate |
|---|---|
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_06_12_000010_create_tenancy_tables` | `tenants`, `tenant_domains`, `plans`, `subscriptions`, `tenant_features` |
| `2026_06_12_000020_create_identity_tables` | `users`, `password_reset_tokens`, `sessions`, `mfa_credentials`, `refresh_tokens`, `devices` |
| `2026_06_12_000030_create_branding_tables` | `brand_profiles`, `brand_assets` |
| `2026_06_12_000040_create_catalog_tables` | `locations`, `service_categories`, `services`, `service_variants`, `staff_members`, `staff_services`, `location_services` |
| `2026_06_12_000050_create_scheduling_tables` | `location_schedules`, `staff_schedules`, `schedule_exceptions`, `customers`, `customer_notes`, `consents`, `appointments`, `appointment_items`, `appointment_events`, `waitlist_entries` |
| `2026_06_12_000060_create_messaging_tables` | `notification_records`, `campaigns`, `audit_logs` |
| `2026_06_12_000070_add_beta_readiness_columns` | altera `users`, crea `email_verifications`, altera `consents`, `brand_profiles` |
| `2026_06_15_000000…brand_profiles` | altera `brand_profiles` (contatti social/whatsapp) |
| `2026_06_15_000010…devices` | altera `devices` (tenant_id, app_version) |
| `2026_06_16_000000_create_app_factory_tables` | `app_projects`, `app_builds` |
| `2026_06_16_000010…brand_assets` | altera `brand_assets` (variant/version) |
| `2026_06_16_000020…app_projects` | altera `app_projects` (built_core_version) |
| `2026_06_17_000000…brand_assets` | altera `brand_assets` (is_current) |
| `2026_06_17_000010…app_builds` | altera `app_builds` (error_message) |
| `2026_06_17_000020…app_builds` | altera `app_builds` (timeline) |
| `2026_06_17_000030…app_builds` | altera `app_builds` (checksum) |
| `2026_06_17_000040_create_beta_feedback_table` | `beta_feedback` |
| `2026_06_17_000050…app_builds` | altera `app_builds` (command/log/exit_code/duration_ms) |
| `2026_06_17_000060_create_beta_testers_table` | `beta_testers` |
| `2026_06_17_000070_create_beta_download_tokens_table` | `beta_download_tokens` |
| `2026_06_17_000080_create_app_versions_table` | `app_versions` |
| `2026_06_17_000090…app_builds` | altera `app_builds` (size_bytes) |

**Modelli Eloquent con `tenant_id` e scope automatico** (trait `BelongsToTenant`, 21 modelli): `BrandProfile`, `Location`, `Service`, `ServiceCategory`, `ServiceVariant`, `Customer`, `CustomerNote`, `Consent`, `Appointment`, `AppointmentItem`, `AppointmentEvent`, `LocationSchedule`, `StaffSchedule`, `ScheduleException`, `StaffMember`, `NotificationRecord`, `AppProject`, `AppBuild`, `AppVersion`, `BetaFeedback`, `BetaTester`, `BetaDownloadToken`.

**Modelli con `tenant_id` ma SENZA scope automatico** (isolamento a convenzione, non a scope): `User` (auth avviene prima che esista un contesto tenant), `Subscription`, `TenantDomain`, `TenantFeature` (platform-level, gestiti solo sotto bypass esplicito).

**Modelli platform-level** (nessun `tenant_id`, condivisi tra tutti i tenant): `Tenant`, `Plan`, `BrandAsset` (isolato transitivamente via `brand_profile_id`).

**Dati condivisi tra tutti i tenant**: `plans` (catalogo commerciale), la definizione stessa di `tenants`, e — a livello di infrastruttura — l'intero database fisico/istanza MySQL.

**Dati multi-tenant** (isolati per riga): tutto il resto — clienti, appuntamenti, servizi, staff, brand, build APK, tester, feedback, token beta.

Dettagli sul meccanismo di isolamento in Fase 8.

---

## FASE 5 — White Label: cosa succede quando creo un nuovo cliente

Percorso reale (`TenantsController::store()` → `ProvisionTenant::execute()`, tutto in **una transazione DB** sotto `CurrentTenant::bypass()`):

1. Crea riga `tenants` (stato iniziale `onboarding`)
2. Crea riga `subscriptions` (piano scelto, stato `active`, `current_period_end` = +1 mese)
3. Crea riga `brand_profiles` di default
4. Crea `locations` + 7 righe `location_schedules` (orari settimanali di default)
5. Crea catalogo di partenza (`service_categories`/`services`/`service_variants` + pivot `location_services`) da un preset per settore (barber/salone/dentale/ecc.)
6. Crea il primo `users` di tipo `tenant_admin` (MFA **forzata** a `true`, email pre-verificata)
7. Genera un invito (token random 64 caratteri, solo l'hash SHA-256 salvato in `password_reset_tokens`)
8. Scrive un record `audit_logs` (`tenant.provisioned`)

Subito dopo, fuori dalla transazione: `AllocateAppIdentifiers::execute()` crea la riga `app_projects` con `bundle_id = package_name = com.platform.t{base36(tenant_id)}` (es. tenant 123 → `com.platform.t3f`), **unico e immutabile** (il modello blocca ogni tentativo di update su questi campi dopo la creazione).

**Dove vengono salvati gli asset**: logo caricato → `brand/{tenant_id}/logo.{ext}` sul disco `branding.asset_disk` (default `public`); derivati (icone/splash/store assets, generati con PHP GD, non ImageMagick) → `brand/{brand_profile_id}/generated/v{n}/{kind}-{variant}.png`, versionati con rollback (i vecchi non vengono cancellati).

**Come nasce una nuova app**: non viene generato codice Flutter nuovo. `GenerateAppPackage` scrive un manifest JSON (`app_factory/{tenant_uuid}/manifest-latest.json`) con identità app, URL API, `tenant_key` (api-key del tenant), colori, riferimenti asset. Questo manifest **non è letto a runtime dall'app** — serve solo a costruire i flag `-PAPP_ID`/`-PAPP_NAME`/`--dart-define` passati al comando `flutter build apk` quando si lancia una build. **Il codice Flutter (`platform-mobile/apps/client_app`) è unico e condiviso da tutti i tenant** — non viene clonato, forkato né modificato per cliente. La personalizzazione visiva a runtime (colori/nome/logo) avviene invece tramite l'endpoint `GET /api/v1/app/config`, che l'app chiama con l'header `X-Tenant-Key` compilato al suo interno.

---

## FASE 6 — Control Room: cosa posso fare oggi realmente

Modulo `app/Modules/ControlRoom/` (solo 5 file: 4 controller + 1 middleware) più le route che puntano a `AppProjectController` del modulo AppFactory. Tutto sotto guard di sessione `admin`, dietro middleware `EnsureSuperAdmin` (ricontrolla `type === super_admin` a ogni richiesta, anche se `auth:admin` è già passato).

Puoi realmente:
- **Creare un tenant** (form completo: nome, settore, email titolare, telefono, indirizzo, colore, piano, template, flag dati sanitari)
- **Vedere lista clienti** con ricerca per nome e filtro stato
- **Vedere scheda cliente** (dati, stato abbonamento, stato invito, chiave API in chiaro)
- **Attivare / sospendere / riattivare** un tenant (non "terminare"/archiviare: l'enum lo prevede ma **nessuna route/UI del Control Room lo espone**)
- **Rigenerare / revocare l'invito** del titolare
- **Caricare/aggiornare logo e colori** (Quick Setup brand)
- **Generare il pacchetto app** (manifest + asset) per un progetto
- **Lanciare una build** (Android o iOS, a seconda del driver configurato)
- **Scaricare l'APK** di una build completata, o l'intero pacchetto export zippato
- **Creare/revocare link beta** (token scaricabile, scadenza 7gg, limite download)
- **Invitare/gestire tester beta** (stato invited/active/blocked)
- **Vedere il feedback beta** in sola lettura (ultimi 10, non gestibile/moderabile da qui)
- **Creare/aggiornare versioni app** (`app_versions`, manuale — nessuna build reale la popola automaticamente)
- **Fare rollback degli asset brand** a una versione precedente
- **Vedere la "flotta"** (aggregato build/versioni su tutti i tenant)

Non puoi (perché non esiste nel codice):
- Vedere log/audit trail nel Control Room stesso (l'audit esiste nel DB ma **non c'è una vista che lo mostri**)
- Gestire pagamenti/fatturazione dei tenant (nessuna feature payment esiste)
- Impostare policy granulari per-tenant al di là di feature flag/quote già previste
- Archiviare/terminare un tenant dall'interfaccia

---

## FASE 7 — Build System: cosa succede quando clicco BUILD

Route: `POST /control-room/apps/{uuid}/build` → `AppProjectController::dispatchBuild()` → `BuildService::request()`.

**Classi coinvolte, in ordine**:
1. `BuildService::request()` — valida `platform ∈ {android, ios}`, richiede che `AppProject.build_manifest` non sia null (il manifest va generato prima), crea riga `app_builds` (stato `queued`, `version = "1.0.0+{N}"` dove N = conteggio build esistenti + 1), transiziona `AppProject` a `queued`, mette in coda `RunAppBuildJob`
2. `RunAppBuildJob::handle()` (job in coda, `ShouldQueue`) — risolve il `BuildDispatcher` in base a `config('app_factory.build_driver')` (`manual`→`LogBuildDispatcher`, `github`→`GithubBuildDispatcher`, `local`→`LocalBuildDispatcher`), lo invoca, persiste il risultato
3. Con driver **`local`** (l'unico che compila realmente): `LocalBuildDispatcher::dispatch()` legge il manifest dal disco (risolvendo l'UUID del **tenant**, non del progetto — questo è esattamente il bug appena corretto nel working tree), verifica che `platform_mobile/apps/client_app` esista, esegue in sequenza **processi reali** (Symfony `Process`, non mock): `flutter clean` → `flutter pub get` → `flutter analyze` → `flutter test` → `flutter build apk --release -PAPP_ID=... -PAPP_NAME=... --dart-define=...`
4. Se un passo fallisce, si ferma lì e lancia `BuildFailedException` con log/exit-code/durata
5. Verifica che `build/app/outputs/flutter-apk/app-release.apk` esista davvero
6. Calcola `sha256` del file, lo copia su `storage/app/private/builds/{tenant_id}/{version}/app-release.apk`
7. `RecordAppBuild`/`RunAppBuildJob` aggiorna `app_builds` (status `built`, checksum, log, durata, size) e `AppProject.build_status`

**Manifest**: generato da `GenerateAppPackage`, non dal comando di build — deve esistere già prima di poter lanciare una build (`BuildService` lo richiede).

**Package ID**: allocato una sola volta da `AllocateAppIdentifiers` alla creazione del tenant (`com.platform.t{base36(tenant_id)}`), immutabile, iniettato a ogni build come Gradle property `-PAPP_ID`.

**Version**: **due sistemi scollegati**. (a) `app_builds.version` è solo un contatore (`1.0.0+N`); (b) `app_versions` è una tabella popolata **manualmente** dall'operatore in Control Room, non da una build reale. **Nessuno dei due arriva nell'APK**: `flutter build apk` non riceve mai `--build-name`/`--build-number`, quindi ogni APK ha lo stesso `versionCode=1`/`versionName=1.0.0` statico da `pubspec.yaml`, indipendentemente dal tenant o dal numero di build.

**Build log**: salvato per intero (fino a 8000 caratteri sull'errore) in `app_builds.build_log`, visibile in Control Room.

**Checksum**: sha256 calcolato solo per il driver `local`; i driver `github`/`manual` non lo popolano mai (nessun percorso di codice lo imposta).

**Signing**: `build.gradle.kts` supporta keystore reale via variabili d'ambiente (`ANDROID_KEYSTORE_PATH/PASSWORD`, `ANDROID_KEY_ALIAS/PASSWORD`) con fallback al keystore debug di Android se assenti. `LocalBuildDispatcher` **non imposta mai queste variabili** nel processo che lancia — quindi, così com'è oggi, ogni build prodotta dal driver `local` è firmata in **debug**, non in release, a meno che quelle env non siano già presenti nell'ambiente della shell di Laravel stesso (nessun codice le imposta).

---

## FASE 8 — Database delle app (isolamento multi-tenant) — la domanda più importante

**Risposta diretta**: le app **condividono lo stesso database fisico** (un'unica istanza MySQL/SQLite). **Non esiste un database per tenant.** L'isolamento è ottenuto interamente **a livello di riga** (row-level), tramite una colonna `tenant_id` + uno scope Eloquent globale — non tramite database, schema o connessioni separate.

Verifica letterale nel codice:

- `grep -rn "DB::connection(" app/` → **zero risultati**
- `grep -rn "config(\['database" app/` → **zero risultati**
- `config/database.php` definisce connessioni statiche standard Laravel (sqlite/mysql/mariadb/pgsql/sqlsrv), nessuna logica per-tenant

**Il meccanismo reale, in 4 livelli di difesa** (`app/Foundation/Tenancy/`):

1. **Risoluzione del tenant da fonte fidata** — mai da un parametro URL/query lato client:
   - `ResolveTenantFromKey` middleware → legge header `X-Tenant-Key` (compilato nell'app Flutter), risolve via `TenantRegistry::findByApiKey()`
   - `JwtGuard::user()` → il tenant è nel claim firmato `tid` del JWT, cross-controllato contro `user.tenant_id`
   - `BindDashboardTenant` middleware → dal `tenant_id` dell'utente autenticato in sessione
   - `TenantAwareJob` trait → per i job in coda, il `tenantId` è serializzato nel job stesso

2. **Contesto per-richiesta** — `CurrentTenant` (singleton scoped, un'istanza per richiesta/job), tenuto in un value object immutabile `TenantContext`, con cache 300s (`TenantRegistry`)

3. **Scope Eloquent globale** — `TenantScope` (applicato via trait `BelongsToTenant` su 21 modelli): ogni query viene filtrata automaticamente `WHERE tenant_id = current_tenant_id`; la creazione di record auto-compila `tenant_id` e rifiuta (con eccezione) un `tenant_id` esplicito che non corrisponda al contesto corrente

4. **Fail-closed**: se nessun contesto è legato e non è stato dichiarato un bypass esplicito, `CurrentTenant::id()` **lancia un'eccezione** invece di eseguire una query non filtrata

5. **Mascheramento cross-tenant**: un tentativo di leggere/modificare una risorsa di un altro tenant tramite UUID indovinato risponde sempre con **404 generico**, mai 403 — per non confermare l'esistenza della risorsa a chi sta probando

**Test che verificano questo comportamento** (letti per intero): `tests/Feature/TenantIsolationTest.php` (5 test), `tests/Feature/AppFactory/WhiteLabelIsolationTest.php` (3 test), `tests/Feature/DashboardSecurityTest.php` (7 test, di cui 3 sull'isolamento). Tutti confermano: nessuna fuga di dati cross-tenant nei percorsi testati, risposta 404 (non 403) per UUID di altri tenant.

**Punti deboli reali di questo modello, verificati nel codice**:
- Il modello `User` **non** usa lo scope automatico (per una ragione architetturale dichiarata: l'autenticazione avviene prima che un contesto tenant esista) — l'isolamento su `User::query()` dipende **da ogni singolo punto di chiamata** che aggiunge manualmente `where('tenant_id', ...)`, non da una garanzia strutturale
- Il commento nel trait `BelongsToTenant` dichiara l'esistenza di un "test di architettura" che impedirebbe di dimenticare il trait su un nuovo modello tenant-scoped — **questo test NON esiste** (`tests/Architecture` non è mai stato trovato). È un disallineamento tra commento e realtà: oggi nulla impedisce, a livello automatico, che un futuro modello con `tenant_id` venga creato senza il trait
- Nessun vincolo a livello di database (CHECK constraint) lega `type` e `tenant_id` su `users` — es. nulla impedisce, a livello di schema, un `customer` con `tenant_id = null`

**Conclusione a questa domanda**: il sistema funziona con **row-level isolation su database condiviso**, non con un database per tenant. Il meccanismo è progettato bene (4 livelli di difesa, fail-closed, testato con test di isolamento reali) — ma è comunque un modello "isolamento logico", non "isolamento fisico". Con un database condiviso, un bug futuro nello scope globale (o un nuovo modello a cui ci si dimentica di applicare il trait) è strutturalmente in grado di causare una fuga cross-tenant — il rischio non è nullo, è mitigato.

---

## FASE 9 — Scalabilità

Base fattuale (non stimata): `platform-infra/terraform/pilot/main.tf` e `platform-infra/bin/deploy.sh`.

**Topologia di deploy reale definita nel codice**: **1 singola istanza EC2** (`aws_instance.app`, non un Auto Scaling Group), **1 singolo RDS MySQL** `db.t4g.micro` (20GB gp3), Caddy + PHP-FPM sulla stessa istanza, worker di coda come servizio systemd sulla stessa istanza, nessun load balancer, nessun Redis (commentato esplicitamente "niente Redis" nel deploy). Il deploy stesso (`deploy.sh`) fa `composer install`, `migrate`, cache di config/route/view e riavvia il worker — tutto sequenziale, senza blue-green o rolling update.

**10 clienti**: nessun problema. Il modello attuale (DB condiviso + scope Eloquent + coda `database`) regge comodamente questo carico su un singolo `db.t4g.micro`.

**100 clienti**: probabilmente ancora reggibile con l'infrastruttura attuale, ma comincia a esporre i limiti architetturali: (a) le build APK (`local` driver) sono processi CPU/IO-intensivi (`flutter build apk` reale) eseguiti **sulla stessa macchina che serve il traffico web** se lanciate lì — nel codice non c'è alcuna separazione di infrastruttura tra "web server" e "build machine" per il driver `local` (il driver `github` sposta il carico su GitHub Actions, ma non è il default — il default è `manual`); (b) la coda `database` (polling su tabella `jobs`) diventa un collo di bottiglia di scrittura più rapidamente di Redis/SQS sotto carico concorrente.

**1000 clienti**: qui il collo di bottiglia diventa strutturale, non solo di capacità:
- **Singola istanza EC2, nessun autoscaling**: un solo processo PHP-FPM serve tutto il traffico di tutti i tenant — non c'è nel codice/infrastruttura alcun meccanismo di scalata orizzontale
- **Un solo RDS `t4g.micro`**: tutte le query di tutti i tenant condividono lo stesso database — a 1000 tenant con appuntamenti/notifiche attive, il carico di scrittura su tabelle come `appointments`, `appointment_events`, `notification_records` (quest'ultima esplicitamente senza foreign key, con un commento nel codice che la segnala come "candidata a partizionamento") diventerebbe rilevante
- **Storage locale di default**: gli artefatti APK/asset vivono su disco locale dell'istanza (`local`/`public` disk) — non scalano orizzontalmente senza un disco condiviso o S3 realmente popolato
- **Scheduler**: un solo comando schedulato (`notifications:dispatch-due`), nessuna pulizia automatica di dati storici/log — a 1000 tenant le tabelle di log/notifiche crescono senza manutenzione automatica

**10000 clienti**: l'architettura applicativa (row-level multi-tenancy con scope Eloquent) è di per sé compatibile con questa scala — è lo stesso pattern usato da SaaS multi-tenant di grandi dimensioni — ma **l'infrastruttura concretamente definita in questo repository (1 EC2 + 1 RDS micro, niente Redis, niente autoscaling, niente CDN per gli asset)** non la sosterrebbe senza un lavoro infrastrutturale sostanziale: RDS più grande con read replica, cache Redis, code su SQS/Redis, storage S3 realmente attivo con CDN, separazione della build machine dal web server, sharding o partizionamento delle tabelle ad alto volume.

**Collo di bottiglia principale, in ordine di impatto**: (1) topologia a istanza singola senza autoscaling/failover, (2) build APK reali eseguibili sulla stessa macchina applicativa se il driver è `local`, (3) storage locale non condiviso, (4) assenza di manutenzione automatica dei dati (nessuna pulizia schedulata oltre le notifiche).

---

## FASE 10 — Cosa manca (solo architettura, non design/UI)

**Limiti reali**:
- Nessun database/schema per tenant: isolamento interamente logico (Fase 8)
- `versionCode`/`versionName` dell'APK non parametrizzati per tenant/build — ogni APK dichiara la stessa versione statica
- Signing di release non attivo sul driver `local` (fallback silenzioso a debug-signing)
- Nessuna feature di pagamento — il prodotto oggi non incassa nulla in autonomia
- Analytics prodotto non collegato a nessun provider reale (solo no-op sink)
- Nessun test esercita l'intera pipeline di build reale (`flutter build apk` end-to-end) — i due test esistenti bypassano il dispatcher reale o si fermano al controllo "cartella mancante"
- Nessuna policy di autorizzazione dedicata (`app/Policies` non esiste) — l'intero controllo accessi è a guard/middleware, non granulare per risorsa
- MFA per il Control Room è opzionale a livello di riga (`mfa_enforced`), non imposta a livello di guard/framework — un super-admin creato fuori dal comando ufficiale potrebbe non averla mai
- Un solo task schedulato in tutto il sistema — nessuna pulizia automatica di dati/log/token scaduti
- Storage locale come default operativo, S3 configurato ma non popolato
- Nessuna separazione infrastrutturale tra web server e build machine
- Topologia a istanza singola, nessun autoscaling/HA nel Terraform esistente

**Rischi**:
- Il commento nel codice che dichiara l'esistenza di un "test di architettura" a protezione del trait tenant è falso — nessuna rete di sicurezza automatica impedisce a un futuro modello di dimenticare `BelongsToTenant`
- `User` (autenticazione) non ha scope automatico — dipende da disciplina manuale in ogni punto di accesso
- Nessun vincolo DB che leghi `type` e `tenant_id` su `users`

**Cosa contesterebbe uno sviluppatore senior**:
- L'assenza di un test end-to-end reale della build pipeline (il pezzo più "fisico" e fragile del sistema è anche il meno testato realmente)
- Il fatto che versione DB (`app_versions`/`app_builds.version`) e versione APK reale (`pubspec.yaml`) siano due sistemi scollegati
- MFA opzionale via flag di riga invece che invariante di sistema
- L'assenza di `app/Policies` in un sistema con RBAC a 4 ruoli e superficie multi-tenant — oggi tutto passa da guard+middleware, funziona ma non è la granularità tipica attesa in Laravel per questo dominio
- Topologia infra a singolo punto di guasto, dichiarata esplicitamente "pilota" nel codice stesso — non è un errore, ma va comunicato come tale

---

## FASE 11 — Risposta allo sviluppatore

> "L'unico collo di bottiglia è che l'app è statica e ogni app dovrebbe poter modificare il proprio database senza influenzare gli altri APK."

**Questa critica, così com'è formulata, non è corretta — e nemmeno il problema reale del sistema.**

Primo: l'affermazione confonde due concetti diversi. Un APK Android **non ha un proprio database server-side** da "modificare" — l'app Flutter è un client che parla con un'unica API REST; l'unico storage locale sul dispositivo è cache/preferenze (`shared_preferences`, `flutter_secure_storage`), non un database applicativo condiviso tra tenant. Non esiste nel codice alcuna architettura in cui gli APK di tenant diversi "si influenzano a vicenda" attraverso un database condiviso lato client — perché non c'è un database lato client in questo senso.

Secondo, e più rilevante: il sistema **già garantisce** che i dati di un tenant non influenzino un altro tenant — non tramite database separati, ma tramite lo scope Eloquent verificato in Fase 8 (`TenantScope` + `BelongsToTenant`, fail-closed, testato con test di isolamento reali che passano). Ogni tenant vede solo i propri appuntamenti, clienti, servizi, build, feedback: questo è già vero oggi, verificato nel codice, non "da costruire".

Terzo: "l'app è statica" è vero in un senso preciso e voluto — il **codice sorgente** Flutter è condiviso da tutti i tenant (nessun fork per cliente), ma i **dati** non sono affatto statici né condivisi: ogni tenant ha il proprio `tenant_id` e vede esclusivamente i propri record. Questo è per design, non un limite: mantenere un unico codice sorgente Flutter è ciò che rende sostenibile aggiornare 10, 100 o 1000 app senza dover ricompilare/mantenere N progetti Flutter divergenti. L'alternativa (un progetto Flutter forkato per tenant, con eventualmente un database imbarcato lato client) sarebbe un downgrade architetturale: più difficile da mantenere, da aggiornare in sicurezza, e da scalare — non un miglioramento.

**Il collo di bottiglia reale**, verificato nel codice (Fase 9), non è l'isolamento dati (già risolto) né la staticità del codice Flutter (scelta corretta) — è **l'infrastruttura di deploy**: una singola istanza EC2 senza autoscaling, un singolo RDS `t4g.micro`, storage locale non condiviso, build APK potenzialmente eseguite sulla stessa macchina che serve il traffico. Questo è ciò che davvero limiterebbe la crescita a 1000+ clienti, non il modello dati.

Se lo sviluppatore intendeva invece "ogni tenant dovrebbe avere un database fisicamente separato" (vera segregazione fisica, non solo logica) — quella è una critica architetturale legittima e discutibile nel merito (trade-off: più isolamento/rumore-a-parte vs. più complessità operativa, migrazioni N×, costo N× istanze DB), ma è una scelta progettuale alternativa, non un bug del sistema attuale. Il sistema attuale ha scelto **shared-database, row-level isolation** — un pattern comune e valido per SaaS multi-tenant a questa scala — e lo ha implementato con le difese corrette (fail-closed, 404-masking, test di isolamento).

---

## Risposte finali

**1. Che cosa abbiamo realmente costruito?**
Una piattaforma SaaS multi-tenant per la gestione di prenotazioni (booking) — autenticazione JWT, calendario/disponibilità con prevenzione conflitti, notifiche push reali (FCM), crash reporting reale (Sentry) — più un motore interno ("App Factory") che, per ogni cliente, genera identità/branding e può compilare realmente un APK Android white-label a partire da un'unica base di codice Flutter condivisa. Un pannello interno ("Control Room") permette di gestire l'intero ciclo di vita di ogni cliente senza toccare codice.

**2. È un semplice template Flutter, o una piattaforma SaaS White Label?**
È una piattaforma SaaS white-label. Il Flutter è solo l'ultimo anello della catena (il client) — dietro c'è un backend Laravel con multi-tenancy reale a livello dati, un motore di provisioning transazionale, una pipeline di build che compila davvero, e gestione del ciclo di vita del cliente (attivazione/sospensione, inviti, versioning, distribuzione beta).

**3. Le app condividono il backend?**
Sì. Un solo backend Laravel, una sola API, serve tutti i tenant.

**4. Le app condividono il database?**
Sì. Un unico database fisico condiviso, isolamento a livello di riga (`tenant_id` + scope Eloquent globale), non un database per tenant.

**5. Ogni cliente è realmente isolato?**
Sì, a livello logico — verificato con test che passano (404 su probing cross-tenant, nessuna fuga rilevata nei percorsi testati). Non isolato a livello fisico (stesso DB, stessa istanza). Punti di attenzione: `User` non ha scope automatico (dipende da convenzione), nessun test di architettura reale protegge nuovi modelli futuri dal dimenticare il trait di tenancy.

**6. Il progetto è già scalabile?**
A livello di modello dati/applicativo sì, fino a centinaia di tenant. A livello di infrastruttura effettivamente definita nel repository (1 EC2, 1 RDS micro, niente Redis, niente autoscaling) — no, non oltre poche centinaia di tenant senza lavoro infrastrutturale aggiuntivo. La topologia è esplicitamente etichettata "pilota" nel codice stesso.

**7. I 10 problemi più importanti rimasti** (in ordine di impatto):
1. Nessun autoscaling/HA infrastrutturale (singola istanza EC2 + singolo RDS micro)
2. Build APK reali potenzialmente eseguite sulla stessa macchina che serve traffico web (driver `local`)
3. `versionCode`/`versionName` dell'APK non parametrizzati — ogni build ha la stessa versione statica
4. Signing di release non attivo sul driver `local` (fallback silenzioso a debug)
5. Nessun pagamento implementato — il prodotto non incassa in autonomia
6. Nessun test end-to-end reale della build pipeline
7. MFA Control Room opzionale per riga, non invariante di sistema
8. Storage locale come default, S3 non popolato
9. Un solo task schedulato, nessuna pulizia automatica dei dati
10. Nessuna policy di autorizzazione granulare (`app/Policies` assente), controllo accessi solo a livello guard/middleware

**8. Se domani entrasse un Senior Software Engineer, quali sarebbero le prime critiche?**
- "Perché la build pipeline più fisica e fragile del sistema è anche la meno coperta da test reali?"
- "Perché esistono due sistemi di versioning (`app_versions` e `app_builds.version`) che non parlano con l'APK reale?"
- "Il commento sul test di architettura per la tenancy è falso — va rimosso o il test va scritto davvero."
- "MFA dovrebbe essere un invariante di sistema per i super-admin, non un flag per-riga."
- "L'infrastruttura Terraform è dichiaratamente 'pilota' — va bene per la demo, ma prima di onboardare clienti paganti serve un piano di scalata concreto (RDS più grande, coda su Redis/SQS, storage S3 reale, separazione build/web)."

**9. Quali invece sarebbero gli aspetti che apprezzerebbe maggiormente?**
- Il modello di tenancy è progettato con cura reale: 4 livelli di difesa, fail-closed by design, 404-masking anti-probing, e — soprattutto — **testato con test di isolamento che verificano davvero l'assenza di fughe cross-tenant**, non solo dichiarato.
- Il dominio booking (disponibilità, conflitti, cancellazioni, no-show) è implementato con attenzione a dettagli reali (lock di concorrenza, DST, idempotenza) — non un CRUD superficiale.
- Push notification e crash reporting sono realmente cablati end-to-end (non stub), con fallback ragionati (email se il push fallisce, no-op se Sentry non è configurato).
- La scelta di un unico codice sorgente Flutter con branding iniettato a build-time, invece di un fork per cliente, è architetturalmente corretta e sostenibile.
- Audit logging presente e usato consistentemente sulle azioni sensibili del Control Room.

**10. Valutazione onesta da CTO — 7/10.**
Motivazione: il nucleo applicativo (tenancy, booking, auth, build pipeline concettuale) è progettato e implementato con un livello di cura superiore alla media per uno stadio "beta" — con test reali a supporto delle affermazioni più critiche (isolamento dati). Quello che manca per un voto più alto non è la qualità del codice esistente, ma la **maturità operativa**: infrastruttura a singolo punto di guasto dichiaratamente "pilota", una pipeline di build reale ma sotto-testata end-to-end, versioning APK scollegato dalla realtà, signing di release non attivo di default, e zero monetizzazione. Sono tutti gap colmabili senza riscrivere nulla di esistente — non difetti strutturali del progetto, ma lavoro che resta da fare prima di poter dire "pronto per centinaia di clienti paganti".
