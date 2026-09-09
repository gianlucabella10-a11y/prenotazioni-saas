# PROJECT_INDEX — Punto di ingresso assoluto

Se stai aprendo questo repository per la prima volta, parti da qui.

## Ordine di lettura consigliato

1. **[`README.md`](README.md)** — cos'è il progetto, come funziona ad alto livello (5 minuti).
2. **Questo file** — mappa di tutto ciò che esiste.
3. **[`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md)** — stato reale verificato sul codice (non intenzioni). Se hai tempo per un solo documento tecnico oltre al README, è questo.
4. In base al tuo ruolo:
   - **Sviluppatore nuovo** → [`DEVELOPER_ONBOARDING.md`](DEVELOPER_ONBOARDING.md) (30 minuti) → [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md) (riferimento) → [`PROJECT_MAP.md`](PROJECT_MAP.md) → [`DEPENDENCY_GRAPH.md`](DEPENDENCY_GRAPH.md) → `docs/<Area>/`
   - **Proprietario piattaforma/operatore** → [`OWNER_GUIDE.md`](OWNER_GUIDE.md) → [`OPERATION_MANUAL.md`](OPERATION_MANUAL.md) → [`BUSINESS_FLOW.md`](BUSINESS_FLOW.md) → `docs/Business/`
   - **Architetto/tech lead** → [`PROJECT_STRUCTURE_AUDIT.md`](PROJECT_STRUCTURE_AUDIT.md) → [`PROJECT_STANDARDIZATION_AUDIT.md`](PROJECT_STANDARDIZATION_AUDIT.md) → [`SYSTEM_BOUNDARIES.md`](SYSTEM_BOUNDARIES.md) → [`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md) → [`PROJECT_SCORE.md`](PROJECT_SCORE.md) → [`PROJECT_STANDARD.md`](PROJECT_STANDARD.md) → [`PROJECT_EVOLUTION_ROADMAP.md`](PROJECT_EVOLUTION_ROADMAP.md)

## Elenco moduli (backend, `platform-backend/app/`)

| Modulo | Responsabilità | Stato |
|---|---|---|
| `Foundation/Tenancy` | Isolamento multi-tenant | 🟢 |
| `Foundation/Auth` | Autenticazione JWT, MFA, verifica email | 🟢 |
| `Foundation/Audit` | Log azioni sensibili | 🟡 |
| `TenantManagement` | Ciclo di vita cliente, piani, quote | 🟢 |
| `Scheduling` | Motore di prenotazione | 🟢 |
| `Catalog` | Servizi, varianti, location | 🟢 |
| `Staff` | Anagrafica operatori | 🟢 |
| `Customers` | Profilo/consensi cliente finale | 🟢 |
| `Branding` | Asset e tema white-label | 🟢 |
| `Notifications` | Invio multi-canale (email/push) | 🟢 nucleo / 🟡 operatività |
| `AppFactory` | Identità app, manifest, build, beta | 🟡 |
| `ControlRoom` | Pannello super-admin | 🟡 |
| `Dashboard` | Pannello titolare/staff | 🟢 |

Legenda stato: vedi [`PROJECT_STRUCTURE_AUDIT.md`](PROJECT_STRUCTURE_AUDIT.md) per il dettaglio completo (16 aree, incluso Build Engine 🔴).

## File principali (radice — unici `.md` ammessi qui, per standard)

| File | Scopo |
|---|---|
| `README.md` | Homepage tecnica |
| `PROJECT_INDEX.md` | Questo file — entry point assoluto |
| `DEVELOPER_ONBOARDING.md` | Percorso guidato a tempo — da zero a produttivo in 30 minuti |
| `DEVELOPER_GUIDE.md` | Riferimento completo: setup, build, test, convenzioni, git, deploy |
| `OWNER_GUIDE.md` | Guida per il proprietario della piattaforma (build, beta, tester, monitoraggio, backup, ripristino) |
| `PROJECT_MAP.md` | Cosa contiene/chi usa/chi dipende/chi può modificare, ogni cartella |
| `DEPENDENCY_GRAPH.md` | Moduli indipendenti/critici, dipendenze, cicli, accoppiamenti (sintesi) |
| `BUSINESS_FLOW.md` | Flusso end-to-end cliente→prenotazione→feedback |
| `OPERATION_MANUAL.md` | Uso quotidiano (Control Room, Dashboard, manutenzione) |
| `SYSTEM_BOUNDARIES.md` | Cosa il sistema può/non può fare, dipendenze esterne, componenti critici/sostituibili |
| `TECHNICAL_DEBT.md` | Registro debito tecnico completo, priorità e stima tempo |
| `PROJECT_SCORE.md` | Valutazione 0-100 su 14 dimensioni, motivata |
| `PROJECT_STRUCTURE_AUDIT.md` | Audit GREEN/YELLOW/RED per 16 aree |
| `PROJECT_STANDARDIZATION_AUDIT.md` | Audit duplicati/file morti/codice non usato, a grana fine |
| `REAL_PROJECT_STATE.md` | Stato reale verificato codice per codice (fonte di verità) |
| `PROJECT_FREEZE_STATE.md` | Stato per modulo/cartella/servizio/comando/schermata |
| `PROJECT_STRUCTURE.md` | Struttura ideale proposta (piano che ha originato la riorganizzazione eseguita) |
| `PROJECT_CLEANUP_PLAN.md` | Motivazione di ogni file spostato/archiviato |
| `PROJECT_DEPENDENCIES.md` | Diagramma di dipendenza dettagliato (versione estesa di `DEPENDENCY_GRAPH.md`) |
| `PROJECT_STANDARD.md` | Standard vincolanti (naming, cartelle, namespace, test, release, versioning) |
| `PROJECT_EVOLUTION_ROADMAP.md` | Roadmap Immediate/Next/Future/Long Term |
| `OPERATIONS_BLUEPRINT.md` | Cosa richiede il terminale oggi, cosa può passare a Control Room, cosa automatizzare |
| `CONTROL_ROOM_ROADMAP.md` | Funzionalità operative enterprise: ESSENZIALI/UTILI/FUTURE |
| `PLATFORM_LIFECYCLE.md` | Ciclo di vita cliente completo, Lead→Archiviazione |
| `PLATFORM_ROLES.md` | Ruoli organizzativi vs. ruoli tecnici (`UserType`) |
| `OPERATING_PROCEDURES.md` | SOP operative (nuovo cliente, build fallita, backup, ripristino, ecc.) |
| `AUTOMATION_CATALOG.md` | Automazioni future, beneficio/priorità/impatto |
| `FOUNDER_DASHBOARD.md` | Definizione dashboard proprietario (widget/KPI/alert) — non implementata |
| `PROJECT_MATURITY.md` | Livello di maturità: Prototype→Enterprise, cosa manca per ognuno |
| `FOLDER_RESPONSIBILITIES.md` | Scopo/dipendenze/chi-può-modificare per ogni cartella, a livello sotto-modulo |
| `CORE_FILES_REFERENCE.md` | I file più critici: perché esistono, cosa succede se eliminati |
| `DEAD_CODE_REPORT.md` | Codice/asset/config/schermate non usati, verificati uno per uno |
| `NAMING_GUIDELINES.md` | Standard di naming per categoria (controller, job, service, ecc.), incoerenze note |
| `SYSTEM_FLOW.md` | Flusso tecnico sistema-per-sistema (diverso da `BUSINESS_FLOW.md`, taglio tecnico non di business) |
| `DEPENDENCY_GRAPH.md` | Moduli indipendenti/critici, dipendenze vietate/corrette, cicli, accoppiamenti |
| `SCALABILITY_REPORT.md` | 10/100/1.000/10.000/100.000 clienti, con dati reali dal Terraform |
| `MAINTAINABILITY_REPORT.md` | Un nuovo team (2 Junior+Senior+Flutter+DevOps) capirebbe il progetto? Quanto tempo? |
| `TECHNICAL_ROADMAP.md` | Roadmap tecnica Done/In Progress/Next/Future/Blocked verso v2.0 |
| `ZERO_MANUAL_OPERATIONS_AUDIT.md` | Ogni operazione terminale: motivo, automatizzabile?, Control Room?, deve restare tecnica? |
| `CONTROL_ROOM_AUTOMATION_PLAN.md` | Implementabile/pericolosa/utile per ogni candidato — e le 3 scelte implementate davvero |
| `FOUNDER_ONE_CLICK_FLOW.md` | Flusso reale login→APK, conteggio click onesto |
| `FOUNDER_EXPERIENCE_AUDIT.md` | Operazioni ancora tecniche: CRITICHE/IMPORTANTI/OPZIONALI |
| `FOUNDER_AUTOMATION_MATRIX.md` | Automatizzabile/nascondibile/tecnica/pulsante per ogni operazione |
| `FOUNDER_DAILY_WORKFLOW.md` | Giornata tipo del Founder, minuto per minuto |
| `CONTROL_ROOM_MATURITY_REPORT.md` | Voto 0-100 su Operatività/Semplicità/Automazione/Affidabilità/Scalabilità/Tempo risparmiato |
| `PLATFORM_SCALABILITY_REPORT.md` | Simulazione 1→10→100→1.000 clienti: cosa funziona/rallenta/si rompe |
| `PLATFORM_OPERATIONS_MATRIX.md` | Ogni processo operativo: Automatizzato/Semi automatico/Manuale/Critico |
| `FOUNDER_DEPENDENCY_REPORT.md` | Tutto ciò che dipende ancora dal Founder — click, decisioni, controlli |
| `COMPANY_OPERATING_MANUAL.md` | Come funziona l'azienda (non il codice) — chi fa cosa, quando, perché |
| `TEAM_ROLES.md` | Ruoli futuri del team (Support, Designer, Sales, Customer Success, Marketing, DevOps, Finance) |
| `FLEET_CURRENT_STATE.md` | Cosa gestito a livello singola app vs. flotta vs. cosa manca completamente |
| `FLEET_DASHBOARD_SPEC.md` | Ogni metrica di flotta: ottenibile/parziale/non ottenibile, con fonte reale |
| `FLEET_OPERATIONS.md` | Operazioni di massa: già possibile/implementabile/non consigliata |
| `FOUNDER_COMMAND_CENTER.md` | La schermata principale ripensata come cabina di comando (10 secondi) |
| `BUSINESS_OS_AUDIT.md` | Gestione clienti/onboarding/rinnovi/licenze/supporto/CRM/contratti: READY/PARTIAL/MISSING + moduli progettati |
| `GO_TO_MARKET_REPORT.md` | Cosa impedisce oggi di vendere a 100 clienti — commerciale, operativo, tecnico |
| `CEO_DASHBOARD.md` | Cosa apre il Founder ogni mattina: livello prodotto + livello business |

## Elenco documentazione (`docs/`)

| Cartella | Contenuto | File |
|---|---|---|
| `docs/00-indice.md` | Indice della serie numerata Business/Architecture | — |
| `docs/Business/` | Prodotto, mercato, strategia (01-17, 20 + 3 report) | 20 |
| `docs/Architecture/` | Progettazione tecnica (18-19, 21-33 + review) | 14 |
| `docs/Backend/` | Code review backend | 2 (incl. index) |
| `docs/Flutter/` | Config Firebase richiesta | 2 |
| `docs/AppFactory/` | Segreti release, indice pipeline | 2 |
| `docs/ControlRoom/` | Guida operatore | 2 |
| `docs/WhiteLabel/` | Indice motore white-label | 1 |
| `docs/Deployment/` | Ambienti, build machine, firma, deploy, release process | 6 |
| `docs/Testing/` | Test su device reale | 2 |
| `docs/Operations/` | Preview locale, backup | 3 |
| `docs/Runbooks/` | Provisioning, beta testing, debug | 4 |
| `docs/API/` | Contratto REST | 2 |
| `docs/Release/` | Checklist e decisioni di rilascio | 5 |
| `docs/developer/`, `docs/customer/`, `docs/build/`, `docs/security/`, `docs/troubleshooting/` | Cartelle indice per ruolo/argomento — puntano ai documenti sopra, nessun contenuto duplicato | 1 ciascuna |
| `docs/archive/` | Storico (8 cluster, ~70 file + 17 immagini) — vedi `docs/archive/README.md` | ~87 |

## Percorsi chiave del codice

```
platform-backend/app/Modules/          moduli di dominio
platform-backend/app/Foundation/       codice trasversale (tenancy, auth, audit, http)
platform-backend/routes/{api,web,console}.php
platform-backend/database/migrations/  schema (23 file)
platform-mobile/apps/client_app/lib/   app Flutter unica
platform-infra/terraform/pilot/        infrastruttura (ambiente pilota)
.github/workflows/                     CI + pipeline build
```

## Stato del progetto (sintesi — dettaglio in `REAL_PROJECT_STATE.md`/`PROJECT_FREEZE_STATE.md`)

Piattaforma funzionante: multi-tenancy isolata e testata, booking engine sofisticato, auth JWT+MFA reale, push/crash reporting reali. Gap noti e circoscritti: Build Engine (firma/versioning APK), documentazione ora riorganizzata (era il gap più grande, risolto in questa sessione), infrastruttura dichiaratamente "pilota" (nessuno staging/autoscaling). Nessun pagamento implementato. Valutazione onesta completa: `REAL_PROJECT_STATE.md` §10 (7/10 da CTO).

## Roadmap (sintesi — dettaglio in `PROJECT_EVOLUTION_ROADMAP.md`)

- **Immediate** (fatto in questa sessione): riorganizzazione documentazione, README, entry point.
- **Next**: test di architettura, `config/cors.php`, envelope API unificato, copertura test mancante.
- **Future**: decisione mono-repo vs multi-repo, driver di build `github` come standard, ambiente di staging, versioning APK reale, contratto OpenAPI.
- **Long Term**: scalabilità infrastrutturale, isolamento build a scala, processo ADR, revisione periodica di questi stessi documenti.
