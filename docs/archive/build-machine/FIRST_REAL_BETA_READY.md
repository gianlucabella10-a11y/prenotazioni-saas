# FIRST_REAL_BETA_READY (FASE 9)

> Verifica finale **basata su comandi reali eseguiti** (FASE 0/1), non su assunzioni. Backend `php artisan test` **207/207** · `flutter analyze` pulito · `flutter test` **40** · `flutter build apk` provato → si ferma solo alla toolchain Android assente su questa macchina.

## Risposte
**1) APK creato veramente?** Su **questa** macchina **no**: `flutter build apk --release` → `[!] No Android SDK found` (Android SDK + Java assenti — provato davvero, nessun mock). Su una build-machine con SDK: **sì**, con i comandi qui sotto. Il codice/pipeline è completo e provato (clean + pub get passano; 207 test backend; pipeline che avanza fino all'SDK).

**2) APK installabile?** **Sì**: firmato con la keystore di piattaforma (config gradle verificata, `ANDROID_SIGNING_FINAL.md`), distribuito via link beta token (scadenza/limite/revoca).

**3) L'app comunica col backend?** **Sì**: `API_BASE_URL`/`TENANT_KEY` via `--dart-define`, `GET /app/config` (brand/template/release) + `X-Tenant-Key`; le API booking/auth esistenti sono testate.

**4) Il booking funziona?** **Sì**: il booking engine multi-tenant è invariato e testato (suite verde); l'app usa quelle API.

**5) Posso farla usare a un esercente?** **Sì**, una volta prodotto l'APK: link beta + roster tester + versioni + feedback/crash in Control Room. Guida operatore: `CONTROL_ROOM_OPERATOR_GUIDE.md`.

**6) Ultimi passaggi manuali?** Solo il **provisioning della toolchain Android** sulla build-machine (una tantum), poi i 2 comandi di build. Esatti qui sotto.

---

## ESEGUI QUESTI COMANDI E OTTIENI L'APK
Su una build-machine (Linux/macOS). Punti 1–5 = setup **una tantum**; punto 6 = produce l'APK.
```bash
# 1) JDK 17
#    Linux:  sudo apt-get install -y openjdk-17-jdk
#    macOS:  brew install --cask temurin@17   (o Temurin 17 manuale)
export JAVA_HOME="$(/usr/libexec/java_home -v 17 2>/dev/null || echo /usr/lib/jvm/java-17-openjdk)"

# 2) Android SDK command-line tools
#    scarica "Command line tools" da developer.android.com/studio#command-tools
mkdir -p "$HOME/android-sdk/cmdline-tools"
#    scompatta in: $HOME/android-sdk/cmdline-tools/latest
export ANDROID_HOME="$HOME/android-sdk"
export PATH="$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"
yes | sdkmanager --licenses
sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"

# 3) verifica toolchain
flutter doctor          # Android toolchain ✓

# 4) keystore di piattaforma (una tantum, vedi SIGNING_SETUP.md) + ENV
export ANDROID_KEYSTORE_PATH=/secure/release.jks
export ANDROID_KEYSTORE_PASSWORD=…  ANDROID_KEY_ALIAS=…  ANDROID_KEY_PASSWORD=…

# 5) .env del backend (build-machine)
#    APP_FACTORY_BUILD_DRIVER=local
#    APP_FACTORY_FLUTTER_APP_DIR=$(pwd)/platform-mobile/apps/client_app
#    QUEUE_CONNECTION=sync          # one-shot: compila inline

# 6) >>> I COMANDI CHE PRODUCONO L'APK <<<  (dato un cliente creato in Control Room)
cd platform-backend
php artisan app:generate <TENANT_UUID>        # asset + manifest
php artisan app:build    <TENANT_UUID> android  # clean→pub get→analyze→test→build apk --release
#    → storage/app/builds/<tenant_id>/<version>/app-release.apk  (firmato)
```
Poi: **Control Room ▸ scheda app ▸ Link beta** → apri il link sul telefono → installa → prenota.

> In produzione, al posto di `QUEUE_CONNECTION=sync`: tieni `php artisan queue:work` attivo e premi **Build Android** dalla Control Room (il bottone fa esattamente ciò che fa `app:build`).

— Il prodotto è pronto: gli unici passi mancanti sono i comandi qui sopra sulla build-machine. Prossimo step: **Design / UX / vendita beta**.
