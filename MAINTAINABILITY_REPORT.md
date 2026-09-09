# MAINTAINABILITY_REPORT — Il progetto tra 3 anni, con un nuovo team

> Scenario: 2 sviluppatori Junior, 1 Senior, 1 Flutter Developer, 1 DevOps entrano sul progetto senza nessuno del team originale disponibile.

## Riusciranno a capire il progetto?

**Sì, con differenze nette per ruolo.**

- **Il Senior**: sì, rapidamente. `PROJECT_INDEX.md` → `REAL_PROJECT_STATE.md` → `PROJECT_MAP.md`/`DEPENDENCY_GRAPH.md` gli danno un modello mentale corretto del sistema senza dover leggere codice riga per riga. La disciplina architetturale (moduli, `Foundation/Tenancy`) è leggibile e auto-esplicativa una volta capito il pattern.
- **I 2 Junior**: sì, ma con un percorso più lungo del previsto sulla parte di **isolamento multi-tenant** — è il concetto meno intuitivo del sistema (uno scope Eloquent globale che agisce silenziosamente) e quello dove un errore ha l'impatto più alto. `DEVELOPER_ONBOARDING.md` li porta produttivi su compiti isolati in 30 minuti, ma "produttivo su un modulo" non è "comprende perché `TenantScope` esiste" — quella comprensione richiede di leggere `REAL_PROJECT_STATE.md` §Fase 8 per intero, non solo il codice.
- **Il Flutter Developer**: sì, velocemente — l'app è piccola (35 file), un solo pattern ripetuto 8 volte (`data/domain/presentation`), zero placeholder da decifrare. Il punto di attrito sarà scoprire che `AnalyticsService` esiste ma è morto (`DEAD_CODE_REPORT.md`) — senza questo documento, lo scoprirebbe solo grepping il codice.
- **Il DevOps**: parzialmente. `platform-infra/` è piccolo e ben commentato, ma descrive esplicitamente solo un ambiente "pilota" — un DevOps che arriva aspettandosi una topologia enterprise (staging, autoscaling) scoprirà rapidamente che non esiste, ma `SCALABILITY_REPORT.md` glielo dice subito invece di lasciarlo scoprire in produzione.

## Quanto tempo servirà?

| Attività | Tempo stimato | Nota |
|---|---|---|
| Setup ambiente locale funzionante | < 30 minuti | `DEVELOPER_ONBOARDING.md`, verificato eseguibile in questa sessione |
| Comprensione architetturale sufficiente per una prima modifica isolata (es. un nuovo campo su un modello esistente) | 1-2 giorni | Serve capire `BelongsToTenant` prima di toccare qualunque modello |
| Comprensione sufficiente per progettare un nuovo modulo | 1-2 settimane | Richiede aver letto `PROJECT_STANDARD.md`, `FOLDER_RESPONSIBILITIES.md`, e alcuni moduli esistenti come riferimento |
| Comprensione sufficiente della pipeline di build/App Factory | 3-5 giorni | È la parte più intricata del sistema (3 driver, manifest, asset pipeline) — mitigato da `SYSTEM_FLOW.md` |
| Autonomia operativa completa (deploy, backup, gestione incidenti) | 1 settimana | `OWNER_GUIDE.md`/`OPERATING_PROCEDURES.md` coprono le SOP, ma un ripristino da backup non è mai stato verificato con un test reale — la prima volta sarà comunque un'incognita |

**Totale stimato per un team pienamente autonomo**: **2-3 settimane**, non mesi — a condizione che la documentazione prodotta in questa linea di sessioni venga letta nell'ordine indicato in `PROJECT_INDEX.md`, non scoperta per tentativi.

## Cosa manca (per rendere questo tempo ancora più breve)

1. **Nessuna garanzia automatica sui confini architetturali** (`PROJECT_STRUCTURE_AUDIT.md`) — un Junior può violare la regola "mai importare `Infrastructure` di un altro modulo" senza che nulla glielo impedisca finché non arriva in review. Un test di architettura eliminerebbe questo rischio strutturalmente, non solo culturalmente.
2. **Nessun ripristino da backup mai stato verificato con un test reale** — la procedura è scritta (`docs/Operations/BACKUP_RECOVERY_GUIDE.md`), ma un DevOps che arriva non ha la certezza che funzioni davvero finché non la esegue per la prima volta in un momento di crisi, che è il momento peggiore per scoprire un problema.
3. **Due incoerenze di naming minori** ora documentate ma non corrette (`NAMING_GUIDELINES.md`: `Http/` vs `Presentation/` per i controller, `BuildAppCommand` vs gli altri comandi senza suffisso) — piccolo attrito cognitivo ripetuto ogni volta che un nuovo sviluppatore cerca un controller.
4. **Nessun contratto OpenAPI** — un Flutter Developer che deve capire la forma esatta di una risposta API deve leggere il controller PHP, non un contratto dichiarativo. Rallenta, non blocca.
5. **Il Build Engine resta l'area con più debito silente** (`TECHNICAL_DEBT.md` #1-2) — un nuovo team potrebbe fidarsi per errore di una build come "pronta per produzione" senza sapere del fallback debug sulla firma, se non legge esplicitamente quella sezione.

## Cosa NON manca (e vale la pena dirlo esplicitamente)

Il codice stesso non è un ostacolo alla manutenibilità — è coerente, testato dove conta di più (tenancy, booking), e ora interamente documentato con un punto di ingresso unico. Il rischio per un nuovo team non è "non capire il sistema", è **fidarsi implicitamente di parti che sembrano complete ma hanno un gap silenzioso** (firma release, versioning APK, analytics morto) — motivo per cui `TECHNICAL_DEBT.md` esiste e va letto presto, non alla fine.
