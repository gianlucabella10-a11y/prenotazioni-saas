# SYSTEM_FLOW — Flusso tecnico attraverso l'architettura

> Diverso da `BUSINESS_FLOW.md` (narrativa di business, dal punto di vista del cliente) — questo documento segue lo stesso viaggio dal punto di vista **tecnico**: quale componente riceve cosa, quale scrive cosa. Nessun contenuto duplicato dove già disponibile altrove; qui solo l'attraversamento sistema-per-sistema, con nota dove l'ordine concettuale richiesto differisce dall'ordine reale di esecuzione nel codice.

```
UTENTE (operatore umano, super-admin)
        │  browser → /control-room (guard "admin", MFA)
        ▼
CONTROL ROOM  — App\Modules\ControlRoom
        │  TenantsController::store()
        ▼
CLIENTE → TENANT  — App\Modules\TenantManagement
        │  ProvisionTenant::execute() (1 transazione DB, sotto CurrentTenant::bypass())
        │  scrive: tenants, subscriptions, brand_profiles, locations,
        │          location_schedules, service_categories/services/service_variants,
        │          users (tenant_admin), password_reset_tokens (invito)
        │  subito dopo: AllocateAppIdentifiers::execute()
        │  scrive: app_projects (bundle_id/package_name univoco e immutabile)
        ▼
CONFIGURAZIONE  — App\Modules\Branding + Dashboard/ControlRoom
        │  StoreBrandLogo::store() (valida, checksum, salva su disco)
        ▼
ASSET PIPELINE  — App\Modules\Branding\Application\GenerateBrandAssets
        │  GD puro: 9 icone + 3 splash + 2 store asset, versionate
        │  scrive: brand_assets (kind, variant, version, checksum)
        ▼
MANIFEST  — App\Modules\AppFactory\Application\GenerateAppPackage
        │  ⚠️ nota d'ordine: nel codice reale il manifest viene generato DOPO
        │     la asset pipeline (ne referenzia i path), non prima come nella
        │     sequenza concettuale richiesta — la sequenza tecnica reale è
        │     Asset Pipeline → Manifest, qui riportata correttamente
        │  scrive: app_factory/{tenant_uuid}/manifest-latest.json (disco)
        │  aggiorna: app_projects.build_manifest, build_status → ready_to_build
        ▼
APP GENERATION  — App\Modules\AppFactory\Application\ExportAppPackage
        │  assembla generated_apps/{tenant_uuid}/ (manifest+asset+config/build.env)
        │  scaricabile da Control Room come pacchetto self-contained
        ▼
BUILD  — App\Modules\AppFactory\Application\BuildService → RunAppBuildJob
        │  crea: app_builds (status: queued) → coda (driver "database")
        │  BuildDispatcher (manual/local/github):
        │    driver "local" → Symfony Process reale:
        │    flutter clean → pub get → analyze → test → build apk --release
        │    -PAPP_ID={package_name} -PAPP_NAME={app_name} --dart-define=...
        ▼
ARTIFACT  — storage/app/private/builds/{tenant_id}/{version}/app-release.apk
        │  checksum sha256 calcolato, app_builds aggiornato (status: built)
        ▼
BETA  — App\Modules\AppFactory\Http\Controllers\{AppProjectController,BetaDownloadController}
        │  crea: beta_download_tokens (token 48 char, scadenza 7gg, limite download)
        │  crea/gestisce: beta_testers (invited/active/blocked)
        ▼
INSTALLAZIONE  — dispositivo Android dell'utente finale
        │  scarica via BetaDownloadController::download() (streaming, download_count++)
        │  oppure via store (Google Play/App Store, CI)
        ▼
CLIENTE FINALE  — app Flutter installata
        │  POST /api/v1/auth/register → verifica email → GET /api/v1/app/config
        │  (WhiteLabelRepository, fetch a ogni avvio, ETag/cache)
        │  usa l'app: prenota, cancella, consulta appuntamenti
        ▼
FEEDBACK  — App\Modules\AppFactory\Http\Controllers\BetaFeedbackController
        │  POST /api/v1/me/feedback (autenticato) → crea: beta_feedback
        │  visibile in sola lettura in Control Room (ultimi 10 per app-project)
        ▼
ANALYTICS  — 🔴 GAP: lib/core/analytics/AnalyticsService esiste lato Flutter
        │  ma non è mai istanziato in app/providers.dart — nessun evento
        │  raggiunge realmente questo punto oggi (TECHNICAL_DEBT.md #3)
        ▼
VERSIONING  — App\Modules\AppFactory\Infrastructure\Models\AppVersion
        │  🔴 GAP: tabella app_versions popolata SOLO manualmente da Control
        │     Room — nessuna build reale la alimenta automaticamente, e
        │     l'APK stesso ha versionCode/versionName statici indipendenti
        │     da questa tabella (TECHNICAL_DEBT.md #2)
        ▼
RELEASE  — CI (.github/workflows/app-factory-build.yml) o Control Room
        │  distribuzione store (Play/App Store) condizionale ai segreti CI,
        │  oppure ridistribuzione beta con lo stesso meccanismo sopra
        ▼
CLIENTE FINALE (di nuovo — ciclo che si ripete a ogni versione)
```

## I 3 punti dove il flusso tecnico si interrompe prima del previsto

| Punto | Cosa dovrebbe succedere concettualmente | Cosa succede davvero |
|---|---|---|
| Analytics | Ogni azione utente alimenta un sistema di analisi | Nessun dato raccolto — il servizio esiste ma non è collegato |
| Versioning | La build reale alimenta automaticamente la cronologia versioni | Sono due sistemi paralleli scollegati (contatore DB vs. versione statica nell'APK) |
| Release → Cliente finale | Un aggiornamento raggiunge automaticamente i device già installati | Nessun forced-update — ogni "release successiva" è manuale, non push (`PLATFORM_LIFECYCLE.md` fase 9) |

Per il dettaglio di ognuno di questi tre, `TECHNICAL_DEBT.md`; per l'impatto operativo, `PLATFORM_LIFECYCLE.md` e `CONTROL_ROOM_ROADMAP.md`.
