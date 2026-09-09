# PROJECT_MAP — Mappa completa del repository

Per ogni cartella: **contiene** / **chi la usa** (consumer umano o sistema) / **chi la richiama** (codice che la invoca) / **dipende da** / **input** / **output**. Ownership (chi può/non deve modificare) nella tabella dedicata sotto. Albero completo alla fine.

## Ownership — chi può modificare cosa

| Cartella | Chi PUÒ modificarla | Chi NON deve modificarla |
|---|---|---|
| `platform-backend/app/Foundation/Tenancy/` | Solo chi ha piena comprensione del modello di isolamento (impatto su tutti i moduli) — modifiche qui richiedono revisione da un secondo sviluppatore senior | Chiunque stia lavorando a una feature di un singolo modulo — un cambiamento qui non è mai "locale" |
| `platform-backend/app/Modules/<Nome>/` | Lo sviluppatore che possiede quel bounded context | Sviluppatori di altri moduli — mai importare `Infrastructure` di un modulo che non si possiede (vedi `PROJECT_STANDARD.md` §3) |
| `platform-backend/database/migrations/` | Chiunque aggiunga un campo/tabella — solo file **nuovi**, mai modificare una migration già eseguita in produzione | Chiunque, se la migration è già stata deployata — va scritta una migration correttiva, non un edit retroattivo |
| `platform-backend/config/` | Sviluppatori backend con consapevolezza dell'impatto (specialmente `database.php`, `auth.php`, `app_factory.php`) | Chi non ha verificato l'uso di quella chiave in tutto il codice (vedi `PROJECT_STANDARDIZATION_AUDIT.md` — nessuna chiave duplicata da preservare) |
| `platform-mobile/apps/client_app/lib/core/` | Sviluppatori mobile senior — è codice trasversale a tutte le feature | Chi sta implementando una singola feature — se serve un cambiamento qui, va discusso, non fatto in corsa |
| `platform-mobile/apps/client_app/lib/features/<nome>/` | Lo sviluppatore che possiede quella feature | Sviluppatori di altre feature (nessun accoppiamento orizzontale trovato oggi — va mantenuto così) |
| `platform-infra/` | Solo chi ha accesso a segreti/credenziali di produzione | Chiunque altro — tocca infrastruttura e chiavi di firma live (vedi `SYSTEM_BOUNDARIES.md`) |
| `.github/workflows/` | Chi possiede la pipeline CI/CD, con consapevolezza dei segreti coinvolti | Chiunque non abbia verificato l'impatto su build/deploy in corso |
| `docs/archive/` | Nessuno in condizioni normali — è storico, va solo aggiunto, mai riscritto | Chiunque voglia "correggere" un documento storico — se un file archiviato è fuorviante, si aggiunge una nota (come già fatto per `SIGNED_APK_READY.md`), non si riscrive la cronologia |
| `docs/<Categoria>/` (attivi) | Chi possiede l'argomento — con obbligo di aggiornare in-place, mai duplicare (`PROJECT_STANDARD.md` §4) | — |
| File canonici alla radice (`REAL_PROJECT_STATE.md`, ecc.) | Solo tramite una nuova verifica sul codice — mai per intenzione o senza rileggere lo stato attuale | Chiunque stia per copiare/incollare uno stato precedente senza riverificarlo |

## Radice

| Cartella/file | Contiene | Chi la usa | Dipende da |
|---|---|---|---|
| `platform-backend/` | API, dashboard, Control Room, App Factory (Laravel) | Sviluppatori backend, CI, `platform-infra/deploy.sh` | — |
| `platform-mobile/apps/client_app/` | App Flutter cliente unica | Sviluppatori mobile, CI, `LocalBuildDispatcher` (backend) | Chiama il backend via HTTP a runtime |
| `platform-infra/` | Terraform, script deploy, runbook | Chi gestisce l'infrastruttura | `platform-backend` (unico target di `deploy.sh`) |
| `docs/` | Documentazione ufficiale per categoria | Tutti | — |
| `.github/workflows/` | CI + pipeline build per-tenant | GitHub Actions (push/PR/dispatch) | SSH verso backend di produzione (per App Factory) |
| `README.md`, `PROJECT_INDEX.md`, `DEVELOPER_GUIDE.md`, `PROJECT_MAP.md`, `DEPENDENCY_GRAPH.md`, `BUSINESS_FLOW.md`, `OPERATION_MANUAL.md`, `PROJECT_STRUCTURE_AUDIT.md`, `REAL_PROJECT_STATE.md`, `PROJECT_FREEZE_STATE.md`, `PROJECT_STRUCTURE.md`, `PROJECT_CLEANUP_PLAN.md`, `PROJECT_DEPENDENCIES.md`, `PROJECT_STANDARD.md`, `PROJECT_EVOLUTION_ROADMAP.md` | Documenti canonici (unici file `.md` ammessi alla radice) | Chiunque entri nel repository | Si referenziano a vicenda, mai duplicati |

## `platform-backend/app/`

| Cartella | Contiene | Input | Output | Chi la richiama |
|---|---|---|---|---|
| `Foundation/Tenancy/` | Risoluzione tenant, scope globale, contesto per-richiesta | Header `X-Tenant-Key`, claim JWT `tid`, sessione utente, `tenantId` di job | `CurrentTenant` bound al contenitore per la durata della richiesta/job | Ogni modulo con modelli `tenant_id` (via `BelongsToTenant`), tutti i middleware di risoluzione tenant |
| `Foundation/Auth/` | Guard JWT custom, MFA TOTP, verifica email, refresh token | Credenziali, token bearer | Sessione autenticata, JWT firmato | `routes/api.php` (guard `api`), `AuthController` |
| `Foundation/Audit/` | `AuditLogger` — scrittura diretta su `audit_logs` | Azione + attore + payload | Riga in `audit_logs` (fail-silent) | ~20 punti nei moduli (provisioning, build, brand, auth) |
| `Foundation/Http/` | Middleware condivisi, envelope errori | Eccezioni tipizzate | JSON `{"error":{code,message,details?}}` per richieste `api/*` | `bootstrap/app.php` (`withExceptions`) |
| `Modules/TenantManagement/` | Ciclo di vita tenant, piani, quote, inviti | Form Control Room / API admin | `Tenant`, `Subscription`, invito | `ControlRoom`, API `/admin/tenants` |
| `Modules/Scheduling/` | Disponibilità, prenotazione, stati appuntamento | Richiesta di booking (servizio+staff+slot) | `Appointment` persistito, eventi di dominio | API cliente (`/appointments`), `ManageAgendaController` (Dashboard) |
| `Modules/Catalog/` | Servizi, varianti, categorie, location | CRUD da Dashboard/API manage | Dati di catalogo consumati da `Scheduling` | `Scheduling`, `Dashboard`, API pubblica `/catalog/services` |
| `Modules/Staff/` | Anagrafica operatori, orari | CRUD da Dashboard | Dati staff consumati da `Scheduling` | `Scheduling`, `Dashboard` |
| `Modules/Customers/` | Profilo/consensi/cancellazione cliente finale | Richieste autenticate `/me/*` | Dati profilo, consensi | API cliente |
| `Modules/Branding/` | Asset e tema white-label | Upload logo, colori | 14 varianti immagine generate, manifest branding | `AppFactory`, `Dashboard`, `ControlRoom` |
| `Modules/Notifications/` | Invio multi-canale (email/FCM) | Eventi di dominio (booking creato/cancellato), sweep schedulato | Notifica inviata o loggata come fallita | Eventi da `Scheduling`, `DispatchDueNotifications` (comando schedulato) |
| `Modules/AppFactory/` | Identità app, manifest, build, distribuzione beta | Tenant provisionato + brand | Manifest JSON, APK, link beta | `ControlRoom` (route montate direttamente), CI via SSH |
| `Modules/ControlRoom/` | Pannello super-admin | Azioni operatore | Redirect/view — nessuna API JSON | Browser (sessione `admin`) |
| `Modules/Dashboard/` | Pannello titolare/staff (web) | Azioni titolare/staff | Redirect/view | Browser (sessione `web`) |

## `platform-backend/` (altre cartelle)

| Cartella | Contiene | Input | Output |
|---|---|---|---|
| `database/migrations/` | 23 file, schema completo | — | Schema DB (SQLite dev / MySQL produzione) |
| `database/seeders/` | `DatabaseSeeder`, `PlanSeeder` | — | Dati iniziali (piani commerciali) |
| `routes/` | `api.php` (`/api/v1`), `web.php` (dashboard+control-room), `console.php` (scheduler) | Richieste HTTP | Dispatch a controller |
| `config/` | 17 file, uno per dominio (`app_factory.php`, `booking.php`, `jwt.php`, ecc.) | Variabili d'ambiente | Configurazione risolta a runtime |
| `tests/` | 45 file (`Unit/`, `Feature/`) | Scenari di test | Verde/rosso in CI |
| `storage/app/private/builds/{tenant_id}/{version}/` | Artefatti APK prodotti | — | Consumato da `BetaDownloadController` |

## `platform-mobile/apps/client_app/lib/`

| Cartella | Contiene | Input | Output | Chi la richiama |
|---|---|---|---|---|
| `app/` | Bootstrap, provider graph, router | Config letta da `core/env` | Widget tree renderizzato | `main.dart` |
| `core/network/` | `ApiClient` (Dio) | Chiamate dei repository | Risposta HTTP normalizzata o `ApiFailure` | Ogni repository in `features/*/data/` |
| `core/session/` | Stato sessione (guest/autenticato/verificato) | Login/logout/token scaduto | Stato consumato dal router (guard) | `app/router.dart` |
| `core/storage/` | Token sicuri (Keychain/Keystore) | Token da persistere | Token letti al bootstrap | `ApiClient`, `SessionController` |
| `core/push/` | Lifecycle FCM | Init app, permessi utente | Token FCM registrato sul backend | `app/app.dart` (init asincrono) |
| `features/<nome>/data/` | Repository (chiamate API) | Richiesta di dominio | DTO/modello di dominio | `presentation/` della stessa feature |
| `features/<nome>/presentation/` | Schermate | Stato dei provider | UI renderizzata | Router |

## `platform-infra/`

| Cartella | Contiene | Input | Output |
|---|---|---|---|
| `terraform/pilot/` | 1 EC2, 1 RDS MySQL, S3, SES | Variabili Terraform | Infrastruttura AWS provisionata |
| `bin/deploy.sh` | Script di deploy | Codice `platform-backend/` | Backend aggiornato sull'istanza |
| `bin/backup-control-center.sh` | Script di backup | Stato locale (DB+storage) | Archivio di backup |
| `runbooks/DEPLOY_PILOT.md` | Procedura di deploy manuale | — | — |

## Albero completo (livelli principali)

```
/
├── README.md, PROJECT_INDEX.md, DEVELOPER_GUIDE.md, PROJECT_MAP.md,
│   DEPENDENCY_GRAPH.md, BUSINESS_FLOW.md, OPERATION_MANUAL.md,
│   PROJECT_STRUCTURE_AUDIT.md, REAL_PROJECT_STATE.md, PROJECT_FREEZE_STATE.md,
│   PROJECT_STRUCTURE.md, PROJECT_CLEANUP_PLAN.md, PROJECT_DEPENDENCIES.md,
│   PROJECT_STANDARD.md, PROJECT_EVOLUTION_ROADMAP.md
├── START_CONTROL_CENTER.command, stop-control-center.sh
├── docs/
│   ├── 00-indice.md
│   ├── Architecture/   (18-19, 21-33, ARCHITECTURE_FINAL_REVIEW)
│   ├── Business/       (01-17, 20, TECH_STATUS_REPORT, PRODUCT_DESIGN_ROADMAP, GIUFFRIDA_FEATURE_GAP)
│   ├── Backend/        (34-code-review-backend)
│   ├── Flutter/        (FIREBASE_CONFIGURATION_REQUIRED)
│   ├── AppFactory/     (APP_FACTORY_RELEASE_SECRETS)
│   ├── ControlRoom/    (CONTROL_ROOM_OPERATOR_GUIDE)
│   ├── WhiteLabel/
│   ├── Deployment/     (ENVIRONMENT_GUIDE, BUILD_MACHINE_SETUP, SIGNING_SETUP, DEPLOYMENT_READY, RELEASE_PROCESS)
│   ├── Testing/        (REAL_DEVICE_TEST)
│   ├── Operations/     (PREVIEW_ACCESS_GUIDE, BACKUP_RECOVERY_GUIDE)
│   ├── Runbooks/       (APP_PROVISIONING_RUNBOOK, BETA_TESTING_GUIDE, BETA_DEBUG_RUNBOOK)
│   ├── API/             (25-api-rest)
│   ├── Release/         (BETA_RELEASE, FINAL_RELEASE_DECISION, MVP_CLIENT_RELEASE_CHECKLIST, APP_STORE_READINESS)
│   └── archive/         (8 sotto-cartelle, ~70 file storici — vedi docs/archive/README.md)
├── platform-backend/
│   ├── app/{Foundation/, Modules/, Http/, Models/, Console/, Providers/}
│   ├── database/{migrations/, seeders/, factories/}
│   ├── routes/{api.php, web.php, console.php}
│   ├── config/
│   ├── tests/{Unit/, Feature/}
│   └── resources/views/{dashboard/, control_room/}
├── platform-mobile/apps/client_app/
│   ├── lib/{app/, core/, features/}
│   ├── android/, ios/, web/
│   └── test/{unit/, widget/, e2e/}
├── platform-infra/
│   ├── terraform/pilot/
│   ├── bin/{deploy.sh, backup-control-center.sh}
│   └── runbooks/
└── .github/workflows/{ci.yml, app-factory-build.yml, app-factory-batch.yml}
```
