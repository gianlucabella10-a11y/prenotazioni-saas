# Piattaforma White Label per Prenotazioni Professionali
## Indice Documentazione Architetturale

> **Punto d'ingresso del repository**: [`PROJECT_INDEX.md`](../PROJECT_INDEX.md) alla radice. Questo indice copre solo la serie numerata di documentazione business/architetturale (`docs/Business/`, `docs/Architecture/`, `docs/API/`, `docs/Backend/`) — per moduli tecnici (App Factory, Control Room, Flutter, White Label), guide sviluppatore, runbook e stato del progetto, parti da `PROJECT_INDEX.md`.
>
> **Nota storica**: questa serie (01→34) è stata scritta in un'unica sessione iniziale (2026-06-13) come pianificazione pre-implementazione. Il codice reale, verificato in `REAL_PROJECT_STATE.md`, ha in parte divergito da quanto qui descritto — in particolare la strategia "tre repository" del documento 22 non è mai stata realizzata (è un solo repository). Dove la realtà diverge dal piano, fidarsi di `REAL_PROJECT_STATE.md`/`PROJECT_FREEZE_STATE.md`, non di questa serie.

Questo set di documenti costituisce la documentazione di progetto completa per la piattaforma White Label di prenotazione appuntamenti, destinata a professionisti (barbieri, parrucchieri, estetisti, dentisti, studi medici, fisioterapisti, consulenti).

**Nota metodologica:** il progetto si ispira, esclusivamente sul piano funzionale, ad applicazioni di prenotazione per saloni/barbershop oggi sul mercato (es. categoria "app per barbieri su appuntamento"). Non viene replicata alcuna grafica, marchio, copy o asset proprietario di terzi: ogni elemento di design, naming e identità visiva qui descritto è originale e personalizzabile per ciascun cliente (white label).

### Fase 1 — Business & Prodotto (`docs/Business/`)

| # | Documento | Contenuto |
|---|---|---|
| 01 | [Product Requirements Document](Business/01-prd.md) | Visione, obiettivi, scope, KPI |
| 02 | [Analisi di Mercato](Business/02-analisi-mercato.md) | Dimensione mercato, trend, segmenti |
| 03 | [Analisi Concorrenti](Business/03-analisi-concorrenti.md) | Competitor diretti/indiretti, posizionamento |
| 04 | [Analisi SWOT](Business/04-swot.md) | Punti di forza, debolezza, opportunità, minacce |
| 05 | [Software Requirements Specification](Business/05-srs.md) | Requisiti funzionali e non funzionali |
| 06 | [User Personas](Business/06-user-personas.md) | Profili utente target |
| 07 | [User Journey](Business/07-user-journey.md) | Percorsi utente end-to-end |
| 08 | [Flussi Applicativi](Business/08-flussi-applicativi.md) | Flussi operativi completi |
| 09 | [Moduli](Business/09-moduli.md) | Architettura modulare del prodotto |
| 10 | [Funzionalità](Business/10-funzionalita.md) | Catalogo funzionalità per modulo |
| 11 | [Strategia White Label](Business/11-strategia-white-label.md) | Modello di personalizzazione e branding |
| 12 | [Strategia Multi-Tenant](Business/12-strategia-multi-tenant.md) | Architettura multi-tenant |
| 13 | [Strategia Scalabilità](Business/13-strategia-scalabilita.md) | Crescita tecnica e operativa |
| 14 | [Strategia Sicurezza](Business/14-strategia-sicurezza.md) | Sicurezza, privacy, compliance |
| 15 | [Strategia Monetizzazione](Business/15-strategia-monetizzazione.md) | Pricing, revenue model, unit economics |
| 16 | [Piano di Crescita a 10.000 Clienti](Business/16-piano-crescita.md) | Roadmap di scala commerciale e operativa |
| 17 | [Audit e Revisione Finale](Business/17-audit-revisione.md) | 30+ criticità, soluzioni, verifica finale |
| 20 | [Analisi Critica Fase 1](Business/20-analisi-critica-fase1.md) | Review multi-ruolo (CTO, Architect, PM, Flutter, Laravel): errori, lacune, rischi con gravità/impatto/soluzione |
| — | [Tech Status Report](Business/TECH_STATUS_REPORT.md) | Ispezione codice propedeutica a roadmap "premium" |
| — | [Product Design Roadmap](Business/PRODUCT_DESIGN_ROADMAP.md) | Roadmap UX/UI |
| — | [Giuffrida Feature Gap](Business/GIUFFRIDA_FEATURE_GAP.md) | Gap analysis vs. app di riferimento, 13 endpoint mancanti, priorità P0-P2 |

### Fase 2 — Progettazione Tecnica (`docs/Architecture/`, `docs/API/`) — stack pianificato: Flutter, Laravel, MySQL, Redis, S3, Firebase, AWS

| # | Documento | Contenuto |
|---|---|---|
| 18 | [Revisione Critica Fase 1](Architecture/18-revisione-critica-fase1.md) | Seconda review critica, propedeutica alla progettazione tecnica |
| 19 | [Architettura Tecnica](Architecture/19-architettura-tecnica.md) | Architettura tecnica completa |
| 21 | [Architettura Generale](Architecture/21-architettura-generale.md) | Vista d'insieme AWS, monolite modulare, ambienti, CI/CD |
| 22 | [Struttura Repository](Architecture/22-struttura-repository.md) | ⚠️ Piano originale (tre repository) — vedi nota storica sopra, oggi divergente dalla realtà |
| 23 | [DDD e Clean Architecture](Architecture/23-ddd-clean-architecture.md) | Bounded context, ubiquitous language, aggregati, layer |
| 24 | [Database e ER Diagram](Architecture/24-database-er.md) | Schema relazionale, ER diagram, cicli di vita |
| 25 | [API REST](API/25-api-rest.md) | Endpoint, versioning, idempotenza, rate limiting |
| 26 | [Autenticazione e Autorizzazione](Architecture/26-autenticazione-autorizzazione.md) | JWT, refresh rotation, MFA, RBAC |
| 27 | [White Label Tecnico](Architecture/27-white-label-tecnico.md) | Thin shell + config runtime, pipeline build, strategia store |
| 28 | [Multi-Tenant Tecnico](Architecture/28-multi-tenant-tecnico.md) | Livelli di isolamento, tenant registry, quote |
| 29 | [Notifiche](Architecture/29-notifiche-tecnico.md) | FCM, promemoria ibridi, campagne |
| 30 | [Engine Appuntamenti](Architecture/30-engine-appuntamenti.md) | Availability engine, booking atomico, macchina a stati, fusi orari |
| 31 | [Dashboard](Architecture/31-dashboard-design.md) | Super Admin, dashboard tenant, app gestionale, app cliente |
| 32 | [Qualità e Costi AWS](Architecture/32-qualita-aws.md) | Performance, costi, colli di bottiglia, DR, backup, logging, monitoring |
| 33 | [Audit Tecnico](Architecture/33-audit-tecnico.md) | 56 criticità con correzioni, riesame finale delle scelte |
| — | [Architecture Final Review](Architecture/ARCHITECTURE_FINAL_REVIEW.md) | Review a 4 ruoli "regge a 1.000 tenant?" |

### Fase 3 — Implementazione (`docs/Backend/`)

| # | Documento | Contenuto |
|---|---|---|
| 34 | [Code Review Backend](Backend/34-code-review-backend.md) | Rilievi della review continua del backend |

### Documenti storici (spostati in `docs/archive/` — valore di cronologia, non fonte di verità corrente)

`SCREEN_ANALYSIS`, `MASTER_HANDOVER_FABLE5`, `BACKEND_RUNTIME_STATUS`, `MVP_PRODUCTION_READINESS_REPORT` → `docs/archive/misc/`; `PROFESSIONAL_DASHBOARD_PLAN`/`READINESS` → `docs/archive/control-room/`. Vedi `PROJECT_CLEANUP_PLAN.md` per l'elenco completo e la motivazione di ogni spostamento.

Le decisioni/checklist di release (`FINAL_RELEASE_DECISION`, `APP_STORE_READINESS`, `MVP_CLIENT_RELEASE_CHECKLIST`) sono in `docs/Release/` — ancora operativamente rilevanti, non archiviate.

### Sintesi del progetto

- **Modello di business**: attivazione una tantum 990€ + canone mensile 250€ + IVA per cliente (tenant)
- **Target**: professionisti che operano su appuntamento (settore beauty, healthcare, servizi professionali)
- **Obiettivo prodotto**: piattaforma SaaS multi-tenant che genera app mobile brandizzate (white label) configurabili da dashboard, senza intervento sul codice
- **Configurabilità per tenant**: nome app, logo, icona, splash screen, colori, servizi, operatori, orari
