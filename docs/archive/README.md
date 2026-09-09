# docs/archive/ — Cronologia, non fonte di verità

Questa cartella raccoglie documenti storici: audit puntuali, report di readiness, piani di fase superati dall'implementazione, checkpoint di sessioni di lavoro passate. **Nessuno di questi file è stato eliminato** (per esplicita richiesta) — sono spostati qui perché descrivono uno stato passato, non lo stato attuale del progetto.

**Per lo stato attuale, verificato direttamente sul codice, usa sempre**: [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) e [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md) alla radice del repository — mai un documento in questa cartella.

## Struttura

| Sottocartella | Contenuto | File |
|---|---|---|
| `app-factory/` | Piani e report di fase del motore App Factory (Master Plan → Phase 3), runbook duplicato | 11 |
| `white-label/` | Catena di audit del White Label Engine (4 checkpoint, stessa sessione) | 4 |
| `beta-readiness/` | 6 cicli ripetuti di "audit → fix → readiness report" sul percorso Control Room→build→APK→beta | 12 |
| `build-machine/` | Snapshot toolchain/firma/install-su-device, per lo più mai committati | 10 |
| `control-room/` | Piani/readiness di Control Room e Dashboard professionista | 4 |
| `environment/` | Versioni precedenti della guida ambienti, consolidate in `docs/Deployment/ENVIRONMENT_GUIDE.md` | 3 |
| `misc/` | Audit/report one-off senza cluster proprio (incl. un duplicato letterale dichiarato) | 9 |
| `reference-assets/` | 17 screenshot di terze parti (app "Giuffrida Barber") usati come materiale di analisi in `SCREEN_ANALYSIS.md` | 17 |

## Attenzione — 3 documenti qui contraddicono il codice attuale

`build-machine/SIGNED_APK_READY.md` e `build-machine/ANDROID_SIGNING_FINAL.md` affermano che la firma release è attiva: **non è più vero** (`PROJECT_FREEZE_STATE.md` §8 verifica che il dispatcher `local` non imposta le variabili del keystore, quindi ricade su firma debug). `misc/FINAL_TECHNICAL_FREEZE_REPORT.md` è un duplicato letterale, dichiarato tale nel proprio testo, di `misc/FINAL_PRODUCT_READINESS_AUDIT.md`. Tutti e tre portano una nota di avviso in testa al file.

Per la motivazione dettagliata di ogni singolo spostamento, vedi [`PROJECT_CLEANUP_PLAN.md`](../../PROJECT_CLEANUP_PLAN.md) alla radice.
