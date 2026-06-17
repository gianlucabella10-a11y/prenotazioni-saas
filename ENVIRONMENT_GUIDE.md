# ENVIRONMENT_GUIDE

> Separazione degli ambienti per la piattaforma white-label. **Mai mischiare** configurazioni: ogni ambiente ha il suo `.env` (backend) e i suoi `--dart-define` (app). Riferimento chiavi: `platform-backend/.env.example`.

## Ambienti
| | LOCAL | STAGING | BETA | PRODUCTION |
|---|---|---|---|---|
| Scopo | sviluppo | QA interno | tester reali | clienti |
| `APP_ENV` | local | staging | production | production |
| `API_BASE_URL` (app) | http://127.0.0.1:8000/api/v1 | https://staging-api… | https://api… | https://api… |
| DB | sqlite | managed | managed | managed (HA) |
| `QUEUE_CONNECTION` | sync/database | database | database | database/redis |
| Storage build/artifact | `local` | `local`/s3 | s3 (privato) | s3 (privato) |
| Firebase (beta) | — | opzionale | **sì** | — |
| Store | — | — | — | Play/App Store |
| Log | stack/debug | stack | stack | stack + Sentry |

## Backend (`.env`)
Chiavi App Factory (in `.env.example`):
```
APP_FACTORY_API_BASE_URL=…        # URL che la build dell'app userà (--dart-define)
APP_FACTORY_BUILD_DRIVER=manual   # manual | github | local
APP_FACTORY_GITHUB_REPO=…         # driver github
APP_FACTORY_GITHUB_TOKEN=…
APP_FACTORY_FLUTTER_APP_DIR=…     # driver local: path app Flutter
APP_FACTORY_ARTIFACT_DISK=local   # disco privato per builds/{tenant}/{version}/
APP_FACTORY_BUILD_TIMEOUT=1800
FIREBASE_APP_ID_ANDROID=…         # beta (Firebase App Distribution)
FIREBASE_SERVICE_ACCOUNT_JSON=…
```
**Regola**: i segreti vivono SOLO nel `.env` dell'ambiente o nei GitHub Secrets, mai nel repo.

## App Flutter (compile-time)
Per ogni build (lo imposta la pipeline dal manifest del tenant):
```
--dart-define=ENV=production
--dart-define=API_BASE_URL=<per-ambiente>
--dart-define=TENANT_KEY=<tenant>
--dart-define=APP_NAME=<app>
--dart-define=TEMPLATE=<skin>
```
Identità nativa (per-tenant): `-PAPP_ID/-PAPP_NAME` (Android), `Tenant.xcconfig` (iOS).

## Coda (worker)
In STAGING/BETA/PRODUCTION serve un worker attivo per processare le build:
```
php artisan queue:work --queue=default --tries=1 --timeout=2000
```
(In LOCAL/test la coda è `sync`: il job gira inline.)

## Storage degli artifact
`builds/{tenant_id}/{version}/app-release.apk` sul disco `APP_FACTORY_ARTIFACT_DISK` (privato). Gli APK **non** sono pubblici: si scaricano solo via link firmato (vedi `BETA_RELEASE.md`).

## CI/CD
`.github/workflows/ci.yml` gira su `main` (production) e `staging`. Build/distribuzione in `app-factory-build` (+ step beta Firebase). I segreti sono per-ambiente (GitHub Environments).
