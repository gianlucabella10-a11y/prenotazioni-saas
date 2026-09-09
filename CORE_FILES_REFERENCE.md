# CORE_FILES_REFERENCE — I file che tengono in piedi il sistema

> Non ogni file del repository — solo quelli il cui malfunzionamento/rimozione avrebbe un impatto ampio o non ovvio. Per l'elenco completo per cartella, `PROJECT_MAP.md`/`FOLDER_RESPONSIBILITIES.md`.

## Backend — fondamenta

| File | Perché esiste | Chi lo usa | Quando viene eseguito | Se viene eliminato |
|---|---|---|---|---|
| `app/Foundation/Tenancy/TenantScope.php` | Applica il filtro `tenant_id` a ogni query di un modello tenant-scoped | Ogni modello con trait `BelongsToTenant` (21 modelli) | A ogni query Eloquent su quei modelli | **Catastrofico**: ogni query su ogni modello tenant-scoped smette di essere filtrata — fuga dati cross-tenant immediata e totale |
| `app/Foundation/Tenancy/CurrentTenant.php` | Contenitore del tenant risolto per la richiesta/job corrente | `TenantScope`, `BelongsToTenant`, ogni servizio che chiama `bypass()` | A ogni richiesta HTTP/job che tocca dati tenant-scoped | Il sistema non compila (dependency injection rotta ovunque) |
| `app/Foundation/Tenancy/BelongsToTenant.php` | Trait che applica `TenantScope` + auto-fill `tenant_id` | 21 modelli | Al boot di ogni modello che lo usa | Isolamento multi-tenant sparisce silenziosamente su tutti quei modelli |
| `app/Foundation/Auth/JwtGuard.php` | Guard custom per l'autenticazione API (non Sanctum) | `config/auth.php` (guard `api`), ogni route `auth:api` | A ogni richiesta autenticata dell'app mobile | L'intera API cliente smette di autenticare — l'app mobile non funziona più |
| `app/Foundation/Http/ApiErrorResponse.php` | Forma unica delle risposte di errore JSON | `bootstrap/app.php` (renderer eccezioni) | A ogni eccezione gestita su richieste `api/*` | Gli errori tornano nel formato grezzo di Laravel — l'app mobile (che si aspetta `{"error":{code,message}}`) smette di mostrare messaggi corretti |
| `bootstrap/app.php` | Punto di composizione dell'intera applicazione (routing, middleware, exception handling) | Laravel stesso, a ogni bootstrap | Ogni richiesta | Il backend non si avvia |
| `app/Modules/TenantManagement/Application/ProvisionTenant.php` | Unico punto di creazione di un nuovo cliente | `TenantsController` (Control Room), `AdminTenantController` (API) | Ogni "Nuovo cliente" | Impossibile onboardare nuovi clienti da nessuna interfaccia |
| `app/Modules/AppFactory/Application/Dispatchers/LocalBuildDispatcher.php` | Unico driver che compila realmente `flutter build apk` | `RunAppBuildJob`, se `build_driver=local` | A ogni build lanciata con questo driver | Le build col driver `local` falliscono; `manual`/`github` restano disponibili |
| `app/Modules/AppFactory/Application/GenerateAppPackage.php` | Scrive il manifest JSON che guida ogni build | `PrepareApp`, comando `app:generate` | A ogni "Genera pacchetto" | Nessuna build può più partire (`BuildService` richiede il manifest) |
| `app/Modules/Scheduling/Application/BookAppointment.php` | Unico punto di creazione di una prenotazione, con lock di concorrenza | `AppointmentController::store` | A ogni prenotazione | I clienti finali non possono più prenotare |
| `app/Modules/Scheduling/Domain/AvailabilityCalculator.php` | Calcolo puro degli slot disponibili (fusi orari, buffer, eccezioni) | `GetAvailability`, `BookAppointment` | A ogni richiesta di disponibilità o prenotazione | Nessuno slot disponibile calcolabile — booking bloccato |
| `app/Console/Commands/CreateControlRoomAdmin.php` | Unico modo per creare il **primo** super-admin | Operatore, una tantum per ambiente, da terminale | Solo al bootstrap di un nuovo ambiente | Se l'ambiente non ha già un super-admin, nessuno può più accedere alla Control Room |
| `database/database.sqlite` (locale) / RDS (produzione) | Il database stesso | Tutto il backend | Sempre | Perdita totale dei dati — unica rete di sicurezza è il backup (`OWNER_GUIDE.md` SOP-07) |
| `config/app_factory.php` | Convenzioni App Factory (bundle prefix, disco, driver, timeout) | `AllocateAppIdentifiers`, `LocalBuildDispatcher`, `GenerateAppPackage` | A ogni operazione App Factory | Valori di default Laravel/`env()` vuoti — l'intera pipeline si comporta in modo indefinito |

## Flutter — fondamenta

| File | Perché esiste | Chi lo usa | Quando viene eseguito | Se viene eliminato |
|---|---|---|---|---|
| `lib/main.dart` | Bootstrap dell'app (valida `TENANT_KEY`, inizializza Sentry, avvia `ProviderScope`) | Punto di ingresso Flutter | All'avvio dell'app | L'app non parte |
| `lib/app/router.dart` | Unico guard di navigazione (auth, verifica email, stato tenant) | `main.dart`/`app.dart` | A ogni cambio di route | Nessuna protezione di navigazione — un utente non autenticato potrebbe raggiungere schermate protette |
| `lib/core/network/api_client.dart` | Unico client HTTP, gestisce header tenant/auth e refresh token | Ogni repository (`features/*/data/`) | A ogni chiamata API | L'app non comunica più col backend — completamente inutilizzabile |
| `lib/core/env/app_environment.dart` | Legge `API_BASE_URL`/`TENANT_KEY` dai dart-define | `main.dart`, `api_client.dart` | All'avvio | L'app non sa a quale backend/tenant appartiene — fallisce immediatamente (per design, vedi `main.dart`) |
| `lib/core/storage/token_storage.dart` | Persistenza sicura dei token (Keychain/Keystore) | `ApiClient`, `SessionController` | A ogni login/refresh/logout | L'utente non resta autenticato tra un avvio e l'altro dell'app |
| `lib/core/session/session_controller.dart` | Stato di autenticazione (guest/autenticato/verificato) | `app/router.dart` (guard), schermate auth | A ogni cambio di stato utente | Il router non sa più chi è l'utente — comportamento di navigazione indefinito |
| `lib/features/white_label/data/white_label_repository.dart` | Fetch/cache della configurazione brand (`GET /app/config`) | `whiteLabelConfigProvider` | A ogni avvio app + refresh manuale | L'app non riceve più branding — mostra solo il fallback hardcoded |
| `lib/features/white_label/domain/app_theme_builder.dart` | Costruisce il `ThemeData` dal config white-label | `app/app.dart` | A ogni rebuild del widget root | L'app perde ogni personalizzazione visiva — non più "white label" |

## Infrastruttura

| File | Perché esiste | Chi lo usa | Quando viene eseguito | Se viene eliminato |
|---|---|---|---|---|
| `platform-infra/bin/deploy.sh` | Unico script di deploy verso produzione | Developer, manualmente | Ogni release backend | Il deploy diventa una procedura manuale non ripetibile — rischio di errore umano alto |
| `platform-infra/bin/backup-control-center.sh` | Unico script di backup | Operatore, manualmente (`OWNER_GUIDE.md` SOP-07) | Dovrebbe essere quotidiano, oggi manuale | Nessuna rete di sicurezza contro perdita dati |
| `.github/workflows/ci.yml` | Unico gate di qualità automatico (test backend+mobile) | GitHub Actions, a ogni push/PR | Ogni push/PR | Nessuna verifica automatica prima del merge — regressioni non rilevate fino alla produzione |
| `.gitignore` (root + per-pacchetto) | Impedisce di committare segreti/build artifact | Git, a ogni commit | Ogni `git add`/`git status` | Rischio concreto di committare `.env`, keystore, `vendor/`, `node_modules/` — già verificato pulito oggi, ma senza questo file resterebbe pulito solo per caso |
