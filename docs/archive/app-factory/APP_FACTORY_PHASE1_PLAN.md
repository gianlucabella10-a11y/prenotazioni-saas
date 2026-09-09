# APP_FACTORY_PHASE1_PLAN — Piano di implementazione (file per file)

> Esecuzione della **FASE 1** di `APP_FACTORY_MASTER_PLAN.md`: livello **App Project + Template + manifest**, config-first, **nessuna build nativa reale**. Additivo: non tocca booking engine, auth, schema esistente. Baseline da non rompere: **backend 110 test · Flutter 32 test · analyze pulito**.
> Stato verificato: modulo `AppFactory`, tabelle `app_projects`/`app_builds`, `config/app_templates.php`, rotte `/control-room/apps` = **tutti ASSENTI** (si crea da zero).

## Obiettivo FASE 1 (Definition of Done globale)
Dal Control Room posso: creare un cliente **scegliendo un template** → l'**App Project** viene allocato con **bundle id/package/slug unici** → aprire `/control-room/apps`, vederlo, **"Genera pacchetto"** → scaricare il **manifest** (identità + `--dart-define` + asset) → `/app/config` espone `template`/`font_style`. Tutto senza build nativa, con test verdi e zero regressioni.

## Riuso (NON duplicare)
`ProvisionTenant` (onboarding), `BrandProfile`+`brand_assets`, `BuildWhiteLabelConfig` (+ già esteso con logo/contacts/social/hours), Control Room (`guard admin`, `EnsureSuperAdmin`, `control_room/layout`, `AuditLogger`, `TenantsController`), `config/sector_presets.php` (pattern per `app_templates.php`), helper test `InteractsWithTenancy`, pattern `ControlRoom*Test`.

---

## STEP 1 — DB & modelli (fondamenta)
**File NUOVI**
- `database/migrations/2026_06_16_000000_create_app_factory_tables.php`
  - `app_projects`: id, uuid (unique), `tenant_id` (unique, FK constrained), `slug` (unique), `shortcode` (unique), `store_name`, `bundle_id` (unique), `package_name` (unique), `template_code`, `font_style` (nullable), `powered_by_enabled` (bool default true), `build_status` (string default `draft`), `last_generated_at` (nullable), `build_manifest` (json nullable), timestamps.
  - `app_builds`: id, uuid (unique), `tenant_id` (FK), `app_project_id` (FK cascade), `version`, `platform` (string), `status` (string), `artifact_path` (nullable), `created_by` (nullable, user id), timestamps.
- `app/Modules/AppFactory/Infrastructure/Models/AppProject.php` — `use BelongsToTenant` (auto-scope, come `BrandProfile`) + `HasUuids`; cast `build_manifest`→array, `last_generated_at`→datetime, `powered_by_enabled`→bool; relazione `builds()`.
- `app/Modules/AppFactory/Infrastructure/Models/AppBuild.php` — `BelongsToTenant` + `HasUuids`; relazione `appProject()`.
- `app/Modules/AppFactory/Domain/AppProjectStatus.php` — enum string (`Draft`, `Ready`, `Generated`) come `TenantStatus`.
- `database/factories/AppProjectFactory.php`, `database/factories/AppBuildFactory.php` — per i test.

**Test:** nessuno isolato (coperto dagli step successivi). **Completamento:** `php artisan migrate` ok; `php artisan test` resta verde (110).

---

## STEP 2 — Config (template + identità)
**File NUOVI**
- `config/app_templates.php` — array di template curati (pattern `sector_presets.php`): `barber_dark`, `beauty_visual`, `medical_clean`, `restaurant_visual`. Ogni voce: `{ label, vertical, theme: {colors, radius, typography}, font_style, layout_variant, sections: [..] }`. Più una chiave `default`.
- `config/app_factory.php` — `bundle_prefix` (env `APP_FACTORY_BUNDLE_PREFIX`, default `com.platform`), `manifest_disk` (default `local`).

**Test:** `AppTemplatesConfigTest` (Unit) — i template attesi esistono e hanno le chiavi obbligatorie (`label`, `theme`, `layout_variant`). **Completamento:** `config('app_templates.barber_dark.layout_variant')` risolve; test verde.

---

## STEP 3 — Application: AllocateAppIdentifiers
**File NUOVO**
- `app/Modules/AppFactory/Application/AllocateAppIdentifiers.php`
  - Input: `Tenant`, `templateCode` (validato contro `config('app_templates')`, fallback `default`), `actorUserId`.
  - **Idempotente**: se il tenant ha già un `AppProject`, lo ritorna invariato.
  - Genera: `shortcode` deterministico/unico (es. base36 da `tenant.id` + suffisso anti-collisione), `bundle_id`/`package_name` = `{bundle_prefix}.t{shortcode}` (unique, **immutabile**), `slug` (kebab di display_name + shortcode), `store_name` (da `display_name`, max 30), `font_style` dal template.
  - Crea `AppProject` (`build_status=Draft`, `powered_by_enabled=true`). Audit `app_project.allocated`.
  - Gira sotto `CurrentTenant::bypass` se chiamato platform-level (Control Room), come gli altri Application.

**EDIT (riuso onboarding, una riga):**
- `app/Modules/ControlRoom/Http/Controllers/TenantsController.php` → in `store()`, dopo `ProvisionTenant`, chiama `AllocateAppIdentifiers` col `template_code` scelto nel form. (Ogni cliente = un'app: allocare alla creazione è coerente.)
- `resources/views/control_room/tenants/create.blade.php` → aggiungi selettore **Template** (da `config('app_templates')`); `store()` valida `template_code`.

**Test:** `AllocateAppIdentifiersTest` (Feature) — bundle/package/slug **unici**; idempotenza (stesso tenant → stesso project); due tenant → identità distinte; template invalido → fallback `default`. **Completamento:** creando un cliente dal Control Room nasce un `AppProject` con identità uniche; lifecycle test esistenti restano verdi.

---

## STEP 4 — Application: GenerateAppPackage (manifest, no build reale)
**File NUOVO**
- `app/Modules/AppFactory/Application/GenerateAppPackage.php`
  - Input: `AppProject` (o uuid), `actorUserId`.
  - Costruisce il **manifest** (array→json): `bundle_id`, `package_name`, `store_name`, `app_name` (da `BrandProfile`), `template_code`/`font_style`, `--dart-define` (`ENV`, `API_BASE_URL` placeholder, `TENANT_KEY`=`tenant.api_key`, `APP_NAME`, `TEMPLATE`), riferimenti asset (logo da `brand_assets`), `config_version`.
  - Salva il manifest su `manifest_disk` (`app_factory/{tenant_uuid}/manifest-v{n}.json`).
  - Crea `AppBuild` (`platform=config`, `status=generated`, `artifact_path`), aggiorna `AppProject` (`build_status=Generated`, `last_generated_at`, `build_manifest`). Audit `app_project.generated`.
  - **v1: nessun `flutter build`** (preparazione, come da master plan).

**Test:** `GenerateAppPackageTest` (Feature) — il manifest contiene `bundle_id` e `TENANT_KEY` corretti + `template`; crea una riga `app_builds`; `build_status` → `generated`; rigenerazione incrementa la versione. **Completamento:** manifest scaricabile e coerente; test verde.

---

## STEP 5 — Control Room: sezione /control-room/apps
**File NUOVI**
- `app/Modules/AppFactory/Http/Controllers/AppProjectController.php` — `index` (lista App Project con stato), `show` (scheda: identità store read-only, selettore template, logo/preview, storico `app_builds`), `updateTemplate` (PUT, cambia `template_code`/`font_style` + bump `config_version`), `generate` (POST → `GenerateAppPackage`), `download` (GET artifact manifest). Tutto via `CurrentTenant::bypass` + `AuditLogger`.
- `resources/views/control_room/apps/index.blade.php` — tabella: attività, template, bundle_id, build_status, ultima generazione, azioni.
- `resources/views/control_room/apps/show.blade.php` — identità store, form template, pulsante "Genera pacchetto", lista build con download.

**EDIT**
- `routes/web.php` → nel gruppo `control-room` (auth:admin + control.admin): `GET /apps` (`control.apps.index`), `GET /apps/{uuid}` (`control.apps.show`), `PUT /apps/{uuid}/template` (`control.apps.template`), `POST /apps/{uuid}/genera` (`control.apps.generate`), `GET /apps/{uuid}/download/{build}` (`control.apps.download`).
- `resources/views/control_room/layout.blade.php` → voce di menu "App" (accanto a "Clienti").

**Test:** `AppProjectControlRoomTest` (Feature) — super_admin: index/show/generate OK; `tenant_admin`/`customer`/guest → 403/redirect (isolamento, come `ControlRoomAccessTest`); `updateTemplate` cambia template e bumpa `config_version`. **Completamento:** flusso completo navigabile; isolamento verde.

---

## STEP 6 — Contratto config + Flutter (additivo)
**EDIT backend**
- `app/Modules/Branding/Application/BuildWhiteLabelConfig.php` → aggiungi al payload `template` e `font_style` letti dall'`AppProject` del tenant (auto-scoped), con merge dei default da `config('app_templates')`; null-safe se l'App Project non esiste.

**EDIT Flutter**
- `lib/features/white_label/domain/white_label_config.dart` → campi `template`, `layout`, `fontStyle` (parse da `/app/config`, default null/`default`). *(Le varianti di layout vere in Home/Scheda sono FASE 2.)*

**Test:**
- Backend: estendi `WhiteLabelConfigTest` — `/app/config` espone `template`/`font_style` (null-safe; riflette l'App Project quando impostato).
- Flutter: estendi `test/unit/white_label_config_test.dart` — parsing di `template`/`font_style`.
**Completamento:** `php artisan test`, `flutter analyze`, `flutter test` verdi.

---

## Test richiesti (riepilogo)
Backend (nuovi): `AppTemplatesConfigTest`, `AllocateAppIdentifiersTest`, `GenerateAppPackageTest`, `AppProjectControlRoomTest` + estensione `WhiteLabelConfigTest`. Flutter: estensione `white_label_config_test`. **Regressione obbligatoria:** i 110 backend + 32 Flutter restano verdi.

## Ordine di esecuzione (dipendenze)
STEP 1 → 2 → 3 → 4 → 5 → 6. Dopo ogni step: `php artisan test` (e da STEP 6 anche `flutter analyze`/`flutter test`). Pint a fine di ogni step backend.

## Criteri di completamento FASE 1
1. `php artisan migrate` ok; `app_projects`/`app_builds` create (additive).
2. Creazione cliente dal Control Room → `AppProject` con **bundle_id/package_name/slug unici e immutabili**.
3. `/control-room/apps`: lista + scheda + cambio template + **"Genera pacchetto"** → manifest scaricabile + `app_builds` tracciato + `build_status=generated`.
4. `/app/config` espone `template`/`font_style` (null-safe).
5. **Isolamento**: solo super_admin accede a `/control-room/apps` (tenant_admin/customer/guest negati) — testato.
6. **Suite verde**: backend (110 + ~12 nuovi) e Flutter (32 + 1) + analyze pulito + Pint pulito.
7. **Nessuna build nativa**, nessuna modifica a booking/auth/schema esistente (solo tabelle additive).

## Rischi & mitigazioni
- *Accoppiamento creazione-cliente ↔ app project*: voluto (ogni tenant è un'app); `AllocateAppIdentifiers` è idempotente → nessun doppione, lifecycle test invariati.
- *Unicità identificatori*: vincoli `unique` su bundle_id/package_name/slug/shortcode + generazione anti-collisione; test dedicato.
- *Regressione Control Room*: i test esistenti (`ControlRoomTenantManagementTest`) non asseriscono l'assenza di app_projects → l'aggiunta è trasparente; verificare comunque a fine STEP 3.

## Fuori scope (FASE 2/3, non implementare ora)
Asset Factory (icone/splash reali), build parametrica `flutter build`, varianti di layout Flutter, font whitelist, Play/App Store API, CI matrix.
