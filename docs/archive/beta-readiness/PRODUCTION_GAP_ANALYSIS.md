# PRODUCTION_GAP_ANALYSIS (FASE 0 — obbligatorio)

> Audit sul **codice reale** prima di implementare. Da "White Label Engine pronto" a "BETA reale usabile ogni giorno". Legenda: 🟢 pronto · 🟡 migliorare · 🔴 bloccante per la beta. Implemento **solo i gap**, additivo, senza toccare booking/auth/payments/scheduling/notification core.

## Sintesi
Il motore c'è (180 test verdi). Per la **beta usabile** mancano: (1) una **vera coda** di build (oggi il dispatch è sincrono, niente stato `queued`), (2) la **timeline** delle build (created/started/finished), (3) un **canale di distribuzione beta** (oggi solo download manifest/ZIP; nessun APK/IPA installabile), (4) una **CI di test** (push→analyze→test→build) e la separazione **staging/production**.

## BUILD PIPELINE
| Area | Stato | Nota |
|---|---|---|
| Dispatcher / contract | 🟢 | `BuildDispatcher` + driver `manual`/`github`. |
| Build driver (manual/github) | 🟢 | `manual` logga l'intento; `github` fa `workflow_dispatch` via API (segreti ENV). |
| **Queue** | 🟡 | `DispatchAppBuild` è **sincrono**: manca `AppProject → BuildRequest → Queue Job → Worker`. Coda Laravel `database` **già configurata** (usata da `SendNotificationJob`). Manca lo stato `queued`. |
| Webhook esito | 🟢 | l'esito rientra via `php artisan app:build-record {uuid} {platform} {status} --error=` (callback CI). |
| Artifact storage | 🟢 | manifest/pacchetto self-contained su disco + download/ZIP. Gli artifact nativi (AAB/IPA) li produce la CI (esterni), tracciati in `app_builds.artifact_path`. |

## FLUTTER
| Area | Stato |
|---|---|
| build configurabile (gradle property + xcconfig + `make_app.sh`) | 🟢 |
| dart-define completo (ENV/API_BASE_URL/TENANT_KEY/APP_NAME/TEMPLATE) | 🟢 |
| assets injection (launcher icons / native splash dall'Asset Factory) | 🟢 |
| app name / package name / bundle id dinamici | 🟢 |
| icon pipeline | 🟢 |

## LARAVEL
| Area | Stato | Nota |
|---|---|---|
| `app_projects` + lifecycle | 🟢→🟡 | manca lo stato `queued`. |
| `app_builds` | 🟡 | manca la **timeline** (`queued_at`/`started_at`/`finished_at`). `error_message` ✅. |
| error handling | 🟢 | `failed` + `error_message`. |
| storage / queue (config) | 🟢 | disco + coda `database` pronti. |

## DEVOPS
| Area | Stato | Nota |
|---|---|---|
| GitHub Actions | 🟡 | `app-factory-build` + `app-factory-batch` ci sono; **manca** una CI di test (analyze/test/php test) e la separazione staging/production. |
| Secrets / environment | 🟡 | documentati per release; manca separazione esplicita **LOCAL/STAGING/PROD**. |
| Signing | 🟢 | Android env-based; iOS account del cliente. |

## BETA DISTRIBUTION
| Area | Stato | Nota |
|---|---|---|
| Canale beta installabile | 🔴 | oggi solo download manifest/ZIP. Per "usarla ogni giorno" serve **Firebase App Distribution** (Android) e **TestFlight** (iOS), o link APK privato. È il gap principale. |

## OSSERVABILITÀ
| Area | Stato | Nota |
|---|---|---|
| logging build | 🟡 | `LogBuildDispatcher` logga; manca la **timeline** per build e i contatori riuscite/fallite nella Control Room. |

## Piano (solo i gap — additivo)
1. **FASE 1 — Pipeline a coda**: stato `queued`; `BuildService` (validazione progetto → crea `app_builds(queued)` → transizione `Queued` → enqueue) + `RunAppBuildJob` (ShouldQueue: `building` → driver dispatch → artifact; su errore `failed`+timeline+error_message). **Sostituisce** `DispatchAppBuild` (niente doppione). Timeline `queued_at/started_at/finished_at`.
2. **FASE 6 — Timeline/observability**: colonne timeline + `app:build-record` setta `finished_at`; logging strutturato nel job.
3. **FASE 7 — Control Room premium**: contatori build riuscite/fallite + ultime versioni nella dashboard Flotta.
4. **FASE 3 — CI**: `.github/workflows/ci.yml` (php test + flutter analyze + flutter test) + note staging/production.
5. **FASE 4 — Beta**: step Firebase App Distribution nel workflow build (gated da secret) + `BETA_RELEASE.md`.
6. **FASE 5 — Environments**: `.env.example` LOCAL/STAGING/PROD + doc.
7. **FASE 8 — Test**: `BuildServiceTest`, lifecycle/timeline, artifact.
8. **FASE 9/10 — Docs**: `PRODUCTION_READY_RUNBOOK.md` + `PRODUCTION_BETA_READINESS.md`.

## Fuori scope (infra esterna — interfaccia+config+doc, non eseguibile qui)
Compilazione nativa (Mac/CI), Firebase project, account store, esecuzione reale CI. Per ognuno: interfaccia pronta + config via ENV + documentazione + placeholder sicuro.
