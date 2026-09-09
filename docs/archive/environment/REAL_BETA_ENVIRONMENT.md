# REAL_BETA_ENVIRONMENT

> Setup degli ambienti per una beta reale. Estende `ENVIRONMENT_SETUP.md`/`ENVIRONMENT_GUIDE.md` con i dettagli della **build-machine**. Regola: ogni ambiente ha i suoi secrets; mai mischiare.

## Ambienti
| | LOCAL | STAGING | BETA |
|---|---|---|---|
| API | http://127.0.0.1:8000/api/v1 | https://staging-api… | https://beta-api… |
| DB | sqlite | managed | managed |
| Queue | sync | database + worker | database + worker |
| Storage artifact | local | local/s3 | s3 privato |
| Build driver | manual | local/github | **local** (o github) |
| Firebase | — | opz. | App Distribution (opz.) |

## Build-machine (BETA) — requisiti reali
La compilazione APK richiede una macchina (o runner CI) con:
- **Flutter SDK** (3.44.2) e **Android SDK** (`ANDROID_HOME`/`ANDROID_SDK_ROOT`), build-tools + platform, JDK 17.
- Accesso al repo dell'app Flutter: `APP_FACTORY_FLUTTER_APP_DIR=/path/platform-mobile/apps/client_app`.
- **Worker attivo**: `php artisan queue:work --tries=1 --timeout=2000`.
- `APP_FACTORY_BUILD_DRIVER=local`, `APP_FACTORY_ARTIFACT_DISK=...` (privato).

### Firma Android (ENV, mai nel repo)
```
ANDROID_KEYSTORE_PATH=/secure/release.jks
ANDROID_KEYSTORE_PASSWORD=…
ANDROID_KEY_ALIAS=…
ANDROID_KEY_PASSWORD=…
```
Senza queste, la release è firmata in debug (non distribuibile come release). Keystore **di piattaforma** (una sola, non per-cliente).

### Verifica build-machine
```bash
flutter --version            # 3.44.x
flutter doctor               # Android toolchain ✓
echo $ANDROID_HOME           # impostato
php artisan queue:work       # worker attivo
```
Se manca l'Android SDK, la build **fallisce esplicitamente** con il log reale (nessun successo simulato).

## Secrets (per ambiente, in vault / GitHub Environments)
- App Factory: `APP_FACTORY_*` (driver, flutter_app_dir, artifact_disk, build_timeout, beta_max_downloads).
- Android: `ANDROID_KEYSTORE_*`.
- Firebase (opz.): `FIREBASE_APP_ID_ANDROID`, `FIREBASE_SERVICE_ACCOUNT_JSON`.
- Store: `PLAY_SERVICE_ACCOUNT_JSON`, `APP_STORE_CONNECT_*` (oltre la beta).
- Sentry: DSN (crash).

## Flusso BETA end-to-end
Control Room (genera + build) → worker su build-machine (`flutter build apk` firmato) → artifact su disco privato → *Link beta* (token) → esercente installa → crash (Sentry) + feedback (Control Room).

Riferimenti: `ENVIRONMENT_SETUP.md`, `APP_PROVISIONING_RUNBOOK.md`, `APP_FACTORY_RELEASE_SECRETS.md`, `BETA_RELEASE.md`.
