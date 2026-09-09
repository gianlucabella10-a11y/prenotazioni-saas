# WHITE_LABEL_PRODUCTION_READINESS

> Chiusura del **motore white-label**. Esito dell'audit `WHITE_LABEL_FINAL_AUDIT.md` + hardening finale. Un operatore crea un'app cliente end-to-end dalla Control Room **senza modificare codice**. Booking/auth/API/sicurezza **intatti**.
>
> Verifiche: backend `php artisan test` **167/167** (643 asserzioni) · Pint pulito (file modificati) · `flutter analyze` pulito · `flutter test` **36** · core (Scheduling/Foundation/auth) non toccato.

## 1. Cosa è completato
- **App Identity** — `app_projects`: bundle_id/package_name/shortcode/slug **unici** (DB) e **immutabili** (guard `AppProject::booted()` blocca la mutazione dopo la creazione; template/font/stato restano modificabili). Allocazione **idempotente** (`AllocateAppIdentifiers`): un tenant = un solo App Project. Versione/metadata tracciati in `app_builds`.
- **Asset pipeline** — un logo (validato: raster, PNG/JPG/WebP, ≥256px) → `GenerateBrandAssets` (GD) produce 9 icone + 3 splash **per-tenant, versionati, isolati**. Adaptive icon Android + AppIcon set iOS completi a build-time da `flutter_launcher_icons`/`flutter_native_splash`. Nessun asset globale: tutto legato al brand del tenant.
- **Pacchetto autosufficiente** — `ExportAppPackage` → `generated_apps/{uuid}/`:
  - `manifest.json` (sorgente di verità), `config.json` (identità+branding+environment+build), `README.txt` (istruzioni operatore),
  - `assets/` (logo + icone + splash), `config/build.env` (`--dart-define`), `config/template.json` (skin).
  Idempotente, isolato, scaricabile in **ZIP** dalla Control Room.
- **Control Room operativa** — login MFA → crea cliente → carica logo → sceglie template → **anteprima brand** (logo, swatch, mockup icona, asset status, ciclo di vita) → **genera pacchetto** → **scarica ZIP**. Error handling: download pre-generazione → 404 con messaggio.
- **Flutter white-label runtime** — **zero hardcoded cliente** (verificato): identità via `--dart-define` (`AppEnvironment.validate()` fail-fast), brand/colori/testi/asset/template via `GET /app/config` (ETag/`config_version`); title da `config.appName`.
- **Sicurezza** — isolamento verificato con test in **contesto tenant** (`WhiteLabelIsolationTest`): Tenant A non vede app_project/brand/asset di Tenant B; device/push isolati per `user_id`; Control Room solo super-admin.
- **Build preparation** — Android `applicationId`/`appName` da Gradle property; iOS `Tenant.xcconfig` + `CFBundleDisplayName`; `make_app.sh` orchestratore. Firma Android default-safe; CI scaffold per-tenant + batch (release train).

## 2. Cosa è pronto per il design
Tutto il **contratto dati** che la UI consumerà è già esposto e stabile in `GET /app/config`:
`template`, `layout`, `font_style`, **`sections`** (ordine), `theme` (token), `app_name`, `tagline`, `logo_url`, `contacts`, `social`, `opening_hours`, `features`, `booking.confirmation_mode`.
Il punto d'innesto del design è il **rendering dinamico delle `sections`** sopra layout/tema/font già guidati dal template (oggi il client rende hero/standard; le sezioni sono esposte e pronte). Nessun blocco architetturale: il design lavora su componenti, non su struttura dati.

## 3. Cosa manca per la store release
- **Build native reali**: `flutter build appbundle`/`ipa` su **Mac/CI** (non eseguibili qui).
- **Account + segreti store** (`APP_FACTORY_RELEASE_SECRETS.md`): keystore Android + service account Google Play; **account Apple Developer del cliente** (4.2.6/4.3) + API key App Store Connect.
- **Prima esecuzione CI** su tenant pilota (track `internal`, canary `--limit=1`).
- **Font `.ttf`** reali (Oswald/Poppins/Inter) — meccanismo cablato, file esterni (licenza).
- *(Opzionali, non bloccanti)*: `app_templates` su DB con editor; Asset Factory come microservizio; rendering `sections` lato Flutter (fase design).

## 4. Processo operativo — creare un nuovo cliente
**Esempio: «Giuffrida Barber»** — l'operatore, dalla Control Room, **senza tocco tecnico**:

1. **Crea tenant** → *Clienti ▸ + Nuovo cliente*: nome attività, settore, email titolare, piano, **template** (es. Barber), colore. *(ProvisionTenant crea tenant + owner + brand + sede + orari + catalogo + invito; AllocateAppIdentifiers alloca bundle/package unici e immutabili.)*
2. **Carica logo** → *App ▸ [cliente]* o quick setup brand: upload logo (validato ≥256px). *(StoreBrandLogo + config_version bump.)*
3. **Configura dati** → colori/contatti/social dal brand; il titolare completa servizi/operatori/orari dalla sua dashboard.
4. **Genera app package** → *App ▸ [cliente] ▸ Genera pacchetto*: `PrepareApp` produce asset + manifest 2.0 + cartella `generated_apps/{uuid}/`. Stato → *Pronta build*.
5. **Scarica package** → *Scarica package (ZIP)*: l'operatore ottiene il pacchetto autosufficiente.
6. **Prepara build** *(Mac/CI, fase successiva)* → `make_app.sh` consuma il manifest/asset → build firmata → upload store (richiede account del cliente per iOS).

Equivalente CLI (super-admin): `php artisan app:generate {tenant-uuid}` → pacchetto pronto; `php artisan app:build-matrix` → flotta per la CI.

— **Stato: motore white-label CHIUSO. Pronto per la fase DESIGN UI/UX.** Riferimenti: `WHITE_LABEL_FINAL_AUDIT.md`, `APP_FACTORY_PHASE2_READINESS.md`, `APP_FACTORY_PHASE2C_READINESS.md`, `APP_FACTORY_PHASE3_READINESS.md`, `APP_FACTORY_RELEASE_SECRETS.md`.
