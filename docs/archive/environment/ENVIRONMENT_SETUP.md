# ENVIRONMENT_SETUP

> Setup operativo per ambiente. Complementa `ENVIRONMENT_GUIDE.md` (tabella di riferimento). Regola: ogni ambiente ha il proprio `.env`/secrets; **mai mischiare**.

## LOCAL (sviluppo)
```bash
cd platform-backend
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve            # http://127.0.0.1:8000
```
- DB: sqlite (default). Coda: `QUEUE_CONNECTION=sync` (i job girano inline).
- App: `flutter run --dart-define=ENV=development --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1 --dart-define=TENANT_KEY=<key>`
- Build driver: `APP_FACTORY_BUILD_DRIVER=manual` (nessuna build reale).

## STAGING (QA interno)
- DB managed; `QUEUE_CONNECTION=database`; storage `local`/s3.
- Avviare un **worker**: `php artisan queue:work --tries=1 --timeout=2000`.
- `APP_FACTORY_API_BASE_URL=https://staging-api…`; CI gira su branch `staging`.
- Build driver: `local` (su runner con Android SDK) o `github`.

## BETA (tester reali)
- Come staging +:
  - `APP_FACTORY_BUILD_DRIVER=local` su una **build-machine con Android SDK** (oppure `github` → CI).
  - `APP_FACTORY_FLUTTER_APP_DIR=/path/to/platform-mobile/apps/client_app`
  - `APP_FACTORY_ARTIFACT_DISK=local` (o s3 privato) → `builds/{tenant}/{version}/app-release.apk`.
  - Firma Android: `ANDROID_KEYSTORE_PATH/…PASSWORD/KEY_ALIAS/KEY_PASSWORD` (ENV, mai nel repo).
  - Distribuzione: link firmato (Control Room) e/o `FIREBASE_APP_ID_ANDROID` + `FIREBASE_SERVICE_ACCOUNT_JSON`.
  - Crash: Sentry già attivo (`sentry_flutter`); per Crashlytics aggiungere il progetto Firebase.

## PRODUCTION
- DB HA; coda `database`/redis con worker supervisionato (Horizon/supervisor).
- Log: stack + Sentry. Storage artifact privato. Store: Play/App Store (account; iOS = account del cliente).

## Checklist worker (staging/beta/prod)
```bash
php artisan queue:work --queue=default --sleep=1 --tries=1 --timeout=2000
```
Senza worker attivo le build restano in stato `queued`.

## Matrice chiavi (estratto `.env.example`)
| Chiave | LOCAL | STAGING/BETA | PROD |
|---|---|---|---|
| `APP_FACTORY_BUILD_DRIVER` | manual | local/github | github |
| `APP_FACTORY_ARTIFACT_DISK` | local | local/s3 | s3 |
| `QUEUE_CONNECTION` | sync | database | database/redis |
| `FIREBASE_APP_ID_ANDROID` | — | (beta) | — |
| `ANDROID_KEYSTORE_*` | — | sì | sì |

Riferimenti: `ENVIRONMENT_GUIDE.md`, `APP_FACTORY_RELEASE_SECRETS.md`, `BETA_RELEASE.md`.
