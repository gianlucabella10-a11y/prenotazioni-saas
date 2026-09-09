# FOLDER_RESPONSIBILITIES — Responsabilità per cartella (livello sotto-modulo)

> Estende `PROJECT_MAP.md` (che copre cartelle di primo/secondo livello) scendendo dentro ogni modulo, ai 4 layer (`Domain/Application/Infrastructure/Http` o `Presentation`). Nessun contenuto duplicato dove `PROJECT_MAP.md` già risponde in modo identico — qui solo il livello che lì non c'era.

## Layer comuni a ogni modulo backend (`app/Modules/<Nome>/`)

| Layer | Scopo | Responsabilità | Dipendenze | Chi la usa | Chi NON dovrebbe usarla |
|---|---|---|---|---|---|
| `Domain/` | Regole di business pure | Enum di stato, value object, eccezioni di dominio, calcoli senza I/O (es. `AvailabilityCalculator`) | Nessuna dipendenza da `Infrastructure`/framework — deve restare testabile senza DB | `Application/` dello stesso modulo | Controller (mai direttamente) |
| `Application/` | Orchestrazione (use case) | Una classe = un'azione di business (`ProvisionTenant`, `BookAppointment`) — transazioni, chiamate a `Domain/`+`Infrastructure/` | `Domain/` e `Infrastructure/` dello **stesso** modulo, `Application/` di **altri** moduli (mai `Infrastructure/` di un altro modulo) | Controller, Console Commands, Job | Nessun altro modulo dovrebbe importarne `Infrastructure/` — solo `Application/` |
| `Infrastructure/Models/` | Persistenza | Eloquent Model, relazioni, scope, cast | Database, `Foundation/Tenancy` (via `BelongsToTenant`) | `Application/` dello stesso modulo | Altri moduli (mai — è la regola di confine più importante del repository, oggi rispettata per disciplina, non imposta da test — `PROJECT_STRUCTURE_AUDIT.md`) |
| `Http/Controllers/` o `Presentation/Controllers/` | Ingresso HTTP | Valida richiesta, chiama `Application/`, forma la risposta | `Application/` dello stesso modulo | Router (`routes/*.php`) | Altri controller (un controller non dovrebbe mai chiamarne un altro direttamente) |

## Cartelle specifiche per modulo (dove il pattern comune non basta)

| Cartella | Scopo | Dipendenze | Chi la usa | Chi NON dovrebbe usarla |
|---|---|---|---|---|
| `Modules/AppFactory/Application/Dispatchers/` | I tre driver di build (`Local`/`Github`/`Log`), intercambiabili dietro l'interfaccia `BuildDispatcher` | `LocalBuildDispatcher` dipende dal filesystem di `platform-mobile` (unico caso di dipendenza cross-cartella "repository", vedi `DEPENDENCY_GRAPH.md` §3.3) | `RunAppBuildJob` (risolve il driver da config, mai in modo diretto) | Controller — la scelta del driver è sempre indiretta via config |
| `Modules/ControlRoom/Http/Middleware/` | `EnsureSuperAdmin`, unico file | Guard `admin` | Route `/control-room/*` | Nessun'altra route — è specifico del guard super-admin |
| `Modules/Dashboard/Http/Middleware/` | `BindDashboardTenant`, `RequireOwner` | Guard `web`, `Foundation/Tenancy` | Route `/dashboard/*` | Route API o Control Room |
| `Modules/Scheduling/Application/Events/` | Eventi di dominio (`AppointmentBooked`, `AppointmentCancelled`) | `Domain/AppointmentStatus` | `Notifications` (per l'invio) | Non dovrebbero essere sollevati fuori dal ciclo di vita reale dell'appuntamento |
| `Modules/Scheduling/Presentation/Resources/` | `AppointmentResource` (unico `JsonResource` del repository) | Modello `Appointment` | `AppointmentController` | Altri controller — se serve un envelope simile altrove, va creata una risorsa propria, non riusata questa (vedi il gap sui 3 pattern di risposta, `TECHNICAL_DEBT.md` #7) |
| `Foundation/Tenancy/` | Isolamento multi-tenant, trasversale a tutto | Cache, Eloquent | **Tutti** i moduli con `tenant_id` | Nessuno dovrebbe reimplementare logica di scoping al di fuori di qui — se serve una nuova eccezione, va discussa, non duplicata |
| `Foundation/Auth/` | Guard JWT, MFA, refresh token | `firebase/php-jwt` | `routes/api.php` (guard `api`) | Dashboard/Control Room (usano i guard `web`/`admin`, non `api`) |

## Flutter — layer per feature (`lib/features/<nome>/`)

| Layer | Scopo | Dipendenze | Chi la usa | Chi NON dovrebbe usarla |
|---|---|---|---|---|
| `domain/` | Modelli di dominio puri (es. `Appointment`, `Availability`, `CatalogService`) | Nessuna dipendenza da `data/` o Flutter SDK dove possibile | `data/` e `presentation/` della stessa feature | Altre feature (nessun caso trovato oggi — va mantenuto così) |
| `data/` | Repository che chiamano `core/network` | `core/network/ApiClient`, `domain/` della stessa feature | `presentation/` della stessa feature, provider in `app/providers.dart` | Altre feature direttamente (l'unica eccezione nota è `booking` → `catalog`, necessaria e documentata) |
| `presentation/` | Schermate e widget privati | Provider Riverpod (`app/providers.dart` o locali) | Router (`app/router.dart`) | Altre schermate (nessuna schermata dovrebbe istanziare un'altra schermata direttamente, solo navigare via router) |

## Cartelle Flutter trasversali (`lib/core/`, `lib/app/`)

| Cartella | Scopo | Dipendenze | Chi la usa | Chi NON dovrebbe usarla |
|---|---|---|---|---|
| `core/network/` | `ApiClient` (Dio), unico punto di uscita HTTP | `core/storage`, `core/env` | Ogni `data/` repository | Le `presentation/` non dovrebbero chiamarlo direttamente, sempre tramite un repository |
| `core/session/` | Stato di autenticazione | `core/network`, `AuthRepository`, `MeRepository` | `app/router.dart` (guard), schermate auth/profilo | Feature che non toccano identità utente (es. `catalog` non dovrebbe leggere la sessione direttamente) |
| `core/analytics/` | `AnalyticsService` | Nessuna (isolato) | 🔴 **Nessuno oggi** — vedi `TECHNICAL_DEBT.md` #3, mai istanziato | — |
| `app/providers.dart` | Grafo di dependency injection Riverpod | Tutti i repository/servizi | Ogni schermata che consuma un provider condiviso | Non dovrebbe contenere logica di business, solo wiring |
| `app/router.dart` | Guard di navigazione unico | `whiteLabelConfigProvider`, `sessionControllerProvider` | `main.dart` | Le schermate non dovrebbero implementare redirect propri — tutta la logica di guard vive qui, in un unico posto |

## Regola di confine più importante (riassunto)

Per il backend: **`Infrastructure/` non si importa mai da fuori il proprio modulo.** Per Flutter: **`domain/`/`data/` di una feature non si importano da un'altra feature**, salvo l'eccezione documentata `booking→catalog`. Entrambe le regole sono rispettate oggi *de facto* ma non imposte da alcun test — vedi `PROJECT_STRUCTURE_AUDIT.md` e `DEAD_CODE_REPORT.md` per il dettaglio di cosa manca per renderle strutturali.
