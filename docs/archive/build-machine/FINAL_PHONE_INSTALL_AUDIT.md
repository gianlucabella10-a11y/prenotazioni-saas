# FINAL_PHONE_INSTALL_AUDIT (FASE 0)

> Audit per l'installazione su telefono reale, con backend esposto via Cloudflare Tunnel. 🟢 ok · 🟡 da impostare · 🔴 blocca.

## Esposizione pubblica (eseguita)
- **cloudflared** installato (`~/.local/bin/cloudflared` 2026.6.0).
- Tunnel attivo → **PUBLIC_URL** = `https://trust-elsewhere-inspections-contributing.trycloudflare.com`.
- Verifica reale: `GET /control-room/login` → **200**, `GET /api/v1/app/config` → **401** (gate corretto). 🟢
- ⚠️ URL **effimero**: vive finché girano `php artisan serve` + `cloudflared` (sessione). Per durare → deploy (`DEPLOYMENT_READY.md`).

## API_BASE_URL (Flutter)
- Iniettato a build-time (`--dart-define`); l'APK va ricompilato con `APP_FACTORY_API_BASE_URL=PUBLIC_URL/api/v1`. ✅ in corso (rebuild verso il tunnel).
- L'app invia `X-Tenant-Key` (dal manifest) → risolve il tenant.

## Endpoint backend
- `/api/v1/app/config` (config white-label), `/api/v1/auth/*`, `/api/v1/availability`, `/api/v1/appointments`, `/beta/download/{token}`. Raggiungibili via tunnel. 🟢

## CORS
- L'app è **nativa** (Dio/HTTP), non un browser → **CORS non applicabile**. 🟢

## Storage pubblico (logo/asset runtime)
- `logo_url` usa il disco `public` (`/storage/...`). Serve `php artisan storage:link` perché i loghi si carichino via HTTP. 🟡 (impostare sulla macchina).

## Beta download
- Token opaco + scadenza + limite + conteggio + revoca; endpoint pubblico `/beta/download/{token}`. 🟢

## Control Room
- Login super-admin + MFA; admin demo creato. Raggiungibile via tunnel. 🟢

## APK config
- Firma release (keystore di piattaforma, `apksigner` ✅). Rebuild verso PUBLIC_URL in corso. 🟢

## Sintesi
Tutto pronto per installare oggi: tunnel pubblico attivo, APK firmato in ricompilazione verso il tunnel, beta link generato. Unico 🟡 operativo: `storage:link` per i loghi. Link e procedura nel report finale (`REAL_PHONE_BETA_READY.md`).
