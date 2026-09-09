# APP_FACTORY_RELEASE_SECRETS

> Segreti richiesti dal workflow CI `.github/workflows/app-factory-build.yml` (FASE 2C). **Nessun segreto vive nel repo**: vanno impostati come *GitHub Actions secrets* (repo → Settings → Secrets and variables → Actions). La firma Android è letta da env dal `build.gradle.kts`; iOS usa l'account Apple Developer **del cliente**.

## 1. Accesso al backend (genera manifest + tracking)
La CI esegue `php artisan app:generate` e `php artisan app:build-record` sul server via SSH.

| Secret | Cos'è | Dove ottenerlo |
|---|---|---|
| `BACKEND_SSH_HOST` | host del server Laravel | infra/hosting |
| `BACKEND_SSH_USER` | utente SSH (deploy, no root) | infra/hosting |
| `BACKEND_SSH_KEY` | chiave privata SSH (ed25519) abilitata sul server | `ssh-keygen -t ed25519`; pubblica in `~/.ssh/authorized_keys` del server |
| `BACKEND_PATH` | path assoluto dell'app sul server (dove gira `artisan`) | infra/hosting |

## 2. Firma Android (Google Play)
| Secret | Cos'è | Dove ottenerlo |
|---|---|---|
| `ANDROID_KEYSTORE_BASE64` | keystore `.jks` in base64 | `base64 -i release.jks` (mai committare il `.jks`) |
| `ANDROID_KEYSTORE_PASSWORD` | password dello store | impostata alla creazione del keystore |
| `ANDROID_KEY_ALIAS` | alias della chiave | `keytool -genkey -v -keystore release.jks -alias <alias> -keyalg RSA -keysize 2048 -validity 10000` |
| `ANDROID_KEY_PASSWORD` | password della chiave | impostata alla creazione |
| `PLAY_SERVICE_ACCOUNT_JSON` | JSON del service account con accesso API Play | Google Play Console → API access → service account (ruolo "Release manager") |

> **Una sola chiave di firma per tutta la piattaforma** (tutte le app per-tenant): è gestita dall'account developer Play della piattaforma. Custodirla in un vault; la rotazione richiede *Play App Signing* (chiave di upload separata da quella di firma).

## 3. Firma + upload iOS (TestFlight / App Store)
Apple impone l'**account Apple Developer del cliente** per app "template" (linee guida 4.2.6/4.3): l'upload usa una API key di **quell'**account.

| Secret | Cos'è | Dove ottenerlo |
|---|---|---|
| `APP_STORE_CONNECT_ISSUER_ID` | Issuer ID dell'account | App Store Connect → Users and Access → Integrations → App Store Connect API |
| `APP_STORE_CONNECT_KEY_ID` | Key ID della API key | stesso pannello, alla creazione della chiave |
| `APP_STORE_CONNECT_PRIVATE_KEY` | contenuto del file `.p8` | scaricabile **una sola volta** alla creazione |

> Per app multi-cliente servono set di credenziali iOS **per cliente** (o un environment GitHub per tenant). La firma/profili dell'IPA sul runner macOS richiedono comunque cert + provisioning profile dell'account del cliente.

## 4. Come lanciare la pipeline
Repo → Actions → **app-factory-build** → *Run workflow*:
- `tenant_uuid`: UUID dell'App Project (dalla Control Room).
- `platform`: `android` | `ios` | `both`.
- `android_track`: `internal` (default) | `alpha` | `beta` | `production`.

La CI: genera/recupera il manifest 2.0 + asset dal backend → build firmata (`make_app.sh --build`) → upload allo store → `app:build-record` aggiorna lo stato (`published`/`failed`) visibile in Control Room.

## 5. Cosa NON è automatizzabile
- **Enrollment Apple Developer del cliente** e primo review (manuale).
- **Risposte a rejection** dello store.
- Generazione iniziale di keystore/cert (una tantum, manuale, custodita in vault).
