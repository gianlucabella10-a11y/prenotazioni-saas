# docs/API/ — Contratto REST (`/api/v1`)

Contenuto: `25-api-rest.md` (design tecnico originale — endpoint, versioning, idempotenza, rate limiting).

**Il riferimento esaustivo e verificato del contratto reale implementato non è duplicato qui** — vive in:
- [`PROJECT_FREEZE_STATE.md`](../../PROJECT_FREEZE_STATE.md) §6 — tabella endpoint completa, autenticazione per gruppo, pattern di risposta (3 forme non unificate — gap noto)
- [`REAL_PROJECT_STATE.md`](../../REAL_PROJECT_STATE.md) — guard JWT custom, non Sanctum

**Nota**: non esiste un contratto OpenAPI/Swagger pubblicato (confermato NOT FOUND in entrambi i documenti sopra) — questo file e il codice dei controller sono oggi l'unica fonte di verità del contratto. Vedi `PROJECT_EVOLUTION_ROADMAP.md` punto 16.

Chi lo usa: app Flutter (client), CI/integrazioni esterne, ogni sviluppatore backend che aggiunge un endpoint.
