# APP_FACTORY_MASTER_PLAN — Architettura definitiva White-Label

> Progetto architetturale **design-only** (nessun codice). Data: 2026-06-15.
> Estende `APP_FACTORY_ROADMAP.md` (approvato) e `docs/27-white-label-tecnico.md` (fonte di verità pipeline build). Documenti correlati: `CONTROL_ROOM_READINESS.md`, `PRODUCT_DESIGN_ROADMAP.md`, `PUSH_NOTIFICATION_READINESS.md`.

## Contesto
Il prodotto non è un'app ma una **piattaforma che genera app personalizzate** (Giuffrida Barber App, Studio Rossi App… → stesse fondamenta, store diversi). Obiettivo: 10 clienti nel primo anno, poi scala.

**Principio guida (il filtro di ogni decisione):** *ogni cliente è una CONFIGURAZIONE, mai codice.* Deve funzionare a 10 → 100 → 1000.

---

## 0. Stato reale (cosa esiste / riusabile / manca)
| Area | Esiste già (RIUSO) | Manca |
|---|---|---|
| Identità runtime | `WhiteLabelConfig` + `BuildWhiteLabelConfig` (app_name, tagline, theme token, **logo_url, contacts, social, maps, opening_hours**), `AppThemeBuilder` (tema dai token), `WhiteLabelRepository` (ETag+cache) | campo `template`/`layout` + `font_style`; varianti di layout |
| Identità compile-time | `app_environment.dart` (`--dart-define` ENV/API_BASE_URL/TENANT_KEY); bootstrap `main.dart`/`app.dart` | bundle id/app name/icona **parametrici** (oggi HARDCODED `com.platform.client_app`); nessun flavor; nessun tooling icone/splash |
| Onboarding tenant | `ProvisionTenant` (tenant+owner+brand+sede+orari+catalogo+invito); `Control Room /control-room` | sezione `/control-room/apps` (App Project) |
| Brand/asset | `BrandProfile` + **`brand_assets`** (kind `logo`/`icon_source`/`splash_source` già previsti); `StoreBrandLogo` | generazione set icone/splash dal master; tabelle `app_projects`/`app_builds` |
| Template | `config/sector_presets.php` (catalogo per settore) | `config/app_templates.php` (skin/layout) |
| Commerciale | `Plan`/`Subscription`/`TenantFeature` | — |
| Push/notifiche | foundation completa (FcmPushChannel + outbox + email fallback) | config Firebase esterna |

**Conclusione:** ~70% del White-Label Engine esiste già. Mancano: il livello **App Project/identità store**, il **Template Engine (skin)**, la **Asset Factory** e la **build parametrica**.

---

## 1. Architettura finale (Master Application)
```
                 ┌──────────────────────── CONTROL ROOM (super-admin) ────────────────────────┐
                 │  crea cliente → sceglie template → brand → genera pacchetto → (build)        │
                 └───────────────┬──────────────────────────────────────────────┬──────────────┘
                                 │ ProvisionTenant + AllocateAppIdentifiers       │ GenerateAppPackage
                                 ▼                                                ▼
   BACKEND (1 Laravel multi-tenant)                              APP FACTORY (build parametrica)
   tenants · brand_profiles · brand_assets                       manifest (--dart-define + ids + assets)
   app_projects · app_builds · sector_presets · app_templates           │
                                 │ GET /app/config (runtime)             ▼
                                 ▼                              MASTER APP (1 repo Flutter)
                        WhiteLabelConfig  ───────────────►  build con identità del tenant
                        (logo, colori, testi, social,              │
                         orari, template, features)                ▼
                                                          Giuffrida App · Studio Rossi App · …
                                                                   (store separati)
```
**Una sola app Flutter, un solo backend, un solo motore.** Il cliente = righe di config + asset. Nessun fork, nessun ramo per cliente.

## 2. Diagramma di flusso (nuovo cliente → store)
```
Control Room: "Nuovo cliente"
   │  nome attività, settore, email titolare, template, colore, logo
   ▼
ProvisionTenant (esistente)         → tenant + owner + brand + sede + orari + catalogo + invito
   ▼
AllocateAppIdentifiers (nuovo)      → app_projects: bundle_id, package_name, slug, store_name, template
   ▼
Asset Factory (nuovo)               → da logo master: icone Android/iOS + splash + store assets + preview
   ▼
GenerateAppPackage (nuovo)          → build manifest (ids + --dart-define + asset paths) + app_builds(generated)
   ▼  [FASE 2: build parametrica]
Build script (1 codebase, N config) → AAB (Android) / IPA (iOS, account Apple del cliente)
   ▼
Pubblicazione store (semi-manuale FASE 2 → automatizzata FASE 3)
   ▼  runtime
GET /app/config → WhiteLabelConfig → brand/colori/testi/social/orari/template dinamici
```

## 3. White Label Engine — Configurazione vs Codice (Parti 1+2)
**CONFIGURAZIONE (database/API, per-tenant, runtime salvo identità nativa):**
- Identità: app_name, brand_name, logo, favicon, primary/secondary_color, font_style*, splash, app_icon* (*curati/whitelist)
- Contatti/social: telefono, email, WhatsApp, Instagram, Facebook, Google Maps → **già esposti** in `/app/config`
- Contenuti: servizi, operatori, immagini, descrizione, orari → DB tenant
- Template/skin + feature flag → `app_templates` + `TenantFeature`

**CODICE (master app, mai per-cliente) — perché:**
- **booking engine, auth, pagamenti, notifiche, sicurezza, isolamento tenant** restano codice perché sono **logica critica, testata e identica per tutti**: duplicarli per cliente moltiplicherebbe i bug, romperebbe la sicurezza e renderebbe impossibile la manutenzione a scala. Regola: *se cambia l'aspetto/contenuto → config; se cambia il comportamento/sicurezza → codice condiviso.*

Confine compile-time vs runtime (docs/27 §1): **bundle id / icona / nome nativo = compilati** (cambiano = nuova build+review store, raro); **logo in-app / colori / testi / social / orari = runtime** (cambiano in minuti via `config_version`).

## 4. Flavor Architecture: valutazione e scelta (Parte 4)
**Flutter Flavors: VALUTATI e SCARTATI come meccanismo di scala.** Un flavor per cliente = per ogni cliente editare `android` `productFlavors` + creare scheme/target Xcode → **codice e progetto per-cliente**, vietato dai vincoli. A 100 clienti = 100 scheme = progetto ingestibile, CI lenta, conflitti. (Oggi: zero flavor, `applicationId`/bundle hardcoded — confermato.)

**SCELTA: single build target parametrizzato (1 codebase, N config) — scala a 1000:**
- `applicationId`/`PRODUCT_BUNDLE_IDENTIFIER`: iniettati a build time (Gradle property `-PappId=…` in `build.gradle.kts`; iOS via `xcconfig`/build-setting override) dal **manifest** del tenant.
- `app_name` nativo: `resValue`/variabile in `AndroidManifest` + `Info.plist` da manifest.
- icone/splash: prodotte dalla **Asset Factory** e copiate prima del build.
- identità runtime: `--dart-define TENANT_KEY/API_BASE_URL` (già esistente).
- **Convenzione bundle id**: `com.<reverse-domain-piattaforma>.t<shortcode>` (docs/27 §3), allocata una volta in `app_projects`, immutabile, mai riusata.
> Flavors accettabili solo come ripiego per i primissimi 1-2 pilota; l'architettura ufficiale è la build parametrica.

## 5. Template System — skin, motore invariato (Parte 5)
- `config/app_templates.php` (come `sector_presets.php`): ogni template = `{ code, label, vertical, theme(token+font_style curato), layout_variant, sections(quali e ordine), slot immagini }`.
- Template FASE 1: `barber_dark`, `beauty_visual`, `medical_clean`, `restaurant_visual` (quest'ultimo = skin che mappa "prenota tavolo" su un appuntamento — **nessuna modifica al booking engine**).
- Flutter: `WhiteLabelConfig` guadagna `template`/`layout`/`font_style`; Home/Scheda hanno 2-3 **varianti di layout** scelte a runtime. Font da **whitelist** impacchettata (no font liberi).
- **Cambia**: UI, colori, icone, layout, sezioni. **NON cambia**: prenotazioni, login, account, backend, sicurezza.

## 6. Asset Factory (Parte 6)
- Input: **un logo master** caricato dal Control Room (già possibile → `brand_assets`).
- Output automatico: launcher icon Android (adaptive), AppIcon iOS (set completo), splash multi-densità, store assets, **preview** in Control Room.
- **Come**: ibrido — (a) generazione **server-side** (PHP/Intervention Image o microservizio) all'upload per preview immediata e store assets; (b) a build, `flutter_launcher_icons` + `flutter_native_splash` (oggi **assenti**, da aggiungere) guidati da un config per-tenant generato dal manifest. Validazione: dimensioni minime, margini sicuri, contrasto.

## 7. App Generator & Control Room (Parti 3+7)
- **`/control-room/apps`**: ogni cliente = un **App Project** con stato `draft → ready → generated` (FASE 2: `building → published`). Solo super-admin (guard `admin` + `EnsureSuperAdmin`, già esistenti). Non visibile ai clienti.
- "Crea App Project" = `ProvisionTenant` (riuso) + `AllocateAppIdentifiers`. "Genera pacchetto" = `GenerateAppPackage` (manifest + asset) → scaricabile, traccia in `app_builds`.
- Il Control Room è il centro operativo: crea cliente, gestisce brand, sceglie template, prepara app, genera config, prepara build.

## 8. Pubblicazione store — realistica (Parte 8)
- **Android (Play)**: app per-tenant dall'**account developer della piattaforma**. Automatizzabile (Play Developer API + Fastlane). Mitigare policy "app ripetitive" con contenuti store reali (descrizione/screenshot dal catalogo).
- **iOS (App Store)**: Apple **richiede l'account Apple Developer del cliente** per app template (4.2.6/4.3) → **non** automatizzabile sotto un unico account. Onboarding assistito dell'account del cliente; build/upload via App Store Connect API una volta delegato.
- **Automatizzabile**: build, firma (keystore/cert da vault), upload, tracking. **Intervento umano**: enrollment Apple del cliente, primo review, risposte a rejection.
- **Strategia 10 clienti**: Android incluso e quasi-automatico; iOS con account del cliente + attivazione guidata (fee di attivazione). Pagina web/PWA come canale di cortesia.

## 9. Database necessario (additivo, nessuna modifica distruttiva)
- **`app_projects`** (1:1 tenant): uuid, tenant_id, slug, shortcode, store_name, bundle_id (unique), package_name (unique), template_code, font_style, powered_by_enabled(true), build_status, last_generated_at, build_manifest(json).
- **`app_builds`**: uuid, app_project_id, version, platform(config/android/ios), status, artifact_path, created_by, timestamps.
- Riuso: `tenants`, `brand_profiles`+`brand_assets`, `sector_presets`(config), `plans/subscriptions/tenant_features`. Nuovo config: `app_templates.php`. (Brand e legal restano in `BrandProfile` — niente duplicazione.)

## 10. Flutter architecture (master app)
- Resta feature-first + Riverpod + go_router (invariato). Aggiunte: `template`/`layout`/`font_style` in `WhiteLabelConfig`; varianti di layout in Home/Scheda guidate dal template; font whitelist in `assets/fonts` + `AppThemeBuilder` che seleziona la famiglia dal `font_style`.
- Build: nessun flavor; `build.gradle.kts`/`xcconfig` parametrici; `flutter_launcher_icons`/`flutter_native_splash` + uno script `make_app` che legge il manifest.
- Struttura cartelle proposta: `lib/core/{env,network,session,push}`, `lib/features/*`, `lib/app/*` (esistenti) + `tool/app_factory/` (script build/asset, FASE 2) + `assets/fonts/`.

## 11. Backend necessario
- Modulo `app/Modules/AppFactory/*`: `Application/`(AllocateAppIdentifiers, GenerateAppPackage, GenerateAppIcons), `Infrastructure/Models/`(AppProject, AppBuild), `Http/Controllers/`(AppProjectController). Riusa `ProvisionTenant`, `BrandProfile`, `brand_assets`, `BuildWhiteLabelConfig`, `AuditLogger`.
- `/app/config` esteso (additivo): `template`, `font_style` (logo/contacts/social/hours già fatti).

## 12. Roadmap implementazione
**FASE 1 — necessaria ORA** (config-first, zero rischio build):
1. `app_projects` + `app_builds` (migrazioni/modelli) + `AllocateAppIdentifiers` (convenzione bundle id).
2. `/control-room/apps`: lista + "Crea App Project" (wrappa `ProvisionTenant`) + scheda.
3. `config/app_templates.php` + selettore template; esporre `template`/`font_style` in `/app/config`.
4. `GenerateAppPackage` v1 = **manifest + config** (no build reale).

**FASE 2 — prima dei primi 10 clienti**:
5. Asset Factory (server-side preview + `flutter_launcher_icons`/`native_splash` a build).
6. Build parametrica: `build.gradle.kts`/`xcconfig` da manifest + script `make_app <tenant>` → AAB; iOS assistito.
7. Varianti di layout Flutter (2-3) guidate dal template; font whitelist.
8. Android: Play Developer API + Fastlane (upload track interno).

**FASE 3 — scala 100+**:
9. CI matrix (build N app dal registry) + treno di rilascio core batched + canary.
10. `app_templates` su DB con editor; Asset Factory come microservizio; osservabilità build; pool runner macOS per iOS.

## Verifica (a implementazione avvenuta)
- `php artisan test` (suite attuale 110 invariata + nuovi test AppFactory: allocazione id unica, isolamento, generazione manifest) e `flutter analyze`/`flutter test` verdi.
- Smoke: Control Room → crea App Project → bundle/package unici → scegli template → genera pacchetto → scarica manifest+asset; l'app legge `template` da `/app/config` e mostra la variante di layout; nessuna modifica a booking/auth/schema (solo tabelle additive).

## Stato di verifica
Architettura derivata da lettura diretta del codice reale (Flutter bootstrap/theme/env/native config; backend Tenant/Provision/Brand/Control Room/config) e da `docs/27` + `APP_FACTORY_ROADMAP.md`. **Nessun file applicativo modificato** in questa fase.
