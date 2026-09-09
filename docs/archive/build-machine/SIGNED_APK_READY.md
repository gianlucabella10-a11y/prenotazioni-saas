# SIGNED_APK_READY (FASE 1)

> ⚠️ **ARCHIVIATO — contraddetto dallo stato verificato attuale.** `PROJECT_FREEZE_STATE.md` §8 (verifica diretta del codice sorgente di `LocalBuildDispatcher`) conferma che oggi il dispatcher `local` **non imposta** le variabili d'ambiente del keystore, quindi ogni build prodotta da quel driver ricade sulla firma debug — indipendentemente da quanto affermato in questo documento in quel momento storico. Non usare questo file come riferimento sullo stato attuale della firma.
>
> Firma **reale** verificata: l'APK non è più debug-signed. Keystore di piattaforma creato (user-space, **nessun segreto nel repo**), build rigenerata via SaaS engine e firma verificata con `apksigner`.

## Keystore (fuori dal repository)
- File: `~/.local/keystore/platform.jks` (alias `platform`, RSA 2048, validità 10000 giorni).
- ENV: `~/.local/keystore/keystore.env` (chmod 600) — `ANDROID_KEYSTORE_PATH/PASSWORD`, `ANDROID_KEY_ALIAS/PASSWORD`. Mai committato.
- Stessa keystore per tutte le app per-tenant (aggiornabilità garantita).

## Build firmata (verificata)
```
$ apksigner verify --print-certs app-release.apk
Signer #1 certificate DN: CN=Platform Beta, O=White Label SaaS, C=IT
```
→ firmato con la keystore di piattaforma (NON `CN=Android Debug`).

| Campo | Valore |
|---|---|
| APK | `storage/app/private/builds/1/1.0.0+1/app-release.apk` |
| Package id | `com.platform.t1` |
| Versione (build row) | `1.0.0+4` |
| Dimensione | **56.2 MB** |
| SHA-256 | `7d5a126e0870c06ce997baee8aea874bb7f190aa6bc564f2e40357d2c292593f` |
| Firma | release (platform keystore) ✅ |

## Riprodurre (su build-machine)
```bash
source ~/.local/keystore/keystore.env       # ANDROID_KEYSTORE_*
export JAVA_HOME=~/.local/toolchain/jdk-17.0.19+10/Contents/Home
export ANDROID_HOME=~/android-sdk
export PATH="$HOME/.local/flutter/bin:$ANDROID_HOME/platform-tools:$PATH"
export APP_FACTORY_BUILD_DRIVER=local QUEUE_CONNECTION=sync
cd platform-backend
php artisan app:build <TENANT_UUID> android
apksigner verify --print-certs storage/app/private/builds/<tenant>/<version>/app-release.apk
```
La firma è applicata da `android/app/build.gradle.kts` (signingConfig `release` dalle ENV; senza ENV → debug). Vedi `ANDROID_SIGNING_FINAL.md`.
