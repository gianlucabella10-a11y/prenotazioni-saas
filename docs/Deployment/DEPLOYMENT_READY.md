# DEPLOYMENT_READY (FASE 3)

> Comandi esatti per rendere il backend raggiungibile da un telefono. Due strade: **A) tunnel** (test immediato, URL effimero) · **B) deploy** (beta stabile). In entrambe l'APK va ricompilato con l'`API_BASE_URL` pubblico (l'URL è compile-time).

## A) Tunnel — test immediato (URL effimero)
Sulla macchina dove gira il backend:
```bash
# 1) backend in ascolto
cd platform-backend && php artisan serve --host=0.0.0.0 --port=8000 &

# 2) tunnel pubblico HTTPS (cloudflared = singolo binario, no sudo)
#    download: https://github.com/cloudflare/cloudflared/releases (cloudflared-darwin-arm64)
cloudflared tunnel --url http://127.0.0.1:8000
#    → stampa un URL tipo https://xxxx.trycloudflare.com  (questo è PUBLIC_URL)
```
> L'URL trycloudflare è **effimero** (cambia ad ogni avvio, muore col processo): va bene per provare la beta subito, non come URL definitivo.

## B) Deploy — beta stabile (server + dominio + TLS)
Su un VPS (Ubuntu) con PHP 8.4, Nginx, un DB gestito:
```bash
git clone <repo> /var/www/app && cd /var/www/app/platform-backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
#  .env: APP_ENV=production · APP_URL=https://app.tuodominio.it
#        DB_* (managed) · QUEUE_CONNECTION=database · FCM_* · SENTRY_DSN
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache
# worker persistente (supervisor/systemd):
php artisan queue:work --tries=1 --timeout=2000
# Nginx → public/ ; TLS con certbot (Let's Encrypt). PUBLIC_URL = https://app.tuodominio.it
```

## In entrambi i casi: ricompila l'APK verso PUBLIC_URL
L'`API_BASE_URL` è iniettato a build-time, quindi l'APK deve puntare al backend raggiungibile:
```bash
source ~/.local/keystore/keystore.env
export JAVA_HOME=~/.local/toolchain/jdk-17.0.19+10/Contents/Home ANDROID_HOME=~/android-sdk
export PATH="$HOME/.local/flutter/bin:$ANDROID_HOME/platform-tools:$PATH"
export APP_FACTORY_BUILD_DRIVER=local QUEUE_CONNECTION=sync
export APP_FACTORY_API_BASE_URL="https://PUBLIC_URL/api/v1"   # <-- backend raggiungibile
cd platform-backend
php artisan app:generate <TENANT_UUID>
php artisan app:build    <TENANT_UUID> android
```
Poi: Control Room ▸ **Link beta** → il link (sul PUBLIC_URL) si apre dal telefono → installa → l'app parla col backend.

## Checklist produzione
- [ ] `APP_ENV=production`, `APP_URL=https://…`
- [ ] DB gestito + `migrate --force`
- [ ] `storage:link` + permessi storage
- [ ] worker `queue:work` attivo (supervisor)
- [ ] TLS valido (HTTPS)
- [ ] `APP_FACTORY_API_BASE_URL` = dominio pubblico, APK ricompilato
- [ ] keystore in vault, ENV `ANDROID_KEYSTORE_*` impostate
