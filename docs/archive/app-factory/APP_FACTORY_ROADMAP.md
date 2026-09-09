# APP_FACTORY_ROADMAP — Control Room → App Factory

> Progetto tecnico **design-only** (nessun codice). Data: 2026-06-14.
> Fonti di verità: codice reale + `docs/27-white-label-tecnico.md`. Deliverable gemello operativo: [CONTROL_ROOM_READINESS.md](CONTROL_ROOM_READINESS.md), [PRODUCT_DESIGN_ROADMAP.md](PRODUCT_DESIGN_ROADMAP.md).

## Contesto
Evoluzione della Control Room nel **sistema operativo proprietario** che porta un cliente da "nuovo" ad "app personalizzata pronta": un solo codice Flutter, configurazione e identità per tenant. Modello: 10 clienti premium ("app proprietaria + gestione piattaforma"), ognuno con app separata nello store (nome/icona/bundle propri) ma **stesso motore**.

**Decisioni di indirizzo (confermate):**
1. **Template = solo skin visive** sopra il motore appuntamenti (nessuna modifica al booking engine; "ristorante" = skin che mappa il tavolo su un appuntamento).
2. **"Genera App" FASE 1 = solo pacchetto di preparazione** (config + identificativi + icone/splash + manifest; nessuna build/pubblicazione reale).

**Riuso, non riprogettazione:** `docs/27-white-label-tecnico.md` contiene già la strategia (compilato-vs-runtime, pipeline build per-tenant, convenzione bundle id, generazione asset, tracking `app_builds`, anteprima live). Questo roadmap la **implementa**.

---

## 1. Analisi stato attuale (cosa si riusa, cosa manca)
| Componente | Dove (reale) | Riuso / Gap |
|---|---|---|
| Onboarding automatico | `ProvisionTenant` (tenant+owner+invito+brand+sede+orari+catalogo) | **RIUSO TOTALE.** Nessun campo store sul tenant |
| Branding | `BrandProfile` (app_name, tagline, colori, theme json, config_version) + **`brand_assets`** (kind `logo`/`icon_source`/`splash_source` già previsti) | **RIUSO.** Generazione icone/splash dal master non implementata |
| Config runtime | `BuildWhiteLabelConfig` + `GET /app/config` + `WhiteLabelConfig` (Flutter) | **RIUSO.** Manca esporre `logo_url`, `template`, `font_style` |
| Flutter shell | Codice unico; identità via `--dart-define` (`lib/core/env/app_environment.dart`: ENV/API_BASE_URL/TENANT_KEY); tema runtime dai token | **RIUSO.** Bundle id HARDCODED (`com.platform.client_app` / `clientApp`), nessun flavor, nessun tooling icone/splash, **un solo layout** |
| Dashboard professionista | `/dashboard/personalizzazione` (nome/colori/contatti) | **RIUSO.** Manca self-service logo/immagini + anteprima live |
| Control Room | `/control-room/clienti` (CRUD tenant, lifecycle, brand quick-setup, logo upload) + guard `admin` + audit + `StoreBrandLogo` | **RIUSO.** Manca sezione `/control-room/apps` + generatore |
| Pipeline build / store | `docs/27` §3/§6 (design) | **DA IMPLEMENTARE** (non duplicare): `app_builds`, allocazione bundle id, asset pipeline, generator, `POST /manage/brand/preview` |

**Regola anti-duplicazione:** `ProvisionTenant` resta l'unico punto di onboarding; il brand vive in `BrandProfile` (non duplicato nell'App Project).

---

## 2. Nuovo modulo: App Factory
- Nuova sezione Control Room **`/control-room/apps`**: ogni cliente diventa un **App Project** (es. "Giuffrida Barber") con uno **stato di build** (distinto da `tenant.status`): `draft` (configurazione) → `ready` (pronta) → `generated` (pacchetto prodotto). [FASE 2: `building` → `published`].
- Modulo `app/Modules/AppFactory/*` (Clean Architecture come gli altri): `Application/` (AllocateAppIdentifiers, GenerateAppPackage, GenerateAppIcons), `Infrastructure/Models/` (AppProject, AppBuild), `Http/Controllers/` (AppProjectController). Riusa `ProvisionTenant`, `BrandProfile`, `brand_assets`, `BuildWhiteLabelConfig`, `AuditLogger`.

---

## 3. Database necessario (2 tabelle nuove, nessuna modifica distruttiva)
**`app_projects`** (1:1 con tenant — identità store/build, NON il brand):
- `id, uuid, tenant_id` (unique FK)
- `slug` (unique), `shortcode` (per convenzione bundle id), `store_name` (max 30)
- `bundle_id` (unique, iOS), `package_name` (unique, Android) — allocati alla creazione, **immutabili**, convenzione `com.<platform>.t<shortcode>` (docs/27 §3)
- `template_code` (chiave Template Engine), `font_style` (chiave curata)
- `powered_by_enabled` (bool, default **true**, sempre true)
- `build_status` (string enum), `last_generated_at` (nullable), `build_manifest` (json, ultimo pacchetto)
- timestamps
> Brand (colori/tema/logo) e legal (privacy/terms) **restano in `BrandProfile`/`brand_assets`** e si referenziano — niente duplicazione.

**`app_builds`** (storico generazioni, da docs/27):
- `id, uuid, app_project_id` FK, `version` (es. `1.0.0+3`), `platform` (`config`/`android`/`ios`), `status` (`generated`/`built`/`published`/`failed`), `artifact_path` (manifest/zip in FASE 1), `notes`, `created_by` (super_admin), timestamps.

---

## 4. Onboarding cliente automatico (riuso, zero DB manuale)
"Crea App Project" in Control Room esegue in sequenza:
1. **`ProvisionTenant::execute`** (esistente) → tenant + owner + invito + brand default + sede + orari + catalogo del settore.
2. **AllocateAppIdentifiers** → `app_projects` con bundle_id/package_name/slug/shortcode, `template_code`, `store_name`, `powered_by=true`, `build_status=draft`.

Tutto dalla UI, nessun inserimento diretto in database. `ProvisionTenant` non viene duplicato né modificato.

---

## 5. Cliente professionista (self-service, 250€/mese)
Il titolare modifica il **brand** senza chiamarti, dalla **sua** dashboard:
- Estendere `/dashboard/personalizzazione`: upload **logo/immagini** (riuso `StoreBrandLogo` + `brand_assets`), colori, info attività. Effetto runtime immediato (`config_version`).
- **Anteprima live** (docs/27 §8): `POST /manage/brand/preview` rende i token su mock di schermate (render web, nessuna build).
- **Confine netto:** il tenant cambia il BRAND (runtime); **non** tocca template/bundle/generazione app (App Project = super-admin). La Control Room resta tua.

---

## 6. Template Engine (skin visive, motore invariato)
- Template = **preset curati dalla piattaforma**, config-first: `config/app_templates.php` (come `sector_presets.php`). Ogni template: `{ code, label, vertical, theme (colori/raggi/tipografia), font_style (curato), layout_variant, sections (quali sezioni e ordine), slot immagini }`.
- Esempi FASE 1: `barber_dark` (scuro, elegante), `beauty_visual` (immagini, staff), `restaurant_visual` (foto grandi, sezione "menu", "prenota tavolo" → **richiesta di appuntamento**). Tutti **sopra il motore appuntamenti** — solo presentazione.
- Lato Flutter: `WhiteLabelConfig` guadagna `template` / `layout` / `font_style`; l'app sceglie una **variante di layout** a runtime (Home/Scheda hanno 2-3 varianti). Font da **whitelist curata** (2-4 font impacchettati), mai libero (guardrail).
- Il tenant **sceglie** un template (o glielo assegni); non disegna layout/font → qualità coerente.
- Storage: preset in config (FASE 1); template in DB con editor (FASE 3).

---

## 7. App Generator v1 (solo preparazione)
Control Room "Genera App" → `GenerateAppPackage` produce (nessuna build reale):
1. **Config nativa** dal template: applicationId/bundleId (da `app_projects`), `store_name`, versione.
2. **Asset dal logo master**: set icone (Android adaptive + iOS) + splash multi-densità, con validazione (dimensioni minime, margini sicuri). Generazione **server-side** (i tool `flutter_launcher_icons`/`flutter_native_splash` sono assenti) → salvati come `brand_assets` (`icon_source`/`splash_source` + derivati) + anteprima.
3. **Build manifest** (json): valori `--dart-define` (ENV, API_BASE_URL, TENANT_KEY, app_name, bundle_id, template) + percorsi asset + placeholder firma — tutto ciò che una CI futura consumerà.
4. **Pacchetto scaricabile** (zip/JSON: manifest + asset) → record `app_builds` (`status=generated`).

> FASE 2: una CI consuma il manifest → `flutter build appbundle` (Android, account piattaforma); iOS **assistito** (account Apple del cliente, policy 4.2.6/4.3).

---

## 8. White Label Rules
**Può cambiare** (per tenant): logo, colori, immagini, testi/tagline, **template/skin**, `font_style` (curato), nome app, store_name, contatti, URL legali.
**NON cambia** (platform-fixed): booking engine + flussi, auth/sicurezza, database/schema, isolamento tenant, struttura componenti, **`powered_by` (sempre true)**, semantica colori di stato.
Estende i guardrail di `PRODUCT_DESIGN_ROADMAP.md`: template e font sono **scelte curate**, non design libero.

---

## 9. Sicurezza
- **Solo super_admin** crea App Project e genera pacchetti → `/control-room/apps` dietro guard `admin` + `EnsureSuperAdmin` (riuso). Il cliente **non** genera app.
- **Isolamento**: un tenant non vede app/dati di altri (scope esistente, testato); il dashboard tenant non espone l'App Factory.
- `bundle_id`/`package_name`/`slug` **UNIQUE** (no collisioni cross-tenant); bundle id mai riusato.
- **Audit** su create/allocate-identifiers/generate (riuso `AuditLogger`); asset validati (mime/size/dimensioni).
- Test: super_admin-only; `tenant_admin`/`customer`/guest → 403/redirect su `/control-room/apps`; un App Project non referenzia asset di altri tenant; generazione audit-loggata.

---

## 10. API e schermate (FASE 1)
**API Control Room (web, `auth:admin` + `control.admin`):** `GET /control-room/apps`, `GET /control-room/apps/{uuid}`, `POST /control-room/apps` (crea App Project → ProvisionTenant + AllocateAppIdentifiers), `POST /control-room/apps/{uuid}/genera` (GenerateAppPackage), `GET /control-room/apps/{uuid}/download/{build}`.
**API tenant (self-service):** estende `PUT /manage/brand` (+ logo/immagini via `StoreBrandLogo`), nuovo `POST /manage/brand/preview` (anteprima live).
**Config app:** `GET /app/config` esteso (additivo, retrocompatibile) con `logo_url`, `template`, `font_style`.

**Schermate Control Room:** lista App Project (nome, template/vertical, bundle/package, build_status, ultima generazione); scheda App Project (identità store read-only post-alloc, selettore template, asset+anteprima icone/splash, pulsante "Genera pacchetto", storico `app_builds` con download); form "Crea App Project". **Dashboard tenant:** personalizzazione estesa (logo/immagini + anteprima live).

---

## 11. Rischi
- **iOS store**: Apple richiede l'account Developer **del cliente** per app template (4.2.6/4.3, docs/27 §6) → non automatizzabile sotto un unico account; FASE 1 prepara, iOS assistito in FASE 2.
- **Play "app ripetitive"**: mitigare con contenuti store reali per tenant (descrizione/screenshot dal catalogo) — docs/27 §6.
- **Bundle id immutabile** dopo pubblicazione → allocare con cura, mai riusare (docs/27 §1).
- **Qualità asset**: logo a bassa risoluzione → validazione dimensioni/margini in generazione.
- **Layout template = lavoro UI Flutter reale** (non solo config) → coordinare con `PRODUCT_DESIGN_ROADMAP.md` (varianti Home/Scheda).
- **Costo build iOS** (runner macOS scarsi) → coda/concorrenza in FASE 2.

---

## 12. Ordine di implementazione
**FASE 1 — necessaria per i primi 10 clienti**
1. Migrazioni + modelli `app_projects`, `app_builds`.
2. `AllocateAppIdentifiers` (bundle/package/slug/shortcode, convenzione docs/27).
3. `/control-room/apps`: lista + scheda + "Crea App Project" (wrappa `ProvisionTenant`).
4. `config/app_templates.php` (Template Engine) + selettore template nella scheda.
5. Estendere `/app/config` con `logo_url` + `template` + `font_style` (additivo).
6. `GenerateAppPackage` v1 (config + manifest + generazione icone/splash + download) → `app_builds`.
7. Self-service brand cliente (logo/immagini in dashboard) + `POST /manage/brand/preview` (anteprima live).
8. Varianti di layout Flutter (2-3) guidate da `template`/`layout` — UI, coordinata con design roadmap.
9. Test sicurezza/isolamento + regressione (i 104 test restano verdi).

**FASE 2 — automazioni**
CI build Android (AAB) dal manifest + firma (keystore vault) + upload Play API; iOS assistito (onboarding account Apple) + build ipa; tracking `app_builds` fino a `published`; treno di rilascio core (rebuild batched + canary).

**FASE 3 — scalabilità 100+**
Template DB-backed + editor; nuovi paradigmi (es. ristorante con vero table-booking → estensione engine, progetto separato); pool runner macOS, code/concorrenza, metriche e osservabilità.

---

## Verifica (a implementazione avvenuta)
- `php artisan test`: nuova suite App Factory verde **e** i 104 test esistenti invariati.
- Smoke Control Room: crea App Project → identità allocata (bundle/package unici) → scelta template → genera pacchetto → scarica manifest+asset; il tenant modifica logo/colori dalla sua dashboard e vede l'anteprima; `tenant_admin` riceve 403 su `/control-room/apps`.
- Nessuna modifica a booking engine, auth, schema esistente (solo tabelle additive).

## Stato di verifica
Tutto deriva da lettura diretta del codice reale (`ProvisionTenant`, `BrandProfile`/`brand_assets`, `BuildWhiteLabelConfig`, `app_environment.dart`, config nativa Android/iOS, Control Room) e da `docs/27`. **Nessun file applicativo è stato modificato** in questa fase di progettazione.
