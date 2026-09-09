# 22 — Struttura Repository

> ⚠️ **Piano storico, non stato attuale.** Questo documento descrive la struttura pianificata *prima* dell'implementazione. Il repository reale è un **mono-repo singolo** (non tre repository separati come descritto sotto), con moduli backend diversi da quelli qui elencati (`Billing`, `Reporting`, `Compliance` non sono mai stati costruiti; `AppFactory`, `ControlRoom`, `Dashboard` esistono e non erano previsti qui). Scostamento completo, sezione per sezione: [`PROJECT_STRUCTURE.md`](../../PROJECT_STRUCTURE.md) §0 alla radice del repository. Per la struttura reale verificata sul codice: [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) e [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md).

## 1. Strategia: tre repository

| Repository | Contenuto | Razionale |
|---|---|---|
| `platform-backend` | Laravel: API, dashboard web, job, scheduler | Ciclo di rilascio continuo, indipendente dalle app |
| `platform-mobile` | Flutter: app cliente white label + app gestionale (monorepo Dart con package condivisi) | Le due app condividono dominio e API client; rilascio legato agli store |
| `platform-infra` | IaC (Terraform), pipeline white label, template configurazione build | Permessi e audit separati: tocca credenziali di firma e infrastruttura |

Un monorepo unico è stato scartato: i permessi (le chiavi di firma app e l'IaC non devono essere accessibili a tutto il team) e i cicli di rilascio sono troppo diversi.

## 2. `platform-backend` (Laravel)

```
platform-backend/
├── app/
│   ├── Foundation/              # codice trasversale non di dominio
│   │   ├── Tenancy/             # TenantContext, risoluzione tenant, global scope, trait BelongsToTenant
│   │   ├── Auth/                # JWT guard, MFA, refresh token
│   │   ├── Audit/               # audit logging trasversale
│   │   └── Http/                # middleware comuni, ApiController base, formato errori
│   └── Modules/                 # un modulo per bounded context (vedi 23-ddd)
│       ├── TenantManagement/
│       ├── Billing/
│       ├── Branding/
│       ├── Catalog/             # servizi, varianti, pacchetti
│       ├── Scheduling/          # orari, disponibilità, appuntamenti, waitlist
│       ├── Staff/
│       ├── Customers/           # CRM clienti finali del tenant
│       ├── Notifications/
│       ├── Reporting/
│       ├── BuildPipeline/       # orchestrazione build white label
│       └── Compliance/          # GDPR: consensi, export, cancellazione
│
│   # ogni modulo segue la stessa struttura interna:
│   # Modules/<Nome>/
│   #   ├── Domain/              # entità, value object, eventi, interfacce repository, servizi di dominio
│   #   ├── Application/         # use case (command/query handler), DTO
│   #   ├── Infrastructure/      # Eloquent model, repository concreti, client esterni
│   #   └── Presentation/        # controller API, controller web, form request, resource
│
├── database/
│   ├── migrations/
│   └── seeders/                 # seed demo/tenant sintetici per dev e staging
├── routes/
│   ├── api_v1.php               # API versionate
│   ├── web_tenant.php           # dashboard tenant (sottodominio)
│   └── web_admin.php            # dashboard super admin
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── TenantIsolation/         # suite dedicata RNF-21, obbligatoria in CI
└── docker/                      # immagini per sviluppo locale e build
```

Regole di dipendenza tra moduli: i moduli comunicano tramite **eventi di dominio** o interfacce pubbliche (`Application` layer); vietato importare `Infrastructure` di un altro modulo. Verificato in CI con regole di architettura (test di dipendenza).

## 3. `platform-mobile` (Flutter, monorepo Dart)

```
platform-mobile/
├── apps/
│   ├── client_app/              # app cliente finale (white label)
│   │   ├── lib/main.dart        # bootstrap, legge WhiteLabelConfig
│   │   ├── android/             # template; i valori per-tenant sono iniettati dalla pipeline
│   │   └── ios/                 # template; idem
│   └── staff_app/               # app gestionale unica (tenant admin + operatori)
├── packages/
│   ├── core_domain/             # entità e logica condivisa (Appointment, Service, slot)
│   ├── api_client/              # client REST generato/da contratto, gestione token e retry
│   ├── design_system/           # componenti UI themabili (tema da config runtime)
│   ├── white_label/             # caricamento/caching WhiteLabelConfig, tema dinamico
│   └── notifications/           # integrazione FCM, gestione permessi e token
├── tooling/
│   └── tenant_build/            # script generazione config nativa per-tenant (consumati da platform-infra)
└── melos.yaml                   # gestione monorepo Dart
```

Struttura interna di ogni app (Clean Architecture, vedi [23-ddd-clean-architecture.md](23-ddd-clean-architecture.md)):

```
lib/
├── features/<feature>/
│   ├── domain/                  # use case, entità (o re-export da core_domain), repository astratti
│   ├── data/                    # repository concreti su api_client, cache locale
│   └── presentation/            # state management, pagine, widget
└── app/                         # routing, DI, bootstrap, gestione sessione
```

## 4. `platform-infra`

```
platform-infra/
├── terraform/
│   ├── modules/                 # vpc, ecs-service, rds, elasticache, s3-cdn, waf, ses
│   └── envs/
│       ├── staging/
│       └── production/
├── whitelabel-pipeline/
│   ├── templates/               # template android/ios (manifest, plist, gradle) con placeholder
│   ├── generator/               # genera config per-tenant dal tenant registry (via API backend)
│   └── workflows/               # definizioni pipeline (build, firma, upload store)
├── runbooks/                    # procedure operative: DR, incident, rotazione segreti, restore
└── docs/                        # ADR (Architecture Decision Records)
```

## 5. Convenzioni trasversali

- **Versionamento**: trunk-based con branch corti; tag semantici; le release backend sono continue, le release mobile sono treni programmati
- **ADR**: ogni decisione architetturale significativa è registrata come ADR in `platform-infra/docs`
- **Segreti**: mai nei repository; AWS Secrets Manager + variabili iniettate in CI; chiavi di firma app in storage cifrato accessibile solo alla pipeline white label
- **Contratto API**: specifica OpenAPI versionata in `platform-backend`, pubblicata come artefatto; `api_client` Flutter si aggiorna dal contratto (il contratto è la fonte di verità, vedi [25-api-rest.md](25-api-rest.md))
