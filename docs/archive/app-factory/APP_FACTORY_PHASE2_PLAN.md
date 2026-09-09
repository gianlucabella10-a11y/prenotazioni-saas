# APP_FACTORY_PHASE2_PLAN — Preparation pipeline (FASE 2A)

> Da "manifest generator" a "app preparation pipeline". **Nessuna build/firma/upload store, nessun flavor, nessun codice cliente.** Additivo. Baseline da non rompere: **backend 123 · Flutter 33 · analyze pulito**. Audit: **GD presente** (asset reali via PHP, nessun nuovo stack); `brand_assets` ha kind/disk_path/mime/width/height/checksum ma **manca version/variant**.

## Cosa verrà modificato / creato
**Backend (nuovi)**
- `database/migrations/..._add_variant_version_to_brand_assets.php` — colonne `variant` (string nullable) + `version` (uint default 1), additive.
- `app/Modules/Branding/Application/GenerateBrandAssets.php` — **Asset Factory reale** (GD): dal logo master genera set icone Android (mdpi→xxxhdpi) + iOS (40/60/80/120/180/1024) + splash, ognuno → riga `brand_assets` (kind `icon`/`splash`, `variant` spec, `version`, width/height/checksum). **Graceful**: senza logo non genera (no throw); SVG non rasterizzabile → saltato con avviso. Idempotente (incrementa version, sostituisce i derivati).
- `app/Modules/AppFactory/Application/PrepareApp.php` — orchestratore: GenerateBrandAssets → GenerateAppPackage; usato da comando **e** Control Room (no duplicazione).
- `app/Console/Commands/GenerateApp.php` — `php artisan app:generate {tenant}` (valida tenant, controlla brand/logo, genera asset, manifest, aggiorna AppProject/AppBuild, stampa stato). **No build.**

**Backend (modifiche)**
- `AppProjectStatus` → aggiunge `ReadyToBuild`.
- `GenerateAppPackage` → **Manifest 2.0**: `app_identity`, `runtime` (dart_define+tenant_key+api_url), `branding` (logo/icons/splash/colors), `template` (code/layout/font), `assets` (paths derivati), `metadata`. Stato `ready_to_build` se asset presenti, altrimenti `generated`.
- `AppProjectController@generate` → usa `PrepareApp`.

**Flutter (preparation, additivo)**
- `AppThemeBuilder` → meccanismo `fontStyle` → fontFamily (mappatura; i file font si impacchettano dopo — fallback al default, nessuna rottura). `ClientApp` passa `config.fontStyle`.
- `HomeScreen` → legge `config.layout` e sceglie variante header (`hero` vs `standard`): aggancio reale del Template Engine lato client.

## File coinvolti (riepilogo)
Migration brand_assets · `GenerateBrandAssets` · `PrepareApp` · `GenerateApp` (command) · `AppProjectStatus` · `GenerateAppPackage` · `AppProjectController` · Flutter `app_theme_builder.dart` + `app.dart` + `home_screen.dart`.

## Rischi & mitigazioni
- **GD/logo formato**: SVG non rasterizzabile da GD → saltato con messaggio (documentato); PNG/JPG ok. Senza logo → asset saltati, stato resta `generated` (gestione "missing logo" testata).
- **Regressione test FASE 1**: il manifest cambia forma (2.0) → aggiorno `GenerateAppPackageTest`; il generate del Control Room senza logo resta `generated` (test invariato).
- **Font Flutter**: senza i .ttf impacchettati il fontStyle è solo meccanismo (fallback default) — onesto in readiness.
- **Isolamento**: asset legati al `brand_profile`/tenant; generazione via `CurrentTenant::bypass`; test di isolamento.

## Test necessari
Backend: `GenerateBrandAssetsTest` (genera derivati da logo, isolamento tenant, idempotenza version, missing-logo graceful, formato non supportato), `GenerateAppPackageTest` aggiornato (manifest 2.0 + ready_to_build), `GenerateAppCommandTest` (comando end-to-end), template validation (già presente). Flutter: `app_theme_builder` (fontStyle), `home_screen` (layout switch). Regressione: 123 backend + 33 Flutter verdi.

## Fuori scope (FASE 2B/3)
Build Android/iOS reale, firma, upload store, CI/CD, generazione di OGNI singola dimensione nativa + Contents.json/mipmap nei progetti (è build-time), font reali impacchettati, layout variants ricchi multipli.
