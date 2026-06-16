#!/usr/bin/env bash
#
# App Factory FASE 2B/2C — prepara la build per-tenant da un manifest 2.0
# (prodotto da `php artisan app:generate {tenant}`). Imposta identità nativa,
# genera icone/splash dal logo e stampa/esegue il comando di build parametrico.
#
# FIRMA (FASE 2C): la firma Android è gestita dal `build.gradle.kts` quando i
# segreti del keystore sono presenti come env (ANDROID_KEYSTORE_PATH/…); senza
# env si firma in debug (build locale invariata). iOS usa la firma configurata
# dall'ambiente (Xcode/CI con account Apple del cliente). L'UPLOAD sugli store
# NON è fatto qui: lo esegue il workflow CI (.github/workflows/app-factory-build.yml).
#
# Uso:
#   tool/app_factory/make_app.sh --manifest <m.json> --assets-dir <dir> [--platform android|ios] [--build]
#
#   --manifest    JSON 2.0 (es. storage/app/private/app_factory/<uuid>/manifest-vN.json)
#   --assets-dir  radice da cui risolvere i path asset del manifest
#                 (es. storage/app/public per il disco 'public')
#   --platform    android (default) → AAB · ios → IPA
#   --build       esegue la preparazione COMPLETA (genera icone/splash, scrive
#                 l'identità nativa) e poi la build del pacchetto.
#                 Senza, fa un DRY-RUN: legge il manifest e stampa il piano +
#                 il comando di build, SENZA modificare alcun file.

set -euo pipefail

usage() { echo "Uso: $0 --manifest <path.json> --assets-dir <dir> [--platform android|ios] [--build]"; exit 1; }

MANIFEST=""; ASSETS_DIR=""; DO_BUILD=0; PLATFORM="android"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --manifest) MANIFEST="${2:-}"; shift 2 ;;
    --assets-dir) ASSETS_DIR="${2:-}"; shift 2 ;;
    --platform) PLATFORM="${2:-}"; shift 2 ;;
    --build) DO_BUILD=1; shift ;;
    *) usage ;;
  esac
done

[[ -z "$MANIFEST" || -z "$ASSETS_DIR" ]] && usage
[[ "$PLATFORM" == "android" || "$PLATFORM" == "ios" ]] || { echo "ERRORE: --platform deve essere android|ios."; exit 1; }
command -v jq >/dev/null 2>&1 || { echo "ERRORE: 'jq' richiesto."; exit 1; }
[[ -f "$MANIFEST" ]] || { echo "ERRORE: manifest non trovato: $MANIFEST"; exit 1; }

APP_ROOT="$(cd "$(dirname "$0")/../.." && pwd)" # → client_app/
BUILD_DIR="$APP_ROOT/tool/app_factory/build"

APP_NAME="$(jq -r '.app_identity.name // .app_identity.store_name // "App"' "$MANIFEST")"
BUNDLE_ID="$(jq -r '.app_identity.bundle_id' "$MANIFEST")"
PACKAGE_NAME="$(jq -r '.app_identity.package_name' "$MANIFEST")"
TENANT_KEY="$(jq -r '.runtime.dart_define.TENANT_KEY' "$MANIFEST")"
API_URL="$(jq -r '.runtime.dart_define.API_BASE_URL' "$MANIFEST")"
TEMPLATE="$(jq -r '.template.template_code // "default"' "$MANIFEST")"
ICON_SRC="$(jq -r '(.branding.icons[]? | select(.variant=="ios_1024") | .path) // (.branding.icons[-1]?.path) // empty' "$MANIFEST")"
SPLASH_SRC="$(jq -r '(.branding.splash[-1]?.path) // empty' "$MANIFEST")"

echo "== App Factory · $APP_NAME =="
echo "  bundle id : $BUNDLE_ID"
echo "  package   : $PACKAGE_NAME"
echo "  template  : $TEMPLATE"
[[ -n "$ICON_SRC" && -f "$ASSETS_DIR/$ICON_SRC" ]] && echo "  icona     : OK ($ICON_SRC)" || echo "  icona     : ASSENTE (esegui 'php artisan app:generate')"
[[ -n "$SPLASH_SRC" && -f "$ASSETS_DIR/$SPLASH_SRC" ]] && echo "  splash    : OK ($SPLASH_SRC)" || echo "  splash    : ASSENTE"

# Comando di build parametrico. Le --dart-define sono comuni alle piattaforme;
# l'identità Android passa via Gradle property, quella iOS via pbxproj/Info.plist.
DART_DEFINES=(
  "--dart-define=ENV=production"
  "--dart-define=API_BASE_URL=${API_URL}"
  "--dart-define=TENANT_KEY=${TENANT_KEY}"
  "--dart-define=APP_NAME=${APP_NAME}"
  "--dart-define=TEMPLATE=${TEMPLATE}")

if [[ "$PLATFORM" == "ios" ]]; then
  BUILD_CMD=(flutter build ipa --release "${DART_DEFINES[@]}")
  SIGN_NOTE="firma iOS dall'ambiente (Xcode/CI, account Apple del cliente)"
else
  BUILD_CMD=(flutter build appbundle --release
    "-PAPP_ID=${PACKAGE_NAME}" "-PAPP_NAME=${APP_NAME}" "${DART_DEFINES[@]}")
  SIGN_NOTE="firma release se ANDROID_KEYSTORE_PATH è in env, altrimenti debug"
fi

echo "== Comando di build (${PLATFORM}: ${SIGN_NOTE}) =="
printf '%q ' "${BUILD_CMD[@]}"; echo

if [[ "$DO_BUILD" -eq 0 ]]; then
  echo "(dry-run: nessun file modificato. Aggiungi --build per preparare e compilare.)"
  exit 0
fi

# ---- Da qui in poi: mutazioni (solo con --build, idealmente su runner CI) ----
cd "$APP_ROOT"
mkdir -p "$BUILD_DIR"

if [[ -n "$ICON_SRC" && -f "$ASSETS_DIR/$ICON_SRC" ]]; then
  cp "$ASSETS_DIR/$ICON_SRC" "$BUILD_DIR/icon.png"
  dart run flutter_launcher_icons -f flutter_launcher_icons.yaml
fi
if [[ -n "$SPLASH_SRC" && -f "$ASSETS_DIR/$SPLASH_SRC" ]]; then
  cp "$ASSETS_DIR/$SPLASH_SRC" "$BUILD_DIR/splash.png"
  dart run flutter_native_splash:create --path=flutter_native_splash.yaml
fi

# iOS: identità a build-time (il repo committato resta ai default).
PBX="ios/Runner.xcodeproj/project.pbxproj"
if [[ -f "$PBX" ]]; then
  # Solo l'app Runner: i RunnerTests hanno suffisso .RunnerTests e non matchano.
  sed -i.bak "s/PRODUCT_BUNDLE_IDENTIFIER = com\.platform\.clientApp;/PRODUCT_BUNDLE_IDENTIFIER = ${BUNDLE_ID};/g" "$PBX"
  rm -f "$PBX.bak"
fi
if [[ -x /usr/libexec/PlistBuddy ]]; then
  /usr/libexec/PlistBuddy -c "Set :CFBundleDisplayName ${APP_NAME}" ios/Runner/Info.plist >/dev/null 2>&1 || true
fi

"${BUILD_CMD[@]}"
