#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Deploy del backend Laravel sull'istanza pilota (profilo docs/32 "pilota":
# EC2 + Caddy + RDS, niente Redis). Push del codice via rsync su SSH, poi
# install/migrate/cache/restart sull'istanza. Idempotente e ri-eseguibile.
#
# Prerequisiti (una tantum, lato istanza): cloud-init server/user-data.sh
# già applicato dal Terraform (PHP 8.4, Caddy, worker, scheduler installati).
#
# Uso, dalla cartella platform-backend:
#   APP_HOST=api.tuodominio.it SSH_USER=ubuntu ../platform-infra/bin/deploy.sh
#
# Il file .env di produzione NON è in questo repo: va creato una sola volta
# sull'istanza in /var/www/platform/.env (vedi runbook DEPLOY_PILOT.md §5),
# e questo script lo preserva fra un deploy e l'altro.
# ---------------------------------------------------------------------------
set -euo pipefail

APP_HOST="${APP_HOST:?Imposta APP_HOST (es. api.tuodominio.it)}"
SSH_USER="${SSH_USER:-ubuntu}"
REMOTE_DIR="${REMOTE_DIR:-/var/www/platform}"
PHP="${REMOTE_PHP:-php8.4}"

SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(cd "${SELF_DIR}/../../platform-backend" && pwd)"

echo "▶ Deploy di ${BACKEND_DIR} → ${SSH_USER}@${APP_HOST}:${REMOTE_DIR}"

# 1. Push del codice. Esclude tutto ciò che è locale/non-produzione: il .env,
#    le dipendenze (reinstallate sull'istanza), gli artefatti di sviluppo.
rsync -az --delete \
  --exclude '.env' \
  --exclude '.git' \
  --exclude 'vendor' \
  --exclude 'node_modules' \
  --exclude 'storage/keys' \
  --exclude 'database/database.sqlite' \
  --exclude 'storage/logs/*' \
  --exclude 'tests' \
  "${BACKEND_DIR}/" "${SSH_USER}@${APP_HOST}:${REMOTE_DIR}/"

# 2. Build e migrazioni sull'istanza (zero-downtime-friendly: migrazioni
#    espandi-poi-contrai, docs/32 §6).
ssh "${SSH_USER}@${APP_HOST}" bash -se <<REMOTE
set -euo pipefail
cd "${REMOTE_DIR}"

# Dipendenze di sola produzione, ottimizzate.
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Le chiavi JWT vivono fuori dal repo: generate una sola volta, mai sovrascritte.
if [ ! -f storage/keys/jwt-private.pem ]; then
  ${PHP} artisan jwt:generate-keys
fi

${PHP} artisan migrate --force
${PHP} artisan db:seed --class=PlanSeeder --force

# Cache di config/route/view per le performance in produzione.
${PHP} artisan config:cache
${PHP} artisan route:cache
${PHP} artisan view:cache

# Riavvio worker coda; Caddy/php-fpm restano su (HMR non applicabile).
sudo systemctl restart platform-queue
REMOTE

echo "✔ Deploy completato. Smoke test:"
echo "   curl -s https://${APP_HOST}/up"
