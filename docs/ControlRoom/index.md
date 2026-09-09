# docs/ControlRoom/ — Pannello interno super-admin

Contenuto: `CONTROL_ROOM_OPERATOR_GUIDE.md` (guida operativa attiva per l'operatore non tecnico).

**Il riferimento esaustivo e verificato non è duplicato qui** — vive in:
- [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) §Fase 6 — cosa si può fare oggi realmente
- [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md) §5 — ogni schermata, percorso, stato
- [`docs/archive/control-room/`](../archive/control-room/) — pianificazione storica (Implementation Plan, Readiness), superata

Chi lo usa: titolare della piattaforma (super-admin). Dipende da: `TenantManagement`, `AppFactory`, `Branding` (vedi `PROJECT_DEPENDENCIES.md` §5). Nessun modulo dipende da `ControlRoom` — è una superficie di presentazione, non un servizio consumato da altri.
