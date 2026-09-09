# PROJECT_DEPENDENCIES — Diagramma di dipendenza (verificato da codice)

> Costruito dai fatti verificati in `REAL_PROJECT_STATE.md` e `PROJECT_FREEZE_STATE.md` più i file letti in questa sessione (`docs/22-struttura-repository.md`, elenco moduli). Nessuna modifica al codice.

## 1. Dipendenze tra moduli backend (Laravel)

```
                              ┌─────────────────────┐
                              │  Foundation/Tenancy   │  ← usato da TUTTI i moduli tenant-scoped
                              │  (CurrentTenant,      │     (scope globale + contesto per-richiesta)
                              │   TenantScope, ecc.)  │
                              └──────────┬───────────┘
                                         │
              ┌──────────────┬──────────┼──────────┬──────────────┬───────────────┐
              │              │          │          │              │               │
      ┌───────▼──────┐ ┌────▼─────┐ ┌──▼─────┐ ┌──▼──────┐ ┌─────▼──────┐ ┌──────▼───────┐
      │TenantManagement│ │ Catalog  │ │ Staff  │ │Customers│ │  Branding  │ │Notifications │
      └───────┬────────┘ └────┬─────┘ └───┬────┘ └─────────┘ └─────┬──────┘ └──────┬───────┘
              │               │           │                       │               │
              │          ┌────▼───────────▼────┐                  │        (eventi da Scheduling)
              │          │     Scheduling        │                  │               │
              │          │ (BookAppointment,      │                  │               │
              │          │  AvailabilityCalc.)    │                  │               │
              │          └───────────┬────────────┘                  │               │
              │                      │                                │               │
              └──────────┬───────────┴────────────┬──────────────────┘               │
                         │                        │                                   │
                 ┌───────▼────────┐       ┌───────▼────────┐                          │
                 │   AppFactory    │       │   Dashboard     │◄─────────────────────────┘
                 │ (identità app,  │       │ (web, sessione, │  (nessuna dipendenza inversa:
                 │  manifest,      │       │  titolare/staff)│   Dashboard/ControlRoom sono
                 │  build, beta)   │       └─────────────────┘   "foglie" — nessun altro modulo
                 └────────┬────────┘                             le importa)
                          │
                 ┌────────▼────────┐
                 │   ControlRoom    │
                 │ (super-admin,    │
                 │  usa AppFactory  │
                 │  via le sue      │
                 │  route/controller)│
                 └──────────────────┘

Foundation/Auth   → consumato da: routes/api.php (guard "api"), Dashboard (guard "web"), ControlRoom (guard "admin")
Foundation/Audit  → scritto da: TenantManagement, Branding, ControlRoom, AppFactory, Foundation/Auth (~20 punti totali)
Foundation/Http   → consumato globalmente: middleware condivisi, rendering eccezioni in bootstrap/app.php
```

**Regola di dipendenza dichiarata in `docs/22`** (§2): *"i moduli comunicano tramite eventi di dominio o interfacce pubbliche (Application layer); vietato importare Infrastructure di un altro modulo. Verificato in CI con regole di architettura (test di dipendenza)."*

**Verifica di questa regola nel codice reale**: **il test di architettura che dovrebbe imporla NON esiste** (confermato in `REAL_PROJECT_STATE.md` §Fase 8 — nessuna cartella `tests/Architecture`). Dai controller letti in questa e nelle sessioni precedenti (`TenantsController`, `TenantBrandController`, `AppProjectController`), la regola risulta **rispettata di fatto** — i moduli si richiamano a vicenda solo tramite classi `Application/` (es. `ControlRoom` chiama `ProvisionTenant`, `ChangeTenantStatus`, `StoreBrandLogo` — tutte in namespace `Application`) — ma questo è **osservato empiricamente su un campione di controller**, non garantito strutturalmente. Nulla impedisce oggi, a livello di CI o linter, che un futuro commit importi direttamente `App\Modules\Scheduling\Infrastructure\Models\Appointment` da dentro `App\Modules\Dashboard`.

**Moduli "foglia" (nessuno dipende da loro)**: `ControlRoom`, `Dashboard` — corretto, sono superfici di presentazione, non dovrebbero mai essere dipendenze di altri moduli.

**Modulo più connesso**: `AppFactory` — dipende da `TenantManagement` (legge `Tenant`), `Branding` (asset), ed è a sua volta consumato da `ControlRoom` e dalla CI esterna. È anche l'unico modulo con una dipendenza **fuori dal backend** (vedi sezione 3).

---

## 2. Dipendenze Flutter (interne)

```
app/  (bootstrap, router, provider graph)
  │
  ├─→ core/network (ApiClient)  ──→ core/storage (TokenStorage)
  ├─→ core/session (SessionController)  ──→ core/network, features/auth, features/profile
  ├─→ core/push (PushNotificationService)  ──→ features/profile (registrazione device)
  ├─→ core/analytics (AnalyticsService)  ──→ NESSUNO (mai istanziato — nodo isolato, confermato in PROJECT_FREEZE_STATE.md §3)
  │
  └─→ features/{auth,booking,business,catalog,home,profile,appointments,white_label}
        │  ognuna dipende da: core/network (via repository), core/session (per il guard di routing)
        └─→ features/booking dipende anche da features/catalog (selezione servizi)
```

Nessuna feature Flutter importa un'altra feature direttamente (nessun accoppiamento orizzontale trovato tra `features/*`) — l'unica eccezione è `booking` → `catalog` (necessaria: il flusso di prenotazione seleziona servizi dal catalogo). Questo è un grafo di dipendenza pulito.

---

## 3. Dipendenze tra i tre "repository" (in realtà sottocartelle di un unico repo Git)

```
platform-backend  ◄────────────────────────────┐
      │                                          │
      │ (1) HTTP a runtime, via:                 │ (3) filesystem, SOLO se
      │     GET /api/v1/app/config               │     APP_FACTORY_BUILD_DRIVER=local:
      │     GET/POST /api/v1/* (booking, auth)    │     Process("flutter build apk", cwd=…)
      ▼                                          │     legge/scrive dentro:
platform-mobile/apps/client_app  ─── (2) build-time: Gradle property -PAPP_ID,
      │                                    --dart-define (iniettati DA platform-backend
      │                                    quando lancia flutter build)
      │
      └── (nessuna dipendenza di codice/import diretta verso platform-backend —
           comunica SOLO via HTTP e via CLI flag a build-time)

platform-infra
      │
      └── (4) deploy.sh fa rsync+SSH SOLO verso platform-backend
           (nessun collegamento a platform-mobile — il deploy dell'app mobile
            avviene interamente fuori da platform-infra, via CI o build locale)
```

**Le 4 dipendenze cross-cartella, verificate nel codice**:

1. **Runtime HTTP** (accoppiamento debole, corretto): l'app Flutter installata chiama l'API REST via `ApiClient`/`API_BASE_URL`. Nessun problema — è la relazione client/server prevista.
2. **Build-time, iniezione config** (accoppiamento debole, corretto): il backend passa `-PAPP_ID`/`-PAPP_NAME`/`--dart-define` come argomenti CLI a `flutter build` — non tocca file sorgente Flutter.
3. **Filesystem, driver `local`** (**accoppiamento forte, da segnalare**): `LocalBuildDispatcher` (backend) risolve `APP_FACTORY_FLUTTER_APP_DIR` di default a `base_path('../platform-mobile/apps/client_app')` — cioè **assume che le due cartelle vivano fianco a fianco sullo stesso filesystem**, ed esegue processi reali (`flutter clean/pub get/analyze/test/build`) direttamente dentro l'albero Flutter. Questo è l'unico punto di tutto il repository dove `platform-backend` non solo dipende da `platform-mobile`, ma lo **esegue** come sotto-processo. È l'esatta ragione per cui il piano originale a 3 repository separati (`docs/22`) non può funzionare senza modifiche se il driver `local` resta in uso: se `platform-backend` e `platform-mobile` diventassero due repository Git realmente separati (clonati in posizioni indipendenti, magari su macchine diverse), questo path relativo si romperebbe e il driver `local` smetterebbe di funzionare — bisognerebbe passare esclusivamente al driver `github` (che non ha questo problema, delega tutto a GitHub Actions).
4. **Deploy** (accoppiamento debole, corretto): `platform-infra/bin/deploy.sh` tocca solo `platform-backend`; il deploy/distribuzione di `platform-mobile` passa interamente per un canale diverso (CI o build locale + beta download token), mai per `platform-infra`.

---

## 4. Dipendenze CI (`.github/workflows/`)

```
ci.yml
  ├─→ platform-backend: composer install, pint, artisan test   (nessuna dipendenza da platform-mobile)
  └─→ platform-mobile/apps/client_app: flutter pub get, analyze, test   (nessuna dipendenza da platform-backend)
      (i due job del workflow "ci" girano in PARALLELO e sono indipendenti)

app-factory-build.yml  (per-tenant, workflow_dispatch o workflow_call)
  job "prepare"  → SSH verso il backend di produzione (secrets BACKEND_SSH_KEY/HOST/USER/PATH)
                 → esegue `php artisan app:generate {tenant}` SUL BACKEND REMOTO
                 → scarica (scp/rsync) manifest + asset brand dal backend
  job "android"  → usa l'artifact di "prepare" + platform-mobile/apps/client_app (checkout)
                 → build reale (tool/app_factory/make_app.sh), firma con keystore da secret
                 → pubblica (Firebase App Distribution / Google Play, condizionale)
                 → richiama di nuovo SSH → `php artisan app:build-record` SUL BACKEND REMOTO
  job "ios"      → analogo, su runner macOS

app-factory-batch.yml
  job "matrix" → SSH → `php artisan app:build-matrix` sul backend → produce lista tenant
  job "build"  → richiama app-factory-build.yml una volta per tenant (max 3 in parallelo)
```

**Osservazione**: `app-factory-build.yml` e `app-factory-batch.yml` dipendono da un **backend di produzione raggiungibile via SSH** con segreti dedicati (`BACKEND_SSH_KEY/HOST/USER/PATH`) — questa è una dipendenza operativa forte tra CI e infrastruttura live, distinta e più fragile della dipendenza puramente di codice: se il backend di produzione è giù, l'intera pipeline di build white-label si blocca (non è testabile/eseguibile offline o contro un ambiente di staging isolato, perché il flusso richiama sempre lo stesso host configurato nei secret).

---

## 5. Dipendenze Control Room → resto del sistema

```
ControlRoom (routes/web.php, prefix /control-room)
  ├─→ TenantManagement (Application): ProvisionTenant, ChangeTenantStatus, IssueTenantInvite
  ├─→ Branding (Application): StoreBrandLogo, GenerateBrandAssets (indiretto via TenantBrandController)
  ├─→ AppFactory: le route /control-room/apps/* richiamano DIRETTAMENTE
  │     App\Modules\AppFactory\Http\Controllers\AppProjectController
  │     (il controller vive fisicamente nel modulo AppFactory, non in ControlRoom —
  │      ControlRoom lo "prende in prestito" via routing, non lo duplica)
  ├─→ Foundation/Tenancy: CurrentTenant::bypass() in OGNI azione (il super-admin opera
  │     sempre fuori dal contesto di un singolo tenant)
  └─→ Foundation/Audit: AuditLogger su quasi ogni azione di scrittura
```

Questo è l'unico punto del sistema dove un modulo di presentazione (`ControlRoom`) monta le route di un altro modulo (`AppFactory`) direttamente nel proprio spazio URL (`/control-room/apps/*` → `AppProjectController`) invece di avere un proprio controller che fa da proxy. Architetturalmente accettabile (evita duplicazione), ma rende `ControlRoom` un modulo "senza confini propri" per quella porzione di funzionalità — chi legge `routes/web.php` deve sapere che una parte delle route sotto `/control-room` non è implementata dentro `app/Modules/ControlRoom/`.

---

## 6. Accoppiamenti eccessivi — riepilogo

| # | Accoppiamento | Severità | Dove |
|---|---|---|---|
| 1 | `LocalBuildDispatcher` assume `platform-mobile` come sibling-directory sullo stesso filesystem del backend | **Alta** — rompe l'assunzione dei 3-repository di `docs/22`; funziona solo perché oggi è un mono-repo | §3.3 |
| 2 | Nessun test di architettura impone i confini tra moduli dichiarati in `docs/22` | **Media** — rispettati de facto, non garantiti | §1 |
| 3 | CI (`app-factory-build.yml`/`batch.yml`) dipende da un singolo backend di produzione raggiungibile via SSH, nessun ambiente di staging isolato per la pipeline di build | **Media** | §4 |
| 4 | `ControlRoom` monta controller di `AppFactory` direttamente nelle proprie route, senza layer proprio | **Bassa** — pragmatico, ma confonde i confini del modulo | §5 |
| 5 | `User` (Foundation, non un modulo) non ha lo scope di tenancy automatico — ogni chiamante deve saperlo e filtrare manualmente | **Media** — già segnalato in `REAL_PROJECT_STATE.md` §Fase 8, qui rilevante come dipendenza implicita "a memoria" tra ogni nuovo caller e la disciplina di chi ha scritto `JwtGuard`/`BindDashboardTenant` | §1 |

Nessuno di questi accoppiamenti causa oggi un bug conosciuto — sono rischi strutturali, non difetti attivi.

---

## 7. Scalabilità del repository e del processo (non dell'infrastruttura — già coperta in `REAL_PROJECT_STATE.md` §Fase 9)

Valutazione mirata a: 1000 clienti, 10.000 APK generati, 100 build concorrenti, 100 sviluppatori — dal punto di vista di come il *repository* e le sue dipendenze reggono, non del server.

**A 1000 clienti**: nessun collo di bottiglia strutturale nel repository stesso — il modello a modulo singolo per bounded context regge; il problema (già coperto altrove) è infrastrutturale (DB/istanza singola).

**A 10.000 APK generati**: l'accoppiamento §3.3 (`LocalBuildDispatcher` esegue `flutter build` come sottoprocesso sullo stesso host) diventa il primo vero collo di bottiglia *architetturale*, non solo di capacità — un solo processo Laravel che lancia build Flutter sincrone (anche se in coda) non è pensato per orchestrare migliaia di build: non esiste isolamento tra build di tenant diversi (stessa cartella `platform-mobile/apps/client_app`, stesso `build/` di output riusato in sequenza — nessuna copia/isolamento per build), quindi build concorrenti sullo stesso host **si pesterebbero i piedi** sulla stessa directory di output. Il driver `github` non ha questo limite (ogni build è un runner isolato), ma non è il driver di default (`manual`).

**A 100 build contemporanee**: confermato dal punto sopra — il driver `local` non è progettato per concorrenza reale (un solo `$appDir` condiviso, nessun lock/isolamento per-build trovato nel codice di `LocalBuildDispatcher`). Con il driver `github`, il limite diventa quello imposto da `app-factory-batch.yml` stesso: `max-parallel: 3` è **hardcoded** nel workflow — 100 build contemporanee richiederebbero prima di tutto di alzare questo numero (e i relativi limiti di concorrenza runner del piano GitHub Actions in uso).

**A 100 sviluppatori**: qui il collo di bottiglia è esattamente quello descritto in `PROJECT_STRUCTURE.md` §0 — **un solo repository Git, senza segregazione di accesso tra codice applicativo e infrastruttura/segreti** (`platform-infra/` contiene — o è pensato per contenere — credenziali/IaC che il piano originale in `docs/22` voleva isolare proprio per questo motivo, "permessi e audit separati"). Con 100 sviluppatori in un mono-repo: (a) chiunque ha accesso in lettura a `platform-infra/`, incluse le convenzioni di deploy e la topologia di produzione; (b) l'assenza di un test di architettura che imponga i confini tra moduli (§1) diventa più rischiosa linearmente col numero di persone che possono introdurre un accoppiamento non voluto; (c) un'unica cartella `docs/` con 77+ file non categorizzati (prima di questo audit) non scala oltre poche persone che già conoscono la cronologia a memoria — è esattamente il tipo di debito che con 100 sviluppatori diventa bloccante invece che solo scomodo.

**Collo di bottiglia principale per il repository stesso (non l'infra)**: l'assenza di segregazione multi-repository prevista ma mai realizzata, e la dipendenza filesystem del driver di build `local` — entrambi diventano limitanti esattamente nello scenario "scala": tanti sviluppatori, tante build parallele.
