# Piattaforma White Label per Prenotazioni Professionali
## Indice Documentazione Architetturale

Questo set di documenti costituisce la documentazione di progetto completa per la piattaforma White Label di prenotazione appuntamenti, destinata a professionisti (barbieri, parrucchieri, estetisti, dentisti, studi medici, fisioterapisti, consulenti).

**Nota metodologica:** il progetto si ispira, esclusivamente sul piano funzionale, ad applicazioni di prenotazione per saloni/barbershop oggi sul mercato (es. categoria "app per barbieri su appuntamento"). Non viene replicata alcuna grafica, marchio, copy o asset proprietario di terzi: ogni elemento di design, naming e identità visiva qui descritto è originale e personalizzabile per ciascun cliente (white label).

### Struttura dei documenti

| # | Documento | Contenuto |
|---|---|---|
| 01 | [Product Requirements Document](01-prd.md) | Visione, obiettivi, scope, KPI |
| 02 | [Analisi di Mercato](02-analisi-mercato.md) | Dimensione mercato, trend, segmenti |
| 03 | [Analisi Concorrenti](03-analisi-concorrenti.md) | Competitor diretti/indiretti, posizionamento |
| 04 | [Analisi SWOT](04-swot.md) | Punti di forza, debolezza, opportunità, minacce |
| 05 | [Software Requirements Specification](05-srs.md) | Requisiti funzionali e non funzionali |
| 06 | [User Personas](06-user-personas.md) | Profili utente target |
| 07 | [User Journey](07-user-journey.md) | Percorsi utente end-to-end |
| 08 | [Flussi Applicativi](08-flussi-applicativi.md) | Flussi operativi completi |
| 09 | [Moduli](09-moduli.md) | Architettura modulare del prodotto |
| 10 | [Funzionalità](10-funzionalita.md) | Catalogo funzionalità per modulo |
| 11 | [Strategia White Label](11-strategia-white-label.md) | Modello di personalizzazione e branding |
| 12 | [Strategia Multi-Tenant](12-strategia-multi-tenant.md) | Architettura multi-tenant |
| 13 | [Strategia Scalabilità](13-strategia-scalabilita.md) | Crescita tecnica e operativa |
| 14 | [Strategia Sicurezza](14-strategia-sicurezza.md) | Sicurezza, privacy, compliance |
| 15 | [Strategia Monetizzazione](15-strategia-monetizzazione.md) | Pricing, revenue model, unit economics |
| 16 | [Piano di Crescita a 10.000 Clienti](16-piano-crescita.md) | Roadmap di scala commerciale e operativa |
| 17 | [Audit e Revisione Finale](17-audit-revisione.md) | 30+ criticità, soluzioni, verifica finale |

### Fase 2 — Progettazione Tecnica (stack: Flutter, Laravel, MySQL, Redis, S3, Firebase, AWS)

| # | Documento | Contenuto |
|---|---|---|
| 20 | [Analisi Critica Fase 1](20-analisi-critica-fase1.md) | Review multi-ruolo (CTO, Architect, PM, Flutter, Laravel): errori, lacune, rischi con gravità/impatto/soluzione |
| 21 | [Architettura Generale](21-architettura-generale.md) | Vista d'insieme AWS, monolite modulare, ambienti, CI/CD |
| 22 | [Struttura Repository](22-struttura-repository.md) | Tre repository, layout backend/mobile/infra |
| 23 | [DDD e Clean Architecture](23-ddd-clean-architecture.md) | Bounded context, ubiquitous language, aggregati, layer |
| 24 | [Database e ER Diagram](24-database-er.md) | Schema relazionale MySQL completo, ER diagram, cicli di vita |
| 25 | [API REST](25-api-rest.md) | Endpoint completi, versioning, idempotenza, rate limiting |
| 26 | [Autenticazione e Autorizzazione](26-autenticazione-autorizzazione.md) | JWT, refresh rotation, MFA, RBAC, impersonificazione |
| 27 | [White Label Tecnico](27-white-label-tecnico.md) | Thin shell + config runtime, pipeline build, strategia store |
| 28 | [Multi-Tenant Tecnico](28-multi-tenant-tecnico.md) | Cinque livelli di isolamento, tenant registry, quote |
| 29 | [Notifiche](29-notifiche-tecnico.md) | FCM multi-progetto, promemoria ibridi, campagne |
| 30 | [Engine Appuntamenti](30-engine-appuntamenti.md) | Availability engine, booking atomico, macchina a stati, fusi orari |
| 31 | [Dashboard](31-dashboard-design.md) | Super Admin, dashboard tenant, app gestionale, app cliente |
| 32 | [Qualità e Costi AWS](32-qualita-aws.md) | Performance, costi per fase, colli di bottiglia, DR, backup, logging, monitoring |
| 33 | [Audit Tecnico](33-audit-tecnico.md) | 56 criticità con correzioni, riesame finale delle scelte |

### Fase 3 — Implementazione

| # | Documento | Contenuto |
|---|---|---|
| 34 | [Code Review Backend](34-code-review-backend.md) | 24 rilievi della review continua (applicati/pianificati) |
| — | [`platform-backend/`](../platform-backend/README.md) | Backend Laravel: multi-tenant, auth JWT+MFA, white label config, booking engine, notifiche — 64 test verdi |
| — | [`platform-mobile/apps/client_app/`](../platform-mobile/apps/client_app/README.md) | App Flutter cliente white label: 25 test + E2E reale contro il backend, analyzer pulito |
| — | [SCREEN_ANALYSIS](../SCREEN_ANALYSIS.md) | Analisi dei 17 screenshot dell'app di riferimento: 12 schermate mappate |
| — | [GIUFFRIDA_FEATURE_GAP](../GIUFFRIDA_FEATURE_GAP.md) | Gap analysis: replicate/mancanti, 13 endpoint mancanti, priorità P0-P2 |
| — | [MASTER_HANDOVER_FABLE5](../MASTER_HANDOVER_FABLE5.md) | Handover completo per la prossima sessione (stato, convenzioni, prompt) |
| — | [ARCHITECTURE_FINAL_REVIEW](../ARCHITECTURE_FINAL_REVIEW.md) | Review a 4 ruoli "regge a 1.000 tenant?": architettura confermata, 4 cambi non-software |
| — | [BACKEND_RUNTIME_STATUS](../BACKEND_RUNTIME_STATUS.md) | Diagnosi runtime: backend sano, 2 problemi latenti trovati e corretti (cache SQLite, E2E ripetibile) |
| — | [MVP_PRODUCTION_READINESS_REPORT](../MVP_PRODUCTION_READINESS_REPORT.md) | Audit a 6 ruoli: 24 rilievi classificati, journey/UX/security/store simulation |
| — | [FINAL_RELEASE_DECISION](../FINAL_RELEASE_DECISION.md) | GO beta privata (post-stabilizzazione) · NO-GO vendita · NO-GO store, con percorsi di sblocco |
| — | [APP_STORE_READINESS](../APP_STORE_READINESS.md) | Conformità Apple/Play post-stabilizzazione: deletion ✅, privacy ✅, residui elencati |
| — | [MVP_CLIENT_RELEASE_CHECKLIST](../MVP_CLIENT_RELEASE_CHECKLIST.md) | Checklist A/B/C/D: GO beta · bloccanti vendita · store blockers · post-MVP |
| — | [PROFESSIONAL_DASHBOARD_PLAN](../PROFESSIONAL_DASHBOARD_PLAN.md) | Piano dashboard professionista: architettura, API esistenti/mancanti, rischi |
| — | [PROFESSIONAL_DASHBOARD_READINESS](../PROFESSIONAL_DASHBOARD_READINESS.md) | Release report dashboard: GO pilota · GO condizionato pagamento · GO tecnico 10 clienti |

### Sintesi del progetto

- **Modello di business**: attivazione una tantum 990€ + canone mensile 250€ + IVA per cliente (tenant)
- **Target**: professionisti che operano su appuntamento (settore beauty, healthcare, servizi professionali)
- **Obiettivo prodotto**: piattaforma SaaS multi-tenant che genera app mobile brandizzate (white label) configurabili da dashboard, senza intervento sul codice
- **Configurabilità per tenant**: nome app, logo, icona, splash screen, colori, servizi, operatori, orari
