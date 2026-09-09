#!/bin/bash
# ============================================================================
#  MY DAILY BUSINESS CONTROL CENTER — avvio one-click
#  Doppio click (macOS) → backend + queue worker + scheduler + Control Room.
#  Ctrl-C in questa finestra ferma tutto.
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")/platform-backend" || { echo "platform-backend non trovato"; exit 1; }

# --- Toolchain (PHP, Flutter, JDK, Android SDK) -----------------------------
export PATH="$HOME/.local/php-toolchain/bin:$HOME/.local/flutter/bin:$HOME/android-sdk/platform-tools:$PATH"
export JAVA_HOME="$HOME/.local/toolchain/jdk-17.0.19+10/Contents/Home"
export ANDROID_HOME="$HOME/android-sdk"
[ -f "$HOME/.local/keystore/keystore.env" ] && source "$HOME/.local/keystore/keystore.env"   # firma release

# --- App Factory: build locale reale ----------------------------------------
export APP_FACTORY_BUILD_DRIVER="${APP_FACTORY_BUILD_DRIVER:-local}"
export APP_FACTORY_FLUTTER_APP_DIR="$(cd .. && pwd)/platform-mobile/apps/client_app"

echo "▶ MY BUSINESS CONTROL CENTER"
echo "  PHP:     $(php -r 'echo PHP_VERSION;')"
echo "  Flutter: $(flutter --version 2>/dev/null | head -1 || echo 'n/d')"
echo "  Java:    $("$JAVA_HOME/bin/java" -version 2>&1 | head -1)"
echo "  Driver:  $APP_FACTORY_BUILD_DRIVER"

php artisan storage:link >/dev/null 2>&1 || true

# --- Servizi di supporto (background) ---------------------------------------
mkdir -p storage/logs
php artisan queue:work --tries=1 --timeout=2400 >> storage/logs/worker.log 2>&1 &
WORKER=$!; echo "  queue worker  PID $WORKER  (log: storage/logs/worker.log)"
php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &
SCHED=$!;  echo "  scheduler     PID $SCHED   (log: storage/logs/scheduler.log)"

# Ferma i servizi quando chiudi il server (Ctrl-C)
trap 'echo; echo "stop servizi…"; kill $WORKER $SCHED 2>/dev/null || true' EXIT

# --- Apri la Control Room nel browser ---------------------------------------
( sleep 2; open "http://127.0.0.1:8000/control-room" >/dev/null 2>&1 || true ) &

echo
echo "  ✅ CONTROL ROOM:  http://127.0.0.1:8000/control-room"
echo "  (Ctrl-C per fermare tutto)"
echo

# --- Backend in primo piano (binda 0.0.0.0 → raggiungibile anche in LAN) -----
php artisan serve --host=0.0.0.0 --port=8000
