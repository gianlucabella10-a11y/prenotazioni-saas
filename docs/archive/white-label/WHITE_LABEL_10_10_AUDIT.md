# WHITE_LABEL_10_10_AUDIT (FASE 0 — obbligatorio)

> Audit sul **codice reale** prima di implementare. Target: portare ogni pilastro a **10/10**. Legenda: 🟢 corretto · 🟡 da migliorare · 🔴 bloccante. Implemento **solo i gap**.

## Punteggi attuali → target
| Pilastro | Ora | Target | Gap principale |
|---|---|---|---|
| Backend white-label | 8.5 | 10 | lifecycle states incompleti, audit senza old→new sistematico |
| Control Room | 8.5 | 10 | manca trigger build + storico errori + preview splash |
| Asset Factory | 7.5 | 10 | **sovrascrive** i derivati (no storico/rollback), niente store assets |
| Build pipeline | 6 | 10 | manca l'**interfaccia di dispatch** build (worker è infra esterna) |
| Scalabilità | 9.5 | 10 | già registry/config/pipeline, nessun `if tenant==` |

## BACKEND
| Area | Stato | Nota |
|---|---|---|
| `AppProject` identità | 🟢 | unica + **immutabile** (guard `booted()`), allocazione idempotente. |
| `AppProject` lifecycle | 🟡 | stati: draft/ready/generated/ready_to_build/building/published/failed. **Mancano** `configured` e `built`; transizioni sparse, **non** loggate old→new. |
| Audit log | 🟡 | `AuditLogger` c'è (chi/quando/cosa/ip/subject) ma le transizioni di stato App non passano da un punto unico con **valore vecchio→nuovo**. |
| `AppBuild` | 🟡 | storico per-piattaforma + status; **manca `error_message`** per gli errori di build. |
| `GenerateAppPackage` / `ExportAppPackage` | 🟢 | manifest 2.0 + pacchetto self-contained (manifest/config/assets/env/README) + ZIP. |
| `BrandAsset` Asset Factory | 🟡→🔴 | genera icone/splash per-tenant versionati MA **cancella i derivati precedenti** ad ogni rigenerazione → viola "mai sovrascrivere / rollback / storico". Niente **store assets** (feature graphic, screenshot). |
| Tenant isolation / permissions | 🟢 | `TenantScope` + test in contesto tenant; Control Room solo super-admin. |

## FLUTTER
| Area | Stato | Nota |
|---|---|---|
| app config loading | 🟢 | `GET /app/config` + ETag/config_version. |
| branding / theme / assets | 🟢 | token + font + logo runtime; **zero hardcoding cliente** (verificato). |
| native configuration | 🟢 | `applicationId`/`appName` (Gradle property), `Tenant.xcconfig` + `CFBundleDisplayName`. |

## BUILD
| Area | Stato | Nota |
|---|---|---|
| Android parametrico | 🟢 | `-PAPP_ID/-PAPP_NAME` + firma env-based default-safe. |
| iOS parametrico | 🟢 | pbxproj/Info.plist a build-time (`make_app.sh`). |
| CI/CD | 🟡 | workflow per-tenant + batch (release train) **scaffoldati e validati**, mai eseguiti (richiedono runner+segreti). **Manca il trigger** dalla Control Room verso la pipeline. |

## Piano (solo i gap, additivo, niente booking/auth)
**FASE 1 — Lifecycle + audit**: `AppProjectStatus` + `configured`/`built`; `TransitionAppProject` (punto unico, logga `app_project.status_changed` con `{from,to}`, actor, subject). Wire: generate, record-build, logo upload (→configured).
**FASE 2 — Control Room**: trigger build (`DispatchAppBuild`) + bottoni Android/iOS; storico errori (`app_builds.error_message`); preview splash.
**FASE 3 — Asset Factory**: storico versioni **senza cancellare** (`brand_assets.is_current`) + `RollbackBrandAssets` + **store assets** (feature graphic 1024×500 + screenshot placeholder), versionati.
**FASE 4 — Build engine (interfaccia reale)**: `BuildDispatcher` (contract) + driver via config (`manual` default sicuro, `github` stub documentato che fa `workflow_dispatch`) + `DispatchAppBuild` che crea l'`AppBuild` e transita a `building`. **La compilazione resta su worker/CI** (infra esterna): interfaccia + config + placeholder sicuro + doc.
**FASE 5–6 — Isolation/parametrico**: convenzione workspace `builds/{tenant}/{version}/` documentata; build già parametrica (🟢).
**FASE 7 — Scala**: già a posto (registry/config/pipeline).
**FASE 8 — Quality gate** dopo ogni fase. **FASE 9 — `WHITE_LABEL_PRODUCTION_READY.md`**.

## Fuori scope (confermato)
Booking engine, auth, payments, core logic, fork, codice cliente, template nuovi. La build nativa reale e l'upload store restano infra esterna (Mac/CI + account): forniti **interfaccia + config + documentazione**.
