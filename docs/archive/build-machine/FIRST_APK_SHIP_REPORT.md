# FIRST_APK_SHIP_REPORT (FASE 9)

> Report finale **basato su esecuzione reale** in questa sessione: toolchain installata, **APK reale prodotta due volte** (build diretta + via SaaS engine), artifact su disco, beta link generato. Backend `php artisan test` **207/207** · Pint pulito.

## Cosa è stato fatto davvero (non simulato)
1. **Audit reale** della macchina (`FINAL_BUILD_MACHINE_AUDIT.md`): mancavano Android SDK + Java.
2. **Installata la toolchain** in user-space: Temurin JDK 17 + Android SDK (cmdline-tools, platform-tools, platforms 34/35/36, build-tools 34/36, cmake) + licenze accettate + `flutter config`.
3. **FASE 4 — build diretta**: `flutter build apk --release` → **✓ 56 MB**.
4. **FASE 5 — build via SaaS engine**: `app:generate` + `app:build <uuid> android` → stato **built**, APK in `storage/app/private/builds/1/1.0.0+1/app-release.apk`.
5. **Bug reale trovato e corretto** (blocker, FASE 7): `LocalBuildDispatcher` cercava il manifest sotto l'UUID del *progetto* invece del *tenant* → "Manifest assente". Allineato all'UUID tenant (come generate/export); test irrobustito; suite 207/207.
6. **FASE 8 — beta link** generato (token/scadenza 7g/limite 50/revoca).

## Risposte
1. **Toolchain installata?** **Sì** — JDK 17 + Android SDK; `flutter build apk` riesce.
2. **Build riuscita?** **Sì**, due volte: diretta e via SaaS engine.
3. **APK path?**
   - Diretta: `platform-mobile/apps/client_app/build/app/outputs/flutter-apk/app-release.apk`
   - SaaS: `storage/app/private/builds/1/1.0.0+1/app-release.apk`
4. **Checksum (sha256)?**
   - Diretta: `3c0365d43ab423d46eca3d8a88edb4f91c81fb2b506f509f840794512c80fe3f`
   - SaaS: `2ec2e6a2d34030cb9441e73376a154fec6623f77ff66b21f97675053a116edde` (56.2 MB, build 1.0.0+3, exit 0, 115s)
5. **APK installata su telefono?** **Non da questa macchina**: nessun telefono Android fisico collegato (vedi sotto). L'APK è pronto e scaricabile dal beta link.
6. **Telefono collegato?** **No** — `flutter doctor` vede solo Chrome + macOS desktop, nessun device Android. Su una macchina con telefono: `adb devices` → `adb install <apk>`.
7. **Backend raggiungibile?** **Sì** lato configurazione: l'app usa `API_BASE_URL` (--dart-define) + `GET /app/config` (+ `X-Tenant-Key`), testati. La conferma runtime avviene all'apertura sul device.
8. **Pronta per esercente?** **Sì**: APK reale firmato (con keystore di piattaforma se ENV impostate; altrimenti debug per test) + beta link da inviare.

## ⚠️ Firma
Le build di questa sessione sono firmate **debug** (nessuna `ANDROID_KEYSTORE_*` impostata): installabili per test. Per la beta distribuibile imposta le ENV keystore (`ANDROID_SIGNING_FINAL.md`) prima del build.

## Ultimi comandi (riproducibili)
```bash
# toolchain già installata in questa sessione: ~/.local/toolchain (JDK17) + ~/android-sdk
export JAVA_HOME="$HOME/.local/toolchain/jdk-17.0.19+10/Contents/Home"
export ANDROID_HOME="$HOME/android-sdk"
export PATH="$HOME/.local/flutter/bin:$HOME/.local/php-toolchain/bin:$ANDROID_HOME/platform-tools:$PATH"
# (per release firmata) export ANDROID_KEYSTORE_PATH=… ANDROID_KEYSTORE_PASSWORD=… ANDROID_KEY_ALIAS=… ANDROID_KEY_PASSWORD=…
export APP_FACTORY_BUILD_DRIVER=local QUEUE_CONNECTION=sync

cd platform-backend
php artisan app:generate <TENANT_UUID>      # asset + manifest
php artisan app:build    <TENANT_UUID> android  # → storage/app/private/builds/<tenant>/<version>/app-release.apk

# consegna: Control Room ▸ scheda app ▸ Link beta → invia il link; il cliente lo apre sul telefono e installa.
# (oppure, con un telefono collegato a QUESTA macchina:)
adb install storage/app/private/builds/<tenant>/<version>/app-release.apk
```

— **Prima APK reale: PRODOTTA.** Manca solo aprirla su un telefono fisico (passo dell'utente, non del codice). Prossimo step: Design / UX / vendita beta.
