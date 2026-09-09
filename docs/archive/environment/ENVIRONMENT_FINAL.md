# ENVIRONMENT_FINAL

> Configurazione finale degli ambienti per la beta. Sintesi operativa (dettagli build-machine in `BUILD_MACHINE_SETUP.md`, secrets in `SIGNING_SETUP.md`/`APP_FACTORY_RELEASE_SECRETS.md`).

## Matrice
| | STAGING | BETA |
|---|---|---|
| `APP_ENV` | staging | production |
| API (`APP_FACTORY_API_BASE_URL`) | https://staging-api… | https://beta-api… |
| DB | managed | managed |
| `QUEUE_CONNECTION` | database (+ worker) | database (+ worker) · `sync` per one-shot |
| Storage artifact (`APP_FACTORY_ARTIFACT_DISK`) | local/s3 | s3 privato |
| Build driver | local/github | **local** (build-machine) o github (CI) |
| Firebase | opzionale | App Distribution (opz.) / Crashlytics (opz.) |
| Monitoring | Sentry | Sentry (crash + breadcrumb) |

## API
- L'app punta all'`API_BASE_URL` iniettato a build-time (`--dart-define`), impostato dal manifest del tenant (`APP_FACTORY_API_BASE_URL`).
- Header `X-Tenant-Key` risolve il tenant; `/app/config` serve brand/template/release.

## Storage
- Manifest/pacchetti su disco privato (`APP_FACTORY_MANIFEST_DISK`/`export_disk`).
- APK in `builds/{tenant}/{version}/app-release.apk` su `APP_FACTORY_ARTIFACT_DISK` (privato; mai esposto direttamente — solo via token).

## Queue
- Le build sono job: serve un worker attivo (`php artisan queue:work`). Per un one-shot sulla build-machine: `QUEUE_CONNECTION=sync`.

## Firebase (opzionale)
- App Distribution (Android beta): `FIREBASE_APP_ID_ANDROID`, `FIREBASE_SERVICE_ACCOUNT_JSON`.
- Crashlytics: progetto Firebase (in alternativa/aggiunta a Sentry).

## Monitoring
- **Sentry** (`sentry_flutter`) per crash/exception con contesto tenant/versione.
- Control Room: build log/errori, feedback, download count, dashboard flotta.

## Secrets
Tutti via ENV / GitHub Environments / vault: `APP_FACTORY_*`, `ANDROID_KEYSTORE_*`, `FIREBASE_*`, `PLAY_SERVICE_ACCOUNT_JSON`, `APP_STORE_CONNECT_*`, Sentry DSN. Mai nel repository.
