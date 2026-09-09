# ANDROID_SIGNING_FINAL

> ⚠️ **ARCHIVIATO — vedi nota di superamento in `SIGNED_APK_READY.md` in questa stessa cartella.** Lo stato di firma release descritto qui non corrisponde al comportamento verificato del codice attuale (`PROJECT_FREEZE_STATE.md` §8).
>
> Stato **verificato** della firma Android. La config esiste nel repo ed è env-based (segreti mai nel codice). Per creare la keystore vedi `docs/Deployment/SIGNING_SETUP.md`.

## Config nel repo (verificata)
`platform-mobile/apps/client_app/android/app/build.gradle.kts`:
```kotlin
signingConfigs {
    create("release") {
        val ksPath = System.getenv("ANDROID_KEYSTORE_PATH")
        if (ksPath != null && file(ksPath).exists()) {
            storeFile = file(ksPath)
            storePassword = System.getenv("ANDROID_KEYSTORE_PASSWORD")
            keyAlias = System.getenv("ANDROID_KEY_ALIAS")
            keyPassword = System.getenv("ANDROID_KEY_PASSWORD")
        }
    }
}
buildTypes {
    release {
        signingConfig = if (System.getenv("ANDROID_KEYSTORE_PATH") != null)
            signingConfigs.getByName("release") else signingConfigs.getByName("debug")
    }
}
```
- **Con** le ENV → APK **firmato release** (installabile + aggiornabile con la stessa keystore di piattaforma).
- **Senza** ENV → firma debug (build locale invariata; non distribuibile come release).
- `applicationId`/nome per-tenant da `-PAPP_ID`/`-PAPP_NAME` (dal manifest).

## ENV richieste (build-machine / CI, mai nel repo)
```
ANDROID_KEYSTORE_PATH=/secure/release.jks
ANDROID_KEYSTORE_PASSWORD=…   # password dello store
ANDROID_KEY_ALIAS=…
ANDROID_KEY_PASSWORD=…
```

## Verifica fatta
La build reale su questa macchina è arrivata fino a `[!] No Android SDK found` (FASE 0/1): il blocco è la **toolchain Android assente**, non la config di firma (che è presente e corretta). Con SDK + keystore l'APK risultante è firmato e installabile.

Riferimenti: `SIGNING_SETUP.md`, `BUILD_MACHINE_SETUP.md`.
