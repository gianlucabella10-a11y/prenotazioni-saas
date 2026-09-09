# APP_FACTORY_PHASE2_AUDIT (FASE 0 — obbligatorio)

> Audit sul **codice reale** (non sulla memoria) prima di scrivere codice. Obiettivo: completare la App Factory fino a *"un operatore crea una nuova app cliente senza intervento tecnico"*. Legenda: 🟢 pronto · 🟡 da migliorare · 🔴 bloccante per il deliverable richiesto.

## Sintesi
Il **70–80% richiesto esiste già** (FASE 1/2A/2B/2C/3 delle sessioni precedenti). Gli unici **deliverable mancanti esplicitamente richiesti** sono: il **TemplateRegistry** (oggi i template sono in config ma letti inline), il **pacchetto self-contained `generated_apps/{tenant}/`** (oggi si produce solo il manifest), la **validazione immagine** in upload, e la **preview brand/icona + asset status** in Control Room.

---

## BACKEND

| Componente | Stato | Evidenza / Nota |
|---|---|---|
| `AppProject` | 🟢 | Model 1:1 tenant, bundle_id/package_name unici e immutabili, `build_status` enum, `built_core_version` (release train). |
| `AppBuild` | 🟢 | Storico per-piattaforma (config/android/ios), `status`, `artifact_path`, `version`. |
| `BrandProfile` | 🟢 | app_name, colori, contatti/social, `config_version` (ETag cache-bust). |
| `BrandAsset` | 🟢 | kind `logo`/`icon`/`splash` + `variant` + `version` + checksum; derivati collegati al brand del tenant. |
| `BuildWhiteLabelConfig` | 🟡 | Espone logo/contacts/social/hours/template/layout/font. **Accesso template inline** (`config("app_templates.$code")`), niente registry; `sections` non esposte. |
| Control Room | 🟡 | Login MFA, clienti CRUD, App Factory (`/apps`), Flotta. **Manca**: preview brand/icona + asset status nella scheda app. |
| `ProvisionTenant` | 🟢 | tenant+owner+brand+sede+orari+catalogo+invito. |
| Asset Factory (`GenerateBrandAssets`) | 🟢 | GD reale: 9 icone (Android mdpi→xxxhdpi, iOS 60/120/180/1024) + 3 splash, per-tenant, versionati, idempotenti, isolati, **testati**. |
| Validazione immagine in upload (`StoreBrandLogo`) | 🟡 | Salva e registra il logo ma **non valida** formato/dimensioni minime: un logo troppo piccolo o non raster passa silenziosamente. |
| `GenerateAppPackage` (manifest 2.0) | 🟢 | app_identity/runtime(dart_define)/branding/template/assets/metadata + `manifest-latest.json`. |
| `php artisan app:generate {tenant}` | 🟢 | Idempotente, prepara asset+manifest, porta a `generated`/`ready_to_build`. |
| **Pacchetto `generated_apps/{tenant}/`** | 🔴 | Si produce **solo il manifest** su disco; **non** la cartella self-contained `manifest.json` + `assets/` + `config/` richiesta, né uno zip scaricabile dalla Control Room. |
| **TemplateRegistry** | 🔴 | I template vivono in `config/app_templates.php` (barber/beauty/medical/restaurant/default) ma **non esiste** un registry con normalizzazione, fallback e guardia su config invalida. |

## FLUTTER

| Componente | Stato | Evidenza / Nota |
|---|---|---|
| Theme system (`AppThemeBuilder`) | 🟢 | Tema dai token + `fontStyle` (oswald/poppins/inter). |
| `WhiteLabelConfig` model | 🟢 | template/layout/fontStyle/logo/contacts/social/hours. |
| Asset loading | 🟢 | logo via URL runtime (ETag/config_version). |
| App bootstrap | 🟢 | `--dart-define` (ENV/API_BASE_URL/TENANT_KEY) + config runtime + cache. |
| Routing | 🟢 | go_router invariato. |
| Template readiness | 🟡 | Consuma `layout_variant` (hero/standard) + theme + font, **senza `if cliente==X`**. L'**ordine delle sezioni** (`sections`) non è ancora data-driven: rinviato alla fase DESIGN (additivo, non bloccante). |

## Decisioni architetturali confermate (config-over-code)
- **Adaptive icon Android + AppIcon set iOS completo** restano generati a **build-time** da `flutter_launcher_icons`/`flutter_native_splash` (FASE 2B) dal master prodotto dall'Asset Factory: corretto non stoccare 20 PNG server-side. 🟢 by design.
- **Stati app**: il ciclo `draft → ready_to_build → published` (+ building/failed) copre la richiesta `draft/configured/ready/generated`; aggiungo un'etichetta **derivata** «Configurata» in Control Room (brand+logo presenti, non ancora generata) **senza** nuovi stati in DB (no churn).
- **Booking/auth/API/sicurezza**: non toccati. Ogni intervento è additivo.

---

## Piano di implementazione (solo i gap)
1. **TemplateRegistry** (Domain) — `all/codes/has/get/layout/fontStyle/sections/label/default`, fallback a `default` su codice/config invalidi. Refactor `BuildWhiteLabelConfig` + `AppProjectController` per usarlo; espone `sections` in `/app/config`. *Test: fallback default, codice invalido, sezioni.*
2. **Validazione immagine** in `StoreBrandLogo` — raster valido, mime in allowlist (png/jpg/webp), lato minimo ≥ 256px → `ValidationException` (niente logica nei controller). *Test: rifiuta non-immagine/troppo piccola, accetta valida.*
3. **ExportAppPackage** — assembla `generated_apps/{uuid}/` con `manifest.json` + `assets/` (logo+derivati) + `config/` (build.env + template.json); idempotente, isolato per tenant. Collegato a `PrepareApp` (lo producono sia `app:generate` sia la Control Room) + **download ZIP** in Control Room. *Test: struttura cartella, asset copiati, isolamento.*
4. **AppPreview** + scheda Control Room — logo, swatch colori, **mockup icona**, asset status (n. icone/splash, versione), etichetta ciclo di vita. *Test: scheda visibile al super-admin con preview.*

## Cosa NON faccio (fuori scope dichiarato)
Redesign UI, nuove feature business, modifiche a booking/auth/architettura core, build native reali / firma / upload store (già coperti come scaffold in FASE 2C/3). Rendering dinamico delle `sections` in Flutter → fase DESIGN.
