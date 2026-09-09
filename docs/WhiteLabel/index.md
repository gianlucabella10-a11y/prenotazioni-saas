# docs/WhiteLabel/ — Motore di personalizzazione per-tenant

Nessun documento attivo proprio in questa cartella oggi — la catena di audit storica (`WHITE_LABEL_10_10_AUDIT`, `WHITE_LABEL_FINAL_AUDIT`, `WHITE_LABEL_PRODUCTION_READINESS`, `WHITE_LABEL_PRODUCTION_READY`) è in [`docs/archive/white-label/`](../archive/white-label/), superata dall'implementazione.

**Il riferimento esaustivo e verificato vive in**:
- [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) §Fase 5, 9 — pipeline asset→manifest→runtime, il Flutter non viene mai forkato per tenant
- [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md) §9 — pipeline completa passo-passo
- [`docs/Architecture/27-white-label-tecnico.md`](../Architecture/27-white-label-tecnico.md) — design tecnico originale
- [`docs/Architecture/11-strategia-white-label.md`](../Business/11-strategia-white-label.md) — strategia di prodotto

Chi lo usa: Control Room (configurazione), backend (`Branding`+`AppFactory`), app Flutter (consumo runtime via `GET /api/v1/app/config`).
