# FINAL_PRODUCT_ACTIVATION_AUDIT (FASE 0 — obbligatorio)

> Audit sul **codice reale** prima di implementare. Obiettivo: chiudere *Creo app → genero APK → installo → uso ogni giorno*. Legenda: 🟢 funziona davvero · 🟡 funziona ma va rinforzato · 🔴 blocca la beta. Implemento **solo i gap**.

## BACKEND
| Area | Stato | Nota |
|---|---|---|
| `AppProject` / lifecycle / identità | 🟢 | draft→configured→queued→building→built→published(+failed); identità immutabile. |
| `BuildService` + `RunAppBuildJob` + Queue | 🟢 | pipeline a coda `database`, worker, timeline. |
| `BuildDispatcher` + drivers (manual/github/**local**) | 🟢 | `local` esegue davvero `flutter build apk`. |
| **Build observability** | 🟡 | timeline (`queued/started/finished_at`) + `error_message` OK; **mancano** `build_log` completo, `command`, `exit_code`, `duration_ms`. Il runner esegue **solo** `flutter build apk` (non `pub get`/`analyze`/`test`) e cattura l'output **solo** in caso di errore. |
| Artifact / checksum / storage | 🟢 | `builds/{tenant}/{version}/app-release.apk` + SHA-256, mai sovrascritti. |
| Beta download | 🟢 | URL firmato + scadenza + solo build `built`. |
| Error handling | 🟢 | `failed` + `error_message` + transizione tracciata. |

## ANDROID BUILD
| Area | Stato | Nota |
|---|---|---|
| Flutter SDK | 🟢 | presente (`flutter analyze`/`test` verdi). |
| **Android SDK / Gradle / Kotlin** | 🔴 (in questo ambiente) | **assenti**: `flutter build apk` gira solo su build-machine/CI. Il driver `local` è reale e fallisce onestamente qui. |
| applicationId / package injection / versioning | 🟢 | `-PAPP_ID/-PAPP_NAME` parametrici. |
| signing / keystore / release | 🟢 | `build.gradle.kts` firma release da ENV (`ANDROID_KEYSTORE_*`); default debug se assenti. |

## CONTROL ROOM
| Area | Stato | Nota |
|---|---|---|
| crea app / config / genera / build trigger / artifact / download / lifecycle | 🟢 | flusso completo 1-click + Release center + Flotta. |
| **log build visibili** | 🟡 | si vede `error_message` troncato; manca il **log completo** consultabile dalla console. |
| **beta tester roster** | 🔴 | esiste `BetaFeedback`, **manca `BetaTester`** (invited/active/blocked) e il conteggio "beta attive". |

## SECURITY
| Area | Stato |
|---|---|
| tenant isolation (incl. build/artifact) | 🟢 (testato) |
| signed URL + expiry | 🟢 |
| permission / access control (Control Room super-admin) | 🟢 |

## MOBILE
| Area | Stato | Nota |
|---|---|---|
| API environment / tenant bootstrap | 🟢 | dart-define + `/app/config` + `validate()`. |
| crash reporting | 🟡 | **Sentry** già integrato (`sentry_flutter`); Crashlytics richiede Firebase (esterno). |
| **analytics prodotto** | 🔴 (per la beta) | **manca** un `AnalyticsService` con eventi prodotto (app_open/login/booking_*). |
| logging | 🟢 | Sentry breadcrumbs. |

## Piano (solo i gap — additivo, niente core/booking/auth/payments/UI)
1. **FASE 1 — Observability build**: colonne `app_builds` (`build_log`, `command`, `exit_code`, `duration_ms`); `LocalBuildDispatcher` esegue `pub get → analyze → test → build apk` catturando log/command/exit_code/durata (anche in caso di successo); log consultabile in Control Room.
2. **FASE 4 — Beta program**: `BetaTester` (tenant/name/email/device/status invited|active|blocked) + invito/blocco in Control Room + conteggio in dashboard.
3. **FASE 6 — Analytics**: `AnalyticsService` Flutter (eventi prodotto, sink pluggable, default no-op/debug; pronto per Firebase Analytics). Test.
4. **FASE 7 — Environments**: `ENVIRONMENT_SETUP.md` (setup operativo per ambiente).
5. **FASE 5 — Crash**: documentare Sentry attivo + come abilitare Crashlytics (Firebase). Nessun mock.
6. **FASE 8 — Console**: "beta attive" nella dashboard Flotta.
7. **FASE 9/10**: quality gate + `FINAL_BETA_READY_REPORT.md`.

## Fuori scope (infra esterna, reale, non eseguibile qui — nessun mock)
Android SDK/compilazione, Firebase (Crashlytics/Analytics/App Distribution), account store/Apple. Per ognuno: codice/driver reale + config ENV + documentazione.
