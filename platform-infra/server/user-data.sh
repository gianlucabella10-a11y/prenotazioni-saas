#!/usr/bin/env bash
# Cloud-init dell'istanza pilota (Ubuntu 24.04 arm64): PHP-FPM 8.4 + Caddy
# (https automatico via Let's Encrypt) + worker coda + scheduler.
# Il codice applicativo arriva successivamente con bin/deploy.sh.
set -euxo pipefail

export DEBIAN_FRONTEND=noninteractive

APP_DOMAIN="${app_domain}"
APP_DIR=/var/www/platform

# --- PHP 8.4 (PPA ondrej, lo standard de-facto per Ubuntu) -----------------
apt-get update
apt-get install -y software-properties-common curl unzip acl
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-gd

# Composer
curl -sS https://getcomposer.org/installer | php8.4 -- --install-dir=/usr/local/bin --filename=composer

# --- Caddy (repo ufficiale) -------------------------------------------------
apt-get install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
  | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
  | tee /etc/apt/sources.list.d/caddy-stable.list
apt-get update
apt-get install -y caddy

cat > /etc/caddy/Caddyfile <<CADDY
$${APP_DOMAIN} {
    root * $${APP_DIR}/public
    encode zstd gzip
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server

    header {
        Strict-Transport-Security "max-age=31536000"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
    }
}
CADDY

# --- Directory applicazione -------------------------------------------------
mkdir -p "$${APP_DIR}"
chown -R www-data:www-data "$${APP_DIR}"
# Il gruppo www-data può scrivere (deploy via utente ubuntu nel gruppo)
usermod -aG www-data ubuntu
chmod -R g+w "$${APP_DIR}"

# --- Worker coda (driver database, profilo pilota: niente Redis) ------------
cat > /etc/systemd/system/platform-queue.service <<'UNIT'
[Unit]
Description=Platform queue worker
After=network.target

[Service]
User=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php8.4 /var/www/platform/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
# Parte solo quando il codice è stato deployato
ConditionPathExists=/var/www/platform/artisan

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable platform-queue caddy php8.4-fpm

# --- Scheduler (docs/29: notifications:dispatch-due e affini) ----------------
cat > /etc/cron.d/platform-scheduler <<'CRON'
* * * * * www-data [ -f /var/www/platform/artisan ] && /usr/bin/php8.4 /var/www/platform/artisan schedule:run >> /var/log/platform-scheduler.log 2>&1
CRON

systemctl restart caddy php8.4-fpm
