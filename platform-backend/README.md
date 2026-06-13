# Platform Backend — Piattaforma White Label Prenotazioni

Backend Laravel della piattaforma multi-tenant white label per professionisti su appuntamento. Implementa la progettazione tecnica dei documenti `../docs/20-33`.

## Stack

| Componente | Tecnologia |
|---|---|
| Runtime | PHP 8.4, Laravel 13 |
| Database | MySQL 8 (produzione) / SQLite (test) |
| Cache, code, lock | Redis (produzione) |
| Storage asset | Amazon S3 |
| Push | Firebase Cloud Messaging (HTTP v1) |
| Email | AWS SES via mailer Laravel |

## Architettura in breve

- **Monolite modulare DDD** (`app/Modules/<Context>/{Domain,Application,Infrastructure,Presentation}`): Scheduling (core), Catalog, Staff, Customers, Branding, Notifications, TenantManagement — confini dettagliati in `../docs/23`.
- **Multi-tenant a database condiviso** con isolamento a 5 livelli (`../docs/28`): `TenantContext` immutabile per request, global scope automatico (`BelongsToTenant`), fail-closed senza contesto, bypass esplicito per il codice di piattaforma, suite di test d'isolamento.
- **White label runtime**: l'app client legge `GET /api/v1/app/config` (tema, feature flag, sedi) con ETag; le modifiche brand sono effettive senza rebuild (`../docs/27`).
- **Booking atomico**: transazione con lock pessimistico + vincolo UNIQUE anti-doppia prenotazione; orari ricorrenti in ora locale + timezone IANA, istanti in UTC (`../docs/30`).
- **Notifiche outbox**: il DB è la fonte di verità, job idempotenti, consenso marketing verificato al send (`../docs/29`).

## Setup locale

Prerequisiti: PHP ≥ 8.3 con estensioni standard (openssl, pdo_sqlite/pdo_mysql, mbstring), Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:generate-keys          # coppia RS256 per i token (solo dev)
php artisan migrate
php artisan db:seed --class=PlanSeeder # piani commerciali (idempotente)
php artisan serve
```

Variabili d'ambiente principali (oltre alle standard Laravel):

| Variabile | Scopo |
|---|---|
| `JWT_PRIVATE_KEY_BASE64` / `JWT_PUBLIC_KEY_BASE64` | Chiavi RS256 (produzione: secrets manager). In alternativa `JWT_*_KEY_PATH` |
| `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL` | Durate token (default 900s / 30gg) |
| `RATE_LIMIT_*` | Limiti per minuto (auth, availability, default, tenant) |
| `FCM_PROJECT_ID`, `FCM_CREDENTIALS_PATH` (in `config/services.php`) | Push Firebase |

## Test

```bash
php artisan test                 # unit + feature (SQLite in-memory)
php artisan test --testsuite=Unit
```

La suite copre: dominio puro (intervalli, availability con casi DST, macchina a stati, TOTP RFC 6238, contrasto WCAG), flussi auth (rotazione refresh + rilevamento riuso, MFA enrollment/verify), booking end-to-end (conflitti 409, idempotenza, cutoff, request/approve), **isolamento multi-tenant** (RNF-21), white label config (ETag, sospensione), provisioning e RBAC, quote di piano.

Nota copertura: la misurazione richiede `pcov`/`xdebug` (non inclusi nel runtime statico usato in sviluppo locale); il job CI di riferimento esegue `php artisan test --coverage --min=90` su immagine con pcov.

## Comandi operativi

| Comando | Scopo |
|---|---|
| `php artisan jwt:generate-keys [--force]` | Genera la coppia RS256 (solo ambienti non-prod) |
| `php artisan notifications:dispatch-due` | Sweep outbox → coda (schedulato ogni 15 min) |
| `php artisan db:seed --class=PlanSeeder` | Allinea i piani commerciali (idempotente, ogni deploy) |

## Superfici API (prefisso `/api/v1`)

| Gruppo | Auth | Contenuto |
|---|---|---|
| Pubblica app cliente | `X-Tenant-Key` | `/app/config` (ETag), `/catalog/services`, `/staff`, `/availability`, `/auth/register|login` |
| Token lifecycle | refresh token | `/auth/refresh` (rotazione), `/auth/logout` |
| MFA | token scope-mfa | `/auth/mfa/setup|confirm|verify` |
| Cliente | JWT customer | `/appointments` CRUD + cancel |
| Gestione | JWT staff/admin | `/manage/agenda`, transizioni appuntamento, `/manage/services|staff|brand` |
| Piattaforma | JWT super admin | `/admin/tenants` (provisioning, sospensione, riattivazione) |

Formato errori: `{"error": {"code", "message", "details?"}}` con codici stabili (`slot_unavailable`, `cutoff_passed`, `quota_exceeded`, `insufficient_contrast`, …). Idempotenza: header `Idempotency-Key` obbligatorio su `POST /appointments`.

## Deployment (sintesi — dettagli in `../docs/32`)

Tre processi dallo stesso artefatto: **web** (php-fpm dietro ALB), **worker** (Horizon, code `critical|default|bulk`), **scheduler** (singolo task, `onOneServer`). Migrazioni expand/contract prima del rollout. Mai eseguire più di uno scheduler.
