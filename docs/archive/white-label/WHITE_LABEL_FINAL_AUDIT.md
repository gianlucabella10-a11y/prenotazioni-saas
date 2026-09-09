# WHITE_LABEL_FINAL_AUDIT

> Audit sul **codice reale** prima di qualsiasi modifica (richiesto). Obiettivo: chiudere il motore white-label al 100% — Control Room → crea cliente → identità → pacchetto → pronto per build — **senza codice per cliente**. Legenda: 🟢 pronto · 🟡 migliorabile · 🔴 bloccante prima del design.

## Esito sintetico
**Nessun 🔴.** Il motore è funzionante end-to-end e già testato (148→159 test nelle fasi precedenti). Restano **3 hardening 🟡** che chiudo in questa fase: (1) guard di immutabilità dell'identità a livello di model, (2) `config.json` + README nel pacchetto esportato, (3) test espliciti di isolamento in *contesto tenant* (oggi l'isolamento c'è ed è testato in generazione, ma non sulla query scoped).

## BACKEND
| Area | Stato | Evidenza |
|---|---|---|
| `tenants` | 🟢 | multi-tenant + `TenantScope` globale + `CurrentTenant` (snapshot). |
| `brand_profiles` | 🟢 | app_name/colori/contatti/social + `config_version` (ETag). |
| `brand_assets` | 🟢 | kind logo/icon/splash + `variant` + `version` + checksum; per-tenant, isolati (test `GenerateBrandAssetsTest::assets_are_isolated_per_tenant`). |
| `app_projects` | 🟡 | identità unica (DB unique su bundle_id/package_name/shortcode/slug) + allocazione **idempotente**; **manca** un guard a livello model che impedisca la mutazione dell'identità dopo la creazione. |
| `app_builds` | 🟢 | storico per-piattaforma (config/android/ios) + status + artifact + version, con `tenant_id`. |
| `BuildWhiteLabelConfig` | 🟢 | template via `TemplateRegistry` (+ `sections`), payload di cortesia per tenant sospesi/terminati. |
| `PrepareApp` | 🟢 | orchestratore unico: asset → manifest → export (Control Room + `app:generate`). |
| `ExportAppPackage` | 🟡 | produce `generated_apps/{uuid}/` con `manifest.json` + `assets/` + `config/build.env` + `config/template.json`; **manca** un `config.json` operatore-facing e un `README.txt` (richiesti dalla spec del pacchetto). |
| Control Room | 🟢 | crea cliente → stato app → branding/preview → genera → **scarica ZIP**; download pre-generazione → 404 con messaggio. |
| device/push | 🟢 | `Device` keyed su `(user_id, fcm_token)`, `tenant_id` rispecchia l'utente; push interroga **solo per `user_id`** → isolamento via ownership. *Niente* `BelongsToTenant` per scelta: il job di push gira senza contesto tenant, un global scope lo romperebbe. |

## FLUTTER (white-label runtime)
| Area | Stato | Evidenza |
|---|---|---|
| `WhiteLabelConfig` | 🟢 | template/layout/font/**sections**/logo/contatti/social/orari. |
| theme system | 🟢 | `AppThemeBuilder` da token + font; unico `Colors.white` come fallback semantico di `onError`. |
| asset loading | 🟢 | logo via URL runtime (ETag/config_version). |
| environment config | 🟢 | `AppEnvironment` da `--dart-define`; `validate()` fail-fast se manca `TENANT_KEY`. |
| API bootstrap | 🟢 | Dio + header `X-Tenant-Key`. |
| tenant resolution | 🟢 | `ResolveTenantFromKey` (header) lato API. |
| app identity | 🟢 | **nessun riferimento cliente hardcoded** (grep su `lib/`), title da `config.appName ?? ''`, nessun colore/nome/asset fisso per-cliente. |

## SICUREZZA WHITE-LABEL
- Isolamento asset/config/app_project: garantito da `BelongsToTenant`/`TenantScope`; 🟡 **mancano test espliciti** che, in contesto Tenant A, le query scoped su `AppProject`/`BrandAsset`/`BrandProfile` **non** restituiscano righe di Tenant B (oggi testato l'isolamento in generazione via bypass, non la query scoped).
- Control Room solo super-admin: 🟢 (`EnsureSuperAdmin`, test forbidden/guest).

## BUILD PREPARATION (Android/iOS)
🟢 già predisposto (FASE 2B/2C): `applicationId`/`appName` da Gradle property; `Tenant.xcconfig` + `CFBundleDisplayName`; `make_app.sh` (icone/splash/identità) — **nessuna build/firma reale** (richiede Mac/CI + account store, documentato).

## Piano (solo i 3 hardening 🟡)
1. **Immutabilità identità** — guard `AppProject::booted()` che blocca la modifica di `uuid`/`bundle_id`/`package_name`/`shortcode` dopo la creazione (+ test collisione/rigenerazione/immutabilità).
2. **Pacchetto autosufficiente** — `ExportAppPackage` aggiunge `config.json` (identità+branding+environment+metadata, operatore-facing) e `README.txt` (come usarlo per la build).
3. **Test di isolamento white-label** — `WhiteLabelIsolationTest`: in contesto Tenant A nessuna riga di Tenant B su app_project/brand_asset/brand_profile.

## Fuori scope (confermato)
Niente template nuovi, niente design UI, niente modifiche a booking/auth, niente fork.
