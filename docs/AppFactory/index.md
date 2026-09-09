# docs/AppFactory/ — Motore di generazione app white-label

Contenuto: `APP_FACTORY_RELEASE_SECRETS.md` (riferimento attivo — segreti CI/GitHub Actions richiesti dalla pipeline di build).

**Il riferimento esaustivo e verificato della pipeline non è duplicato qui** — vive in:
- [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) §Fase 5, 7, 10 — creazione tenant → identità app → asset → manifest → build → APK
- [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md) §8, §10 — Build Engine e App Factory pipeline dettagliate
- [`docs/archive/app-factory/`](../archive/app-factory/) — pianificazione storica (Master Plan, fasi 1-3), superata dall'implementazione

Chi lo usa/richiama: Control Room (`/control-room/apps/*`), CI (`app-factory-build.yml`, `app-factory-batch.yml`). Dipende da: modulo `TenantManagement` (identità tenant), `Branding` (asset). Output: manifest JSON, APK firmato (debug — vedi gap noto), link beta.
