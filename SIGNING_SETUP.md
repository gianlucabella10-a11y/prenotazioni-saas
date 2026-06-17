# SIGNING_SETUP

> Firma Android **di piattaforma** (una sola keystore per tutte le app per-tenant in beta). Segreti **solo via ENV**, mai nel repository. Senza ENV la release è firmata in debug (non distribuibile come release).

## 1. Crea la keystore (una tantum)
```bash
keytool -genkey -v -keystore release.jks \
  -alias platform -keyalg RSA -keysize 2048 -validity 10000
```
Custodisci `release.jks` in un vault/secret store. **Non** committarla.

## 2. Variabili d'ambiente (build-machine / CI)
| ENV | Significato |
|---|---|
| `ANDROID_KEYSTORE_PATH` | path assoluto del `.jks` |
| `ANDROID_KEYSTORE_PASSWORD` | password dello **store** |
| `ANDROID_KEY_ALIAS` | alias della chiave (es. `platform`) |
| `ANDROID_KEY_PASSWORD` | password della chiave |

## 3. Come le usa Gradle
`android/app/build.gradle.kts` legge le ENV in un `signingConfig` `release`:
- se `ANDROID_KEYSTORE_PATH` è presente → firma **release** con la keystore;
- altrimenti → firma **debug** (build locale invariata, non distribuibile).
`applicationId`/nome arrivano da `-PAPP_ID`/`-PAPP_NAME` (per-tenant, dal manifest).

## 4. Aggiornabilità
L'APK è aggiornabile **solo** se firmato con la **stessa** keystore: usa sempre la keystore di piattaforma per tutte le build beta. Un APK firmato con una chiave diversa dà "App non installata" in update.

## 5. CI
Nel workflow `app-factory-build` la keystore arriva da `ANDROID_KEYSTORE_BASE64` (decodificata a runtime) + le password come secrets. Vedi `APP_FACTORY_RELEASE_SECRETS.md`.

> iOS: la firma richiede l'account Apple Developer **del cliente** (cert + provisioning profile) — fuori dalla beta Android.
