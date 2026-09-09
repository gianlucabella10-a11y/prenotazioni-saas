# PROJECT_FREEZE_STATE — Stato reale del progetto (congelamento)

> Documento generato leggendo direttamente il codice sorgente (nessuna modifica applicata). Ogni voce riflette ciò che è stato letto nei file elencati. Dove qualcosa non esiste, è scritto **ASSENTE** / **NOT FOUND**. La tabella finale di completamento (sezione 12) è l'unica parte che richiede un giudizio di sintesi — è dichiarata come tale, non presentata come misura meccanica.

Repo: `/Users/gianlucabella/Desktop/app prenotazioni progetto`
Branch: `docs/production-readiness-audit` — 2 file con modifiche non committate (`LocalBuildDispatcher.php`, `BuildPipelineTest.php`, fix di un bug sull'UUID del manifest).

---

## 1. Moduli backend — stato

| Modulo (`app/Modules/*` o `app/Foundation/*`) | Stato | Motivo |
|---|---|---|
| `TenantManagement` | **COMPLETATO** | Provisioning transazionale, macchina a stati, invito, quote — tutto funzionante e testato |
| `Scheduling` | **COMPLETATO** | Booking completo: slot calc, lock di concorrenza, cancellazione, no-show, idempotenza |
| `Catalog` | **COMPLETATO** | Servizi/categorie/varianti/location, CRUD completo |
| `Staff` | **COMPLETATO** | Anagrafica operatori, orari, associazione servizi |
| `Customers` | **COMPLETATO** | CRM minimo (note, consensi, account) |
| `Branding` | **COMPLETATO** | Asset pipeline (GD), rollback versionato, validatore di contrasto |
| `Notifications` | **COMPLETATO** (nucleo) / **PARZIALE** (operatività) | Canali email+FCM reali; un solo task schedulato per l'invio differito, nessuna pulizia automatica |
| `AppFactory` | **PARZIALE** | Orchestrazione reale e build `local` che compila davvero, ma: signing release non attivo di default (fallback debug), versionCode/versionName non parametrizzati, checksum solo per driver `local`, nessun test end-to-end della build reale |
| `ControlRoom` | **COMPLETATO** (funzioni esposte) / **PARZIALE** (copertura ciclo di vita) | Nessuna azione "termina/archivia" esposta in UI nonostante l'enum la preveda; nessuna vista di audit log |
| `Dashboard` | **COMPLETATO** | Pannello titolare/staff: agenda, servizi, staff, disponibilità, branding |
| `Foundation/Tenancy` | **COMPLETATO** | Scope globale, contesto per-richiesta, fail-closed, testato — con una nota: il commento che dichiara un "test di architettura" a protezione del trait è falso (il test non esiste) |
| `Foundation/Auth` | **COMPLETATO** | Guard JWT custom, MFA TOTP, verifica email, refresh token con rotazione |
| `Foundation/Http` | **PARZIALE** | Middleware ed exception handling solidi, ma nessun envelope di risposta unificato (3 pattern diversi coesistono — vedi sezione 6) e CORS non ristretto (`*`) |
| `Foundation/Audit` | **PARZIALE** | Servizio funzionante, ma copertura non esaustiva (letture, alcune azioni di build/generate non loggate) |

---

## 2. Cartelle — responsabilità, dipendenze, stato

### Backend (`platform-backend/app/`)

| Cartella | Responsabilità | Dipendenze principali | Stato |
|---|---|---|---|
| `Modules/TenantManagement/` | Ciclo di vita tenant, piani, quote, inviti | `Foundation/Tenancy`, `Foundation/Audit` | COMPLETATO |
| `Modules/Scheduling/` | Dominio booking: disponibilità, prenotazione, stati appuntamento | `Modules/Catalog`, `Modules/Staff`, `Modules/Customers`, `Foundation/Tenancy` | COMPLETATO |
| `Modules/Catalog/` | Servizi/varianti/categorie/location | `Foundation/Tenancy` | COMPLETATO |
| `Modules/Staff/` | Anagrafica staff, orari | `Foundation/Tenancy`, `Modules/Catalog` | COMPLETATO |
| `Modules/Customers/` | Profilo cliente, consensi, cancellazione account | `Foundation/Auth`, `Foundation/Tenancy` | COMPLETATO |
| `Modules/Branding/` | Asset e tema white-label | `Foundation/Tenancy`, disco `public`/`local` | COMPLETATO |
| `Modules/Notifications/` | Invio notifiche multi-canale (email/push) | `Foundation/Tenancy`, `config/services.php` (FCM) | COMPLETATO nucleo / PARZIALE operatività |
| `Modules/AppFactory/` | Identità app, manifest, build, distribuzione beta | `Modules/TenantManagement`, `Modules/Branding`, Flutter project esterno (`platform-mobile`) | PARZIALE |
| `Modules/ControlRoom/` | Pannello interno super-admin | `Modules/TenantManagement`, `Modules/AppFactory`, `Modules/Branding` | PARZIALE (copertura ciclo di vita) |
| `Modules/Dashboard/` | Pannello titolare/staff (web, sessione) | `Modules/Scheduling`, `Modules/Catalog`, `Modules/Staff`, `Modules/Branding` | COMPLETATO |
| `Foundation/Tenancy/` | Isolamento multi-tenant riga-per-riga | Cache Laravel, Eloquent | COMPLETATO |
| `Foundation/Auth/` | Guard JWT, MFA, verifica email, refresh token | `firebase/php-jwt` | COMPLETATO |
| `Foundation/Http/` | Middleware trasversali, envelope errori | — | PARZIALE (envelope successo non unificato) |
| `Foundation/Audit/` | Log azioni sensibili (scrittura diretta su tabella) | — | PARZIALE |
| `Foundation/Enums/` | `UserType` | — | COMPLETATO |
| `Console/Commands/` | 7 comandi artisan (vedi sezione 4) | vari moduli | COMPLETATO |
| `Models/` | `User`, `Device`, `MfaCredential`, `RefreshToken` (platform-level, non tenant-scoped) | `Foundation/Auth` | COMPLETATO (con limite noto: `User` non ha scope automatico) |

### Flutter (`platform-mobile/apps/client_app/lib/`)

| Cartella | Responsabilità | Dipendenze principali | Stato |
|---|---|---|---|
| `app/` | Composizione root: `main.dart`, provider graph, router | Riverpod, go_router | COMPLETATO |
| `core/analytics/` | Interfaccia analytics + sink no-op | — | **PARZIALE — mai istanziato in `providers.dart`, codice morto a runtime** |
| `core/env/` | Lettura configurazione dart-define (`API_BASE_URL`, `TENANT_KEY`) | `String.fromEnvironment` | COMPLETATO |
| `core/network/` | Client Dio, refresh token single-flight, normalizzazione errori | `dio`, `TokenStorage` | COMPLETATO |
| `core/push/` | Lifecycle FCM (init/permessi/token/registrazione) | `firebase_core`, `firebase_messaging` | **PARZIALE — richiede file di config Firebase nativi assenti dal repo** |
| `core/session/` | Stato di sessione (guest/autenticato/verificato) | `AuthRepository`, `MeRepository` | COMPLETATO |
| `core/storage/` | Storage sicuro token | `flutter_secure_storage` | COMPLETATO |
| `core/utils/` | Formattazione, mapping errori→copy IT | `intl` | COMPLETATO |
| `features/appointments/` | Lista/cancellazione appuntamenti | `booking_repository` | COMPLETATO |
| `features/auth/` | Login, registrazione, verifica email | `core/session`, `core/network` | COMPLETATO |
| `features/booking/` | Flusso di prenotazione (3 step) | `features/catalog`, `core/network` | COMPLETATO (screens senza test dedicato — vedi §7.8) |
| `features/business/` | Scheda attività (contatti, orari, sedi, staff) | `white_label_config` | COMPLETATO |
| `features/catalog/` | Repository/dominio servizi (no UI propria) | `core/network` | COMPLETATO |
| `features/home/` | Home branding-aware | `white_label_config`, `booking` | COMPLETATO |
| `features/profile/` | Profilo, modifica dati, cancellazione account | `core/session` | COMPLETATO (nessun test dedicato) |
| `features/white_label/` | Config runtime, tema dinamico, splash, schermata tenant non operativo | `core/network`, `SharedPreferences` | COMPLETATO (font custom non incluse — fallback a font di sistema) |

### Infrastruttura (`platform-infra/`)

| Cartella | Responsabilità | Stato |
|---|---|---|
| `terraform/pilot/` | 1 EC2 + 1 RDS MySQL micro + S3 assets + SES, dichiarato esplicitamente "pilota" | COMPLETATO **per lo scope dichiarato** (pilota), non per produzione a scala |
| `bin/deploy.sh` | Deploy via rsync+SSH, composer install, migrate, cache, restart worker | COMPLETATO (nessun blue-green/rolling) |
| `server/user-data.sh` | Cloud-init: PHP 8.4, Caddy, worker, scheduler | non riletto in questo passaggio (già coperto indirettamente da `deploy.sh`) |

---

## 3. Servizi — cosa fanno, dove sono usati, se funzionano

### Backend

| Servizio | Cosa fa | Dove è usato | Funzionante? |
|---|---|---|---|
| `CurrentTenant` (+ `TenantRegistry`, `TenantContext`, `TenantScope`) | Risoluzione e scoping del tenant corrente | Ovunque nei moduli tenant-scoped | **Sì**, testato |
| `AuthenticationService` / `JwtGuard` / `JwtService` / `RefreshTokenService` | Login, MFA, emissione/verifica/rotazione JWT | Guard `api` | **Sì**, testato |
| `EmailVerificationService` | Codice a 6 cifre, verifica email | `AuthController`, middleware `verified` | **Sì**, testato |
| `Totp` | TOTP RFC 6238 | Auth API + Control Room + Dashboard | **Sì**, testato |
| `AuditLogger` | Scrittura diretta su `audit_logs` (no model Eloquent) | ~20 punti di chiamata elencati in Fase 8 dell'audit precedente | **Sì**, ma copertura non esaustiva (fail-silent by design) |
| `ProvisionTenant` / `ChangeTenantStatus` / `IssueTenantInvite` / `QuotaService` | Ciclo di vita tenant | Control Room, API admin | **Sì**, testato |
| `BookAppointment` / `CancelAppointment` / `AvailabilityCalculator` | Motore di prenotazione | `AppointmentController`, `ManageAgendaController` | **Sì**, testato (lock concorrenza + unique index) |
| `AllocateAppIdentifiers` / `GenerateAppPackage` / `ExportAppPackage` / `PrepareApp` | Identità app, manifest, pacchetto esportabile | `GenerateApp` command, Control Room | **Sì** |
| `BuildService` / `RunAppBuildJob` / `LocalBuildDispatcher` / `GithubBuildDispatcher` / `LogBuildDispatcher` | Orchestrazione e dispatch build | Control Room, `BuildAppCommand` | **Sì per `local`** (compila realmente); `Github`/`Log` non verificati end-to-end in questo passaggio |
| `StoreBrandLogo` / `GenerateBrandAssets` / `RollbackBrandAssets` / `ContrastValidator` | Pipeline asset brand | Control Room, Dashboard | **Sì**, testato |
| `FcmPushChannel` / `EmailChannel` / `SendNotificationJob` / `TemplateRenderer` | Invio notifiche multi-canale | `DispatchDueNotifications`, eventi booking | **Sì** (FCM richiede credenziali reali in produzione — codice non è uno stub) |
| `AppPreview` / `BuildFleet` / `TemplateRegistry` / `TransitionAppProject` | Supporto Control Room (preview, aggregati flotta, stati progetto) | `AppProjectController` | **Sì** |

### Flutter

| Servizio | Cosa fa | Dove è usato | Funzionante? |
|---|---|---|---|
| `ApiClient` | Wrapper Dio: header tenant/auth, refresh 401 single-flight, normalizzazione errori | Ogni repository | **Sì**, testato indirettamente (e2e) |
| `TokenStorage` (`SecureTokenStorage`) | Persistenza sicura token | `ApiClient`, `SessionController` | **Sì** |
| `SessionController` | Stato sessione (guest/autenticato/verificato), login/logout/delete | `router.dart` (guard) | **Sì** |
| `PushNotificationService` | Lifecycle FCM lato client | `app.dart` (init asincrono) | **Parzialmente** — codice reale, ma richiede file di config Firebase nativi assenti dal repo; oggi si disattiva silenziosamente |
| `AnalyticsService` | Tracciamento eventi prodotto | **Nessuno** — mai istanziato in `providers.dart` | **No** — codice morto a runtime, esercitato solo dal proprio test |
| `WhiteLabelRepository` | Fetch/cache config brand (ETag) | `whiteLabelConfigProvider` | **Sì** |
| `AppThemeBuilder` | Costruzione `ThemeData` dinamica da config | `app.dart` | **Sì**, con fallback hardcoded se colori assenti/malformati |

---

## 4. Comandi Artisan

| Nome | Utilizzo | Output |
|---|---|---|
| `app:generate {tenant}` | Genera identità app + asset + manifest per un tenant (nessuna build nativa) | Stampa bundle id, template, versione/piattaforma build, path manifest, stato finale |
| `app:build {tenant} {platform=android}` | Mette in coda una build reale per il progetto del tenant | Stampa id/stato build, path futuro dell'artefatto, driver configurato, promemoria `queue:work` se necessario |
| `app:build-matrix {--platform=} {--stale-only} {--limit=}` | Calcola l'elenco di tenant da ricostruire in batch (per CI) | Stampa un array JSON di UUID tenant (ultima riga di output, consumato dalla CI) |
| `app:build-record {tenant} {platform} {status} {--app-version=} {--artifact=} {--error=}` | Callback CI: registra l'esito di una build/pubblicazione esterna | Aggiorna/crea riga `app_builds`, transiziona `AppProject`; nessun output interattivo oltre conferma |
| `control-room:create-admin {email} {--password=}` | Crea/aggiorna un utente `super_admin` con MFA forzata | Stampa email e password (generata se non fornita) |
| `notifications:dispatch-due {--chunk=500}` | Sweep di sicurezza: invia notifiche schedulate scadute | Dispaccia `SendNotificationJob` per ogni record; schedulato ogni 15 minuti |
| `jwt:generate-keys {--force}` | Genera coppia di chiavi RSA 2048-bit per firma JWT | Scrive i file su `config('jwt.private_key_path'/'public_key_path')`; rifiuta di sovrascrivere senza `--force` |

---

## 5. Control Room — schermate

| Percorso | Funzionalità | Stato |
|---|---|---|
| `/control-room/login` | Login super-admin (guard `admin`) | COMPLETATO |
| `/control-room/mfa`, `/control-room/mfa/setup` | Sfida/setup MFA TOTP | COMPLETATO (ma opzionale a livello di riga — vedi §11) |
| `/control-room/clienti` (`index`) | Lista tenant, ricerca per nome, filtro stato | COMPLETATO |
| `/control-room/clienti/nuovo` (`create`) | Form creazione tenant | COMPLETATO |
| `/control-room/clienti/{uuid}` (`show`) | Scheda tenant: dati, stato abbonamento, invito, API key in chiaro, quick-setup brand | COMPLETATO |
| Azioni su `/clienti/{uuid}`: sospendi/riattiva/attiva | Transizione di stato tenant | COMPLETATO — **manca "termina/archivia" in UI** (l'enum lo prevede, nessuna route lo espone) |
| Azioni invito: rigenera/revoca | Gestione invito titolare | COMPLETATO |
| `/control-room/apps` (`index`) | Lista progetti app | COMPLETATO |
| `/control-room/apps/flotta` (`fleet`) | Aggregati build/versioni su tutta la flotta | COMPLETATO |
| `/control-room/apps/{uuid}` (`show`) | Preview app, dettagli tecnici, template, build (log/asset/versioni/beta-link/tester/feedback), rollback asset | COMPLETATO |
| Azioni su `apps/{uuid}`: genera/build/beta-link/revoca/tester/versioni/rollback/download | Intero ciclo App Factory da UI | COMPLETATO |

**Assente**: nessuna vista che mostri l'audit log (`audit_logs`) dal Control Room, nessuna gestione fatturazione/pagamenti, nessuna azione di terminazione/archiviazione tenant.

---

## 6. API (`/api/v1`, `routes/api.php`)

**Pattern di risposta — non unificato**, 3 forme coesistono nel codice (verificato leggendo 5 controller):
- `JsonResource`/`AnonymousResourceCollection` (es. `AppointmentController`) → include pagination envelope standard Laravel (`data`, `links`, `meta`) sulle liste cursor-paginate
- Array grezzo senza wrapper (`AvailabilityController::index` → `response()->json($result)`)
- `{"data": ...}` costruito a mano (Catalog, Me, AdminTenant) — con eccezioni non coerenti (`MeController::destroy` → `{"status":"deleted"}` senza `data`; `AdminTenantController::store` → `data` + chiavi sorelle `admin`/`invite_token`/`tenant_api_key`)

**Errori** — envelope unico e coerente per le eccezioni gestite: `{"error": {"code", "message", "details"?}}`, reso solo per richieste `api/*` e solo per 6 tipi di eccezione esplicitamente intercettati (`ApiException`, `SlotUnavailable`, `InvalidStatusTransition`, `ValidationException`, `AuthenticationException`, `ModelNotFoundException|NotFoundHttpException`); altre eccezioni non gestite ricadono sul JSON di default di Laravel.

**CORS**: nessun `config/cors.php` applicativo — usato il default Laravel a monte, `allowed_origins: ['*']` (wide open).

**Endpoint** (35 route sotto `/api/v1`):

| Endpoint | Autenticazione | Forma risposta |
|---|---|---|
| `GET /app/config` | `tenant.key` (header `X-Tenant-Key`) | `{"data": ...}` presunto (white-label config) |
| `GET /catalog/services` | `tenant.key` + `tenant.operating` | `{"data": [...]}`, non paginato |
| `GET /staff` | `tenant.key` + `tenant.operating` | array grezzo (non verificato in dettaglio) |
| `GET /availability` | `tenant.key` + `tenant.operating`, throttle `availability` (60/min per utente/ip) | array grezzo senza wrapper |
| `POST /auth/register` | `tenant.key` + `tenant.operating`, throttle `auth` (5/min per ip) | — |
| `POST /auth/login` | idem | — |
| `POST /auth/refresh` | throttle `auth` (nessun tenant.key richiesto) | — |
| `POST /auth/logout` | throttle `auth` | — |
| `POST /auth/mfa/verify`, `/setup`, `/confirm` | `auth:api` + throttle `auth` | — |
| `POST /auth/email/verify`, `/resend` | `auth:api`+`auth.full`+`user.type:customer`+throttle `api`/`auth` | — |
| `GET /me`, `DELETE /me` | `auth:api`+`auth.full`+`user.type:customer` | `{"data": ...}` / `{"status":"deleted"}` (inconsistente) |
| `POST /me/feedback` | idem | — |
| `PATCH /me`, `PUT /me/consents`, `PUT/DELETE /me/devices` | idem + `verified`+`tenant.operating` | — |
| `GET/POST /appointments`, `GET /appointments/{uuid}`, `POST /appointments/{uuid}/cancel` | idem | `JsonResource`/`AnonymousResourceCollection` (cursor pagination standard Laravel) |
| `GET /manage/agenda`, `/manage/appointments/{uuid}/confirm|complete|no-show|cancel` | `auth:api`+`auth.full`+`user.type:tenant_admin,staff` | — |
| `GET/POST/PATCH/DELETE /manage/services`, `/manage/services/{uuid}/variants` | idem + `user.type:tenant_admin` | — |
| `GET/POST/PATCH/DELETE /manage/staff`, `PUT /manage/staff/{uuid}/schedules` | idem | — |
| `GET/PUT /manage/brand` | idem | — |
| `GET/POST /admin/tenants`, `POST /admin/tenants/{uuid}/suspend|reactivate|activate` | `auth:api`+`auth.full`+`user.type:super_admin` | `{"data": [...], "next_page": ...}` custom (no `meta`/`total`) |

**Rate limit configurati** (`config/api.php`): `auth` 5/min per IP, `availability` 60/min per utente/IP, `api` anonimo 60/min per IP, `api` autenticato 120/min per utente **più** un tetto 1000/min per tenant applicato in aggiunta.

**Versioning API**: solo `/api/v1`, nessun meccanismo v2/deprecazione — **NOT FOUND**.
**Health check**: `/up`, stock Laravel, non personalizzato.
**OpenAPI/Swagger**: **NOT FOUND**.

---

## 7. Flutter — pagine, servizi, providers, routing, configurazione

**35 file Dart totali**, 9 cartelle feature (`appointments`, `auth`, `booking`, `business`, `catalog`, `home`, `profile`, `white_label`) — nessuna cartella `payments`/`chat`/`loyalty`/`settings`/`onboarding` esiste.

**7.1 Pagine (12 schermate, tutte complete — zero stub/TODO/placeholder in tutto `lib/`)**:
`SplashScreen`, `TenantUnavailableScreen`, `LoginScreen`, `RegisterScreen`, `VerifyEmailScreen`, `HomeScreen`, `BusinessInfoScreen`, `BookingServicesScreen`, `BookingScheduleScreen`, `BookingSuccessScreen`, `MyAppointmentsScreen`, `ProfileScreen`.

**7.2 Servizi**: vedi sezione 3 (tabella Flutter).

**7.3 Providers (17 totali, Riverpod v3)**: `tokenStorageProvider`, `apiClientProvider`, `whiteLabelRepositoryProvider`, `authRepositoryProvider`, `catalogRepositoryProvider`, `bookingRepositoryProvider`, `meRepositoryProvider`, `pushNotificationServiceProvider`, `myProfileProvider`, `whiteLabelConfigProvider`, `sessionControllerProvider`, `servicesProvider`, `staffProvider`, `appointmentsProvider`, `routerProvider`, `bookingFlowProvider`, `availabilityProvider`.

**7.4 Routing** (`lib/app/router.dart`, go_router, 12 route flat, nessuna `ShellRoute`): guard unico basato su `redirect:` che combina stato config white-label + stato sessione — non operativo → `/unavailable`; non autenticato → `/login`/`/register` soltanto; autenticato non verificato → `/verify-email`; altrimenti → `/home`.

**7.5 Configurazione**: `main.dart` valida `TENANT_KEY` a startup (`throw StateError` se assente — fail loudly by design), Sentry condizionale al dart-define `SENTRY_DSN` (disattivo se vuoto), Firebase inizializzato in modo asincrono/lazy da `PushNotificationService`, nessun override di provider al bootstrap (solo nei test). `API_BASE_URL`/`TENANT_KEY` letti via `String.fromEnvironment` in un solo punto (`app_environment.dart`) e consumati in un solo punto (`api_client.dart`).

**7.6 White label a runtime**: `GET /app/config` chiamato **a ogni avvio** (non solo la prima volta), con ETag/cache condizionale; fallback a cache locale solo se la rete fallisce; se non c'è cache e la rete fallisce, l'app mostra un errore con retry (non finge un config fittizio). Tema costruito dinamicamente da `AppThemeBuilder`, con palette di fallback interamente hardcoded. **Font personalizzati non incluse nel repo** (`assets/fonts/README.md` lo dichiara esplicitamente) — l'app usa il font di sistema di default.

**7.7 Auth (lato mobile)**: token in `flutter_secure_storage`, refresh single-flight su 401 con Dio dedicato per evitare ricorsione, logout best-effort (offline-safe), cancellazione account con doppia conferma, sessione riidratata a freddo via `GET /me`.

**7.8 Copertura test — lacune verificate**: nessun test per `booking_services_screen.dart`, `booking_schedule_screen.dart`, `booking_success_screen.dart`, `profile_screen.dart`, `me_repository.dart`, `white_label_repository.dart`, `splash_screen.dart`, `tenant_unavailable_screen.dart`, `app/app.dart`, `app/router.dart` (il guard di routing, il pezzo di logica più delicato dell'app, non è testato), `core/env/app_environment.dart`.

---

## 8. Build Engine — pipeline completa

```
POST /control-room/apps/{uuid}/build
  → AppProjectController::dispatchBuild()
  → BuildService::request()  — valida platform, richiede manifest già generato,
                                crea app_builds(status=queued, version="1.0.0+N"),
                                transiziona AppProject→queued, dispatch coda
  → RunAppBuildJob::handle() (ShouldQueue, driver coda "database" di default)
  → BuildDispatcher risolto da config('app_factory.build_driver'):
        "manual" (default)  → LogBuildDispatcher   (registra intento, nessuna build reale)
        "github"            → GithubBuildDispatcher (avvia GitHub Actions via API)
        "local"             → LocalBuildDispatcher  (compila DAVVERO sulla macchina)
  → [driver "local"] legge manifest da disco (per UUID TENANT, fix appena applicato)
  → verifica dir Flutter esiste
  → flutter clean → pub get → analyze → test → build apk --release
        -PAPP_ID={package_name} -PAPP_NAME={app_name} --dart-define=...
  → verifica app-release.apk prodotto
  → sha256 checksum
  → copia su storage/app/private/builds/{tenant_id}/{version}/app-release.apk
  → persiste su app_builds (status=built, checksum, log, durata, size)
```

**Difetti verificati nel codice**: nessun `--build-name`/`--build-number` passato → versione APK statica da `pubspec.yaml` per tutti i tenant; nessuna env keystore impostata dal dispatcher → firma release non attiva (fallback debug); checksum popolato solo per driver `local`; nessun test esercita l'intera pipeline reale (i 2 test esistenti sostituiscono il dispatcher con un fake, o si fermano al controllo "cartella mancante").

---

## 9. White Label Engine — pipeline completa

```
Creazione tenant → ProvisionTenant crea BrandProfile di default
Control Room "Quick Setup" → TenantBrandController::update / uploadLogo
  → StoreBrandLogo (valida MIME/dimensione minima 256px, salva su disco, checksum)
  → GenerateBrandAssets (PHP GD puro): 9 icone Android/iOS + 3 splash + 2 store asset
    versionate (v{n}), vecchie versioni conservate per rollback
  → bump BrandProfile.config_version (cache-busting)
Runtime (app installata) → GET /api/v1/app/config (header X-Tenant-Key)
  → Flutter: WhiteLabelRepository (fetch a ogni avvio, ETag, fallback a cache offline)
  → AppThemeBuilder costruisce ThemeData dinamicamente, fallback hardcoded per colori mancanti
Build APK → GenerateAppPackage scrive manifest-latest.json (schema 2.0) su disco
  → usato SOLO per costruire i dart-define della build (non letto a runtime dall'app)
```

Nota architetturale confermata nel codice: il Flutter non viene mai clonato/forkato per tenant — un solo sorgente, branding iniettato a build-time (Gradle property + dart-define) e a runtime (endpoint `/app/config`).

---

## 10. App Factory — pipeline completa

```
ProvisionTenant (creazione tenant)
  → AllocateAppIdentifiers::execute()
      bundle_id = package_name = "com.platform.t{base36(tenant_id)}" (immutabile, unique)
  → GenerateApp command / Control Room "genera"
      PrepareApp::execute() → GenerateBrandAssets → GenerateAppPackage → ExportAppPackage
      (manifest + pacchetto self-contained scaricabile, nessuna build nativa qui)
  → BuildService::request() (vedi sezione 8, Build Engine)
  → BetaDownloadToken: link revocabile, scadenza 7gg fissa, limite download (default 50)
  → BetaTester / BetaFeedback: gestione tester da Control Room, feedback raccolto
      dall'app (POST /me/feedback) e mostrato in sola lettura in Control Room
  → AppVersion: tabella popolata MANUALMENTE dall'operatore, non collegata a build reali
```

**Stato**: PARZIALE — il nucleo (identità, asset, manifest, distribuzione beta) è completo e reale; i gap sono nella parte "fisica" finale (signing, versioning APK, test end-to-end) già dettagliati in sezione 8.

---

## 11. Tenant Engine — pipeline completa (isolamento multi-tenant)

```
Richiesta in ingresso → risoluzione tenant da FONTE FIDATA (mai da parametro client):
  - X-Tenant-Key header → ResolveTenantFromKey → TenantRegistry::findByApiKey()
  - JWT claim "tid" firmato → JwtGuard::user() → cross-check contro user.tenant_id
  - utente autenticato in sessione (dashboard) → BindDashboardTenant
  - job in coda → TenantAwareJob (tenantId serializzato nel job)
→ CurrentTenant::set() — singleton scoped per richiesta/job (TenantContext immutabile, cache 300s)
→ TenantScope (global scope Eloquent, via trait BelongsToTenant su 21 modelli):
    ogni query filtrata WHERE tenant_id = current; creazione auto-compila tenant_id
→ Fail-closed: nessun contesto legato + nessun bypass esplicito → eccezione, MAI query non filtrata
→ Bypass esplicito (CurrentTenant::bypass(...)): unico modo sanzionato per query cross-tenant
    (usato in ProvisionTenant, ChangeTenantStatus, viste Control Room)
→ Mascheramento: probing UUID di altro tenant → sempre 404, mai 403
```

**Database**: unico, condiviso, riga-per-riga isolato — **NON** un database per tenant. Confermato: zero occorrenze di `DB::connection(` o switch di connessione per tenant in tutto `app/`.

**Test che verificano questo comportamento**: `TenantIsolationTest` (5 test), `WhiteLabelIsolationTest` (3 test), `DashboardSecurityTest` (3 test di isolamento su 7 totali) — tutti letti per intero, tutti verdi nell'ultima verifica disponibile.

**Limite noto**: `User` (autenticazione) non ha lo scope automatico — l'isolamento su questo modello dipende da convenzione manuale a ogni punto di chiamata, non da una garanzia strutturale. Il commento nel codice che dichiara un "test di architettura" a protezione di questo pattern è **falso** — quel test non esiste nel repository.

---

## 12. REAL PROJECT COMPLETION

> Le percentuali sotto sono una sintesi qualitativa basata sui fatti verificati nelle sezioni precedenti (non una misura meccanica/automatica — dichiarato esplicitamente per trasparenza). Ogni voce è ancorata a gap concreti elencati sopra, non a impressioni.

| Area | % | Motivazione sintetica (rimando a sezione) |
|---|---|---|
| Core Engine (tenancy + booking + auth) | **92%** | Meccanismo di isolamento e motore di prenotazione completi e testati (§11, §1); gap: `User` non scoped, "test di architettura" dichiarato ma assente |
| Backend (moduli applicativi) | **90%** | Tutti i moduli completi (§1); gap: envelope risposta non unificato, CORS aperto (§6) |
| Flutter | **85%** | Tutte le schermate reali, zero placeholder (§7.1); gap: analytics mai wired, config Firebase native assenti, font mancanti, guard di routing non testato (§2, §7.8) |
| Control Room | **88%** | Intero ciclo App Factory + gestione tenant esposti (§5); gap: nessuna azione termina/archivia, nessuna vista audit log |
| Build Engine | **75%** | Compila realmente con driver `local` (§8); gap: signing release non attivo di default, versionCode/Name statici, checksum solo su un driver, zero test e2e reali |
| White Label Engine | **90%** | Pipeline asset/manifest/runtime completa e testata (§9); gap: font personalizzate non incluse |
| Security | **80%** | JWT+MFA+isolamento dati solidi e testati; gap: CORS `*`, MFA opzionale per riga (non invariante), nessun layer `Policies`, nessun vincolo DB su `type`/`tenant_id` |
| Deployment | **55%** | Deploy funzionante e ripetibile (§2, `deploy.sh`); ma topologia dichiarata esplicitamente "pilota": 1 istanza EC2, 1 RDS micro, niente autoscaling/HA/CDN |
| Testing | **78%** | 45 test backend + 13 Flutter, buona copertura su tenancy/booking (§11); gap: build pipeline reale non testata e2e, diversi screen/core Flutter senza test dedicato (§7.8) |
| Infrastructure (Terraform/Infra-as-code) | **65%** | Terraform reale e curato per lo scope "pilota" (security group, cifratura, IMDSv2, SES); gap: nessuna ridondanza, nessun load balancer, storage locale non condiviso |

**Media semplice delle 10 aree**: ~80%.
