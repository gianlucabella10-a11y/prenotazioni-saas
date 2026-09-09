# PROJECT_STRUCTURE — Struttura ideale del repository

> Documento di sola pianificazione. Nessun file è stato spostato, rinominato o eliminato. Fonte di verità funzionale: [REAL_PROJECT_STATE.md](REAL_PROJECT_STATE.md) (cosa esiste ed è verificato nel codice). Fonte di verità sulla visione originale: [docs/22-struttura-repository.md](docs/22-struttura-repository.md) (come il progetto era stato pianificato prima di essere costruito).

## 0. Premessa: due strutture, non una

Esiste già un documento di pianificazione — `docs/22-struttura-repository.md` — che descrive una struttura target scritta **prima** dell'implementazione. Il codice reale (verificato in `REAL_PROJECT_STATE.md`/`PROJECT_FREEZE_STATE.md`) ha divergito da quel piano in modo sostanziale. Questo documento non ripropone da zero una struttura ideale astratta: **riconcilia le due**, tenendo ciò che il codice reale ha già fatto bene, segnalando dove la pianificazione originale non è mai stata realizzata, e proponendo — solo per la documentazione, l'unica area dove riorganizzare senza toccare codice applicativo — una struttura ordinata.

**Scostamenti principali tra `docs/22` (piano) e la realtà (verificata)**:

| Piano (`docs/22`) | Realtà | Nota |
|---|---|---|
| 3 repository Git separati (`platform-backend`, `platform-mobile`, `platform-infra`) | **1 solo repository Git**, i tre sono sottocartelle | Il piano motivava la separazione con permessi/segreti (chiavi di firma non accessibili a tutto il team) — questo isolamento **non esiste** nella realtà attuale |
| Moduli backend: `TenantManagement, Billing, Branding, Catalog, Scheduling, Staff, Customers, Notifications, Reporting, BuildPipeline, Compliance` | Moduli reali: `TenantManagement, AppFactory, Branding, Catalog, ControlRoom, Customers, Dashboard, Notifications, Scheduling, Staff` | `Billing`, `Reporting`, `Compliance` **non sono mai stati costruiti**; `ControlRoom` e `Dashboard` non erano previsti come moduli a sé; `BuildPipeline` è diventato il più ampio `AppFactory` |
| `routes/api_v1.php`, `web_tenant.php`, `web_admin.php` (3 file separati) | `routes/api.php`, `routes/web.php` (dashboard **e** control-room nello stesso file), `routes/console.php` | Nessuna separazione fisica tra dashboard tenant e control-room super-admin a livello di file di route |
| `tests/TenantIsolation/` (suite dedicata, obbligatoria in CI) | `tests/Feature/TenantIsolationTest.php` (1 file dentro `Feature/`) | Il test esiste ed è verificato, ma non come suite isolata |
| `docker/` per sviluppo locale | **Assente** | Sviluppo locale via `php artisan serve` diretto, nessuna containerizzazione |
| Flutter: monorepo Dart con `melos.yaml`, `packages/{core_domain, api_client, design_system, white_label, notifications}`, `apps/{client_app, staff_app}` | Un solo `apps/client_app`, nessun `packages/`, nessun `melos.yaml`, nessuna `staff_app` (lo staff usa la Dashboard web, non un'app nativa) | Il pattern interno `features/<nome>/{domain,data,presentation}` **è stato rispettato** nonostante l'assenza del monorepo Dart |
| `platform-infra/{terraform/modules+envs/staging+production, whitelabel-pipeline/, runbooks/ (plurale), docs/ (ADR)}` | `platform-infra/{terraform/pilot/ (1 solo ambiente), runbooks/DEPLOY_PILOT.md (1 file), bin/, server/}` | Nessun ADR mai scritto; un solo ambiente "pilota", non staging+production separati; nessuna cartella `whitelabel-pipeline` (quella logica vive nel backend, modulo `AppFactory`) |
| Contratto OpenAPI come fonte di verità dell'API | **Assente** — nessun file OpenAPI/Swagger nel repository | Confermato in `REAL_PROJECT_STATE.md` |

Questo scostamento non è di per sé un errore — il progetto è cresciuto in una direzione pragmaticamente diversa e funzionante — ma **`docs/22` oggi descrive un sistema che non esiste**, ed è pericoloso lasciarlo intatto senza una nota che lo segnali: un nuovo sviluppatore che lo legge per primo si costruirà un modello mentale sbagliato del repository.

---

## 1. Struttura codice — CONFERMATA (nessuna modifica proposta)

Il codice applicativo (`platform-backend/app`, `platform-mobile/apps/client_app/lib`, `platform-infra/`) è già organizzato in modo coerente e non necessita di riorganizzazione strutturale — solo di essere documentato correttamente (vedi sezione 2). Albero confermato (sintesi; dettaglio completo per file in `PROJECT_FREEZE_STATE.md` §2):

```
platform-backend/                         PUBBLICA (repo del team backend)
├── app/
│   ├── Foundation/                       codice trasversale non di dominio
│   │   ├── Tenancy/                      risoluzione tenant, scope globale, contesto — usato da TUTTI i moduli tenant-scoped
│   │   ├── Auth/                         guard JWT, MFA, refresh token — usato da routes/api.php, ControlRoom, Dashboard
│   │   ├── Audit/                        audit logging — usato da ~20 punti nei moduli
│   │   ├── Http/                         middleware condivisi, envelope errori — usato da bootstrap/app.php
│   │   └── Enums/                        UserType
│   └── Modules/                          un modulo per bounded context
│       ├── TenantManagement/             ciclo di vita tenant, piani, quote — usato da ControlRoom, API admin
│       ├── Scheduling/                   dominio booking — usato da API cliente, Dashboard
│       ├── Catalog/                      servizi/varianti/location — usato da Scheduling, Dashboard
│       ├── Staff/                        anagrafica operatori — usato da Scheduling, Dashboard
│       ├── Customers/                    profilo/consensi cliente finale — usato da API cliente
│       ├── Branding/                     asset e tema white-label — usato da AppFactory, Dashboard, ControlRoom
│       ├── Notifications/                invio multi-canale — usato da Scheduling (eventi), scheduler
│       ├── AppFactory/                   identità app, manifest, build, beta — usato da ControlRoom, CI
│       ├── ControlRoom/                  pannello interno super-admin — consuma TenantManagement+AppFactory+Branding
│       └── Dashboard/                    pannello titolare/staff (web) — consuma Scheduling+Catalog+Staff+Branding
├── database/{migrations,seeders,factories}
├── routes/{api.php, web.php, console.php}
├── config/                               17 file di configurazione (uno per dominio: app_factory, booking, branding, jwt, ...)
├── tests/{Unit, Feature}                 45 file, organizzati per modulo dentro Feature/
├── bootstrap/app.php                     routing, middleware alias, exception rendering — punto di composizione dell'app
└── storage/, public/, resources/views/

platform-mobile/apps/client_app/          PUBBLICA (repo del team mobile) — un solo target, non un monorepo Dart
├── lib/
│   ├── app/                              bootstrap, provider graph, router — usato da main.dart
│   ├── core/{analytics,env,network,push,session,storage,utils}   servizi trasversali — usati da tutte le feature
│   └── features/{appointments,auth,booking,business,catalog,home,profile,white_label}/
│       └── {data,domain,presentation}/   pattern rispettato in modo coerente in ogni feature
├── android/, ios/, web/                  target di build nativi (parametrizzati a build-time da AppFactory)
└── test/{unit,widget,e2e}                13 file

platform-infra/                           INTERNA (accesso ristretto — tocca segreti/IaC, coerente con la motivazione originale di docs/22)
├── terraform/pilot/                      1 EC2 + 1 RDS + S3 + SES — un solo ambiente "pilota", non staging/production
├── bin/deploy.sh                         script di deploy via rsync+SSH
└── runbooks/DEPLOY_PILOT.md              1 runbook

.github/workflows/                        PUBBLICA (visibile al team, esegue contro segreti CI)
├── ci.yml                                test backend+mobile su ogni push/PR
├── app-factory-build.yml                 build per-tenant (Android/iOS), reusable workflow
└── app-factory-batch.yml                 build in batch su flotta tenant
```

**Nota sulla visibilità "pubblica/interna"**: nel repository attuale (mono-repo, un solo `.git`) questa distinzione è solo concettuale — chiunque abbia accesso al repository vede tutto, incluso `platform-infra/`. Questo è uno scostamento diretto dal piano originale (motivo dei 3 repository separati in `docs/22` §1). Non è un problema da risolvere in questa fase (richiederebbe uno split di repository, un cambiamento strutturale reale, fuori scope per un audit "solo documentazione"), ma va segnalato esplicitamente in `PROJECT_EVOLUTION_ROADMAP.md`.

---

## 2. Struttura documentazione — PROPOSTA (l'unica riorganizzazione concreta possibile senza toccare codice)

La documentazione è l'area dove esiste un problema reale e risolvibile solo con documentazione: **77 file markdown alla radice del repository**, nessun `README.md`, nessuna categorizzazione, il 25% mai nemmeno committato. La cartella `docs/` esistente (35 file, tutti aggiunti in un solo commit iniziale, mai più toccati) è invece già ordinata e numerata in modo sequenziale — va preservata come nucleo e usata come base per assorbire concettualmente il resto.

Struttura documentale target:

```
/README.md                                NUOVO — punto d'ingresso, oggi assente (vedi PROJECT_CLEANUP_PLAN.md)

docs/
├── 00-indice.md                          indice master — da aggiornare per riferire la struttura sotto
│
├── business/                             PUBBLICA (interna al team, non tecnica)
│   ├── 01-prd.md … 16-piano-crescita.md  (i 16 documenti di business esistenti, INVARIATI)
│   ├── PRODUCT_DESIGN_ROADMAP.md         (da root)
│   ├── GIUFFRIDA_FEATURE_GAP.md          (da root — analisi competitiva)
│   └── TECH_STATUS_REPORT.md             (da root)
│
├── architecture/                         PUBBLICA (team tecnico)
│   ├── 21-architettura-generale.md … 32-qualita-aws.md   (i 12 documenti tecnici esistenti, INVARIATI)
│   ├── tech/18-revisione-critica-fase1.md, tech/19-architettura-tecnica.md   (INVARIATI, la sotto-cartella resta — è coerente: sono i due documenti "di passaggio" tra business e tecnico)
│   └── ARCHITECTURE_FINAL_REVIEW.md      (da root)
│
├── audit/                                INTERNA (storico, sola lettura)
│   ├── 17-audit-revisione.md, 20-analisi-critica-fase1.md, 33-audit-tecnico.md, 34-code-review-backend.md   (INVARIATI)
│   ├── REAL_PROJECT_STATE.md, PROJECT_FREEZE_STATE.md   (restano referenziati da root come indice, vedi nota sotto)
│   └── history/                          i cluster di audit di sessione (vedi PROJECT_CLEANUP_PLAN.md §Cluster 3/4/6/7/8 — es. FINAL_BETA_AUDIT.md, PRODUCTION_GAP_ANALYSIS.md, ecc.)
│
├── developer/                            PUBBLICA (onboarding sviluppatori)
│   ├── ENVIRONMENT_GUIDE.md              (unico file superstite dopo merge del cluster ambiente — vedi cleanup plan)
│   ├── BUILD_MACHINE_SETUP.md, SIGNING_SETUP.md   (da root)
│   └── PREVIEW_ACCESS_GUIDE.md           (da root)
│
├── deployment/                           INTERNA
│   ├── RELEASE_PROCESS.md                (da root)
│   └── DEPLOYMENT_READY.md               (da root)
│
├── customer/                             PUBBLICA (operatore non tecnico / cliente)
│   ├── CONTROL_ROOM_OPERATOR_GUIDE.md    (da root)
│   ├── APP_PROVISIONING_RUNBOOK.md       (da root — assorbe PRODUCTION_READY_RUNBOOK.md, quasi-duplicato)
│   └── BETA_TESTING_GUIDE.md             (da root)
│
├── operations/runbooks/                  INTERNA
│   ├── BETA_DEBUG_RUNBOOK.md             (da root)
│   ├── BACKUP_RECOVERY_GUIDE.md          (da root)
│   └── DEPLOY_PILOT.md                   (già in platform-infra/runbooks/ — referenziato, non duplicato)
│
├── release/                              INTERNA
│   ├── BETA_RELEASE.md, SIGNED_APK_READY.md   (da root)
│   ├── FINAL_RELEASE_DECISION.md, MVP_CLIENT_RELEASE_CHECKLIST.md, APP_STORE_READINESS.md   (da root)
│
├── beta/                                 INTERNA (storico checkpoint beta — vedi cleanup plan, cluster ampio)
│   └── (l'intero cluster beta/APK/device-install, ~20 file, come archivio cronologico)
│
└── legacy/                               INTERNA
    ├── MASTER_HANDOVER_FABLE5.md, BACKEND_RUNTIME_STATUS.md   (da root)
    ├── SCREEN_ANALYSIS.md                (da root)
    └── reference-assets/ (NON tracciata in git — dove dovrebbero vivere i 17 IMG_97xx.HEIC, oggi committati per errore alla radice)
```

**Nota sui documenti di stato canonici**: `REAL_PROJECT_STATE.md`, `PROJECT_FREEZE_STATE.md` e i 5 documenti generati in questa sessione (`PROJECT_STRUCTURE.md`, `PROJECT_CLEANUP_PLAN.md`, `PROJECT_DEPENDENCIES.md`, `PROJECT_STANDARD.md`, `PROJECT_EVOLUTION_ROADMAP.md`) sono **gli unici documenti che ha senso tenere alla radice del repository**, insieme a un futuro `README.md` — sono il punto d'ingresso "stato attuale verificato" che qualunque nuovo arrivato (umano o audit futuro) deve trovare per primo, senza doverlo cercare in una sottocartella. Tutto il resto (i 77 file di sessione) appartiene a `docs/`.

**Chi usa cosa / pubblica vs interna** — criterio applicato sopra: *pubblica* = utile a chiunque lavori sul prodotto (dev, PM, designer); *interna* = presuppone accesso a sistemi/segreti operativi (deploy, infra, credenziali, cronologia di audit grezza) o è storico non normativo. Questa distinzione è oggi solo organizzativa (un solo repository Git, nessun controllo accessi per cartella) — diventerebbe reale solo con lo split multi-repository previsto (e mai realizzato) da `docs/22`.

---

## 3. Cosa NON cambia in questa fase

Per vincolo esplicito di questo audit: nessun file viene spostato, nessuna cartella rinominata. La struttura sopra è una **proposta**, dettagliata a sufficienza da poter essere eseguita meccanicamente in una fase successiva (uno script di `git mv` che replica esattamente le mappature elencate), ma la sua esecuzione è responsabilità di un intervento futuro esplicitamente autorizzato — vedi `PROJECT_EVOLUTION_ROADMAP.md`, orizzonte "Immediate".
