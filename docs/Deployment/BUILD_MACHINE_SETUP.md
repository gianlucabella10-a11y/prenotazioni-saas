# BUILD_MACHINE_SETUP

> Come configurare da zero una **build-machine** (o runner CI) per produrre APK reali. Obiettivo: un tecnico esterno la configura seguendo questi passi.

## Requisiti
- Linux/macOS, 8+ GB RAM, ~20 GB disco.
- Accesso al repo del progetto.

## 1. Toolchain
```bash
# Java 17
sudo apt-get install -y openjdk-17-jdk      # (macOS: brew install openjdk@17)

# Flutter SDK 3.44.2
git clone https://github.com/flutter/flutter.git -b 3.44.2 ~/flutter
export PATH="$HOME/flutter/bin:$PATH"

# Android SDK (command line tools) + componenti
mkdir -p ~/android-sdk/cmdline-tools
# scarica cmdline-tools da developer.android.com, scompatta in ~/android-sdk/cmdline-tools/latest
export ANDROID_HOME="$HOME/android-sdk"
export PATH="$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"
yes | sdkmanager --licenses
sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"
```

## 2. PATH / variabili (persistenti in ~/.bashrc o ~/.zshrc)
```bash
export PATH="$HOME/flutter/bin:$PATH"
export ANDROID_HOME="$HOME/android-sdk"
export PATH="$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"
```

## 3. Verifica
```bash
flutter --version          # 3.44.2
flutter doctor             # Android toolchain ✓ , Java ✓
echo $ANDROID_HOME         # impostato
```

## 4. Progetto + firma
```bash
cd platform-backend
composer install --no-dev
cp .env.example .env && php artisan key:generate && php artisan migrate --force
# .env: configura DB/queue/storage + App Factory:
#   APP_FACTORY_BUILD_DRIVER=local
#   APP_FACTORY_FLUTTER_APP_DIR=/ABS/PATH/platform-mobile/apps/client_app
#   ANDROID_KEYSTORE_PATH/PASSWORD, ANDROID_KEY_ALIAS/PASSWORD  (vedi SIGNING_SETUP.md)
```

## 5. Worker della coda
La build gira come job. In produzione tieni un worker attivo:
```bash
php artisan queue:work --tries=1 --timeout=2000
```
Per un one-shot puoi usare `QUEUE_CONNECTION=sync` (la build gira inline al comando).

## 6. Lancia una build
```bash
php artisan app:generate <TENANT_UUID>     # prepara pacchetto (asset + manifest)
php artisan app:build <TENANT_UUID> android  # clean → pub get → analyze → test → build apk
# Artifact: storage/app/builds/<tenant_id>/<version>/app-release.apk
```
Se l'Android SDK non è configurato, la build **fallisce con il log reale** (nessun successo simulato), leggibile dalla Control Room.

Riferimenti: `SIGNING_SETUP.md`, `ENVIRONMENT_FINAL.md`, `REAL_BETA_SHIP_REPORT.md`.
