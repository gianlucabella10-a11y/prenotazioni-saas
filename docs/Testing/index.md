# docs/Testing/ — Strategia e stato dei test

Contenuto: `REAL_DEVICE_TEST.md` (checklist attiva per test su device Android fisico, consolida anche `REAL_DEVICE_TEST_GUIDE.md`).

**Il riferimento esaustivo e verificato non è duplicato qui** — vive in:
- [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md) §7.8 — copertura test Flutter per feature, lacune esplicite
- [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) — 45 test backend / 13 test Flutter, breakdown per modulo
- [`PROJECT_STANDARD.md`](../../PROJECT_STANDARD.md) §5 — standard obbligatori per nuovi test (isolamento tenant, test di architettura, copertura schermate)

Chi lo usa: sviluppatori backend/mobile, CI (`ci.yml` esegue `artisan test` e `flutter test`).
