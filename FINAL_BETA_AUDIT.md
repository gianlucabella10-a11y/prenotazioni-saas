# FINAL_BETA_AUDIT (FASE 0 — obbligatorio)

> Audit sul **codice reale** prima di implementare. Obiettivo: chiudere il ciclo *Control Room → genera → build → link privato → installo APK → uso*. Legenda: 🟢 pronto · 🟡 migliorare · 🔴 bloccante per la beta installabile.

## Realtà dell'ambiente (onestà)
**Android SDK assente in questo ambiente** (`~/Library/Android/sdk` mancante, `ANDROID_HOME` non impostato, niente `sdkmanager`). Quindi `flutter build apk` **non è eseguibile qui**: gira su una build-machine/CI con l'SDK. Implemento un driver `local` **reale** (esegue davvero `flutter build apk`), non un mock; in questo sandbox fallisce in modo onesto (stato `failed` + `error_message` col vero output). Tutto il resto del ciclo (artifact, checksum, download privato firmato, feedback) è implementabile e **testabile qui** con una fixture artifact.

## BACKEND
| Area | Stato | Nota |
|---|---|---|
| `AppProject` / lifecycle | 🟢 | draft→configured→ready_to_build→queued→building→built→published (+failed); identità immutabile. |
| `AppBuild` | 🟡 | version/platform/status/artifact_path/error_message/timeline OK. **Manca `checksum`** (integrità artifact). |
| `BuildService` / Queue / `RunAppBuildJob` | 🟢 | pipeline a coda (`database`), worker, timeline. |
| `BuildDispatcher` + driver | 🟡 | `manual`/`github` (async/remoti). **Manca un driver `local`** che produca un **artifact reale** (APK) con risultato sincrono (completed+artifact+checksum). Il contract ritorna `string`: serve un risultato più ricco. |
| Storage / artifact handling | 🔴 (beta) | manifest/pacchetto su disco OK; **manca** la convenzione `builds/{tenant}/{version}/app-release.apk`, il **checksum** e soprattutto un **download privato** (link firmato, scadenza, tenant-check). |
| Control Room | 🟡 | scheda con build/timeline/rollback OK; manca **link beta** + checksum (Release center). |
| Beta feedback | 🔴 | inesistente: nessun canale per raccogliere segnalazioni dai tester. |

## FLUTTER
| Area | Stato |
|---|---|
| build config / dart-define / asset injection | 🟢 |
| Android package / iOS bundle / app name / icons / splash (parametrici) | 🟢 |
| nessun hardcode cliente | 🟢 (verificato) |

## CI/CD
| Area | Stato | Nota |
|---|---|---|
| GitHub Actions | 🟢 | `ci.yml` (test) + `app-factory-build`/`-batch` + step beta Firebase. |
| secrets / runners | 🟡 | documentati, non eseguiti (richiedono account). |
| cache / artifacts | 🟢 | `flutter-action` cache; artifact via upload-artifact/Firebase. |

## Piano (solo i gap — additivo, niente booking/auth/payment)
1. **FASE 3 — Artifact**: `app_builds.checksum` + convenzione `builds/{tenant}/{version}/` + disco artifact (config).
2. **FASE 1 — Driver locale reale**: `BuildDispatchResult` (DTO: reference, completed, artifactPath, checksum) + `LocalBuildDispatcher` (`flutter build apk --release` parametrico → copia artifact + checksum). `RunAppBuildJob`: se `completed` → stato `built` + checksum + `finished_at`.
3. **FASE 4 — Download beta privato**: route firmata `/beta/download/{build}` (scadenza + integrità + solo build `built`), `BetaDownloadController`; Control Room genera il link (audit).
4. **FASE 5 — Release center**: scheda app con versione/stato/artifact/checksum/**link beta**/errori.
5. **FASE 6 — Beta feedback**: `BetaFeedback` (tenant/utente/versione/messaggio/timestamp) + endpoint app autenticato + lista in Control Room.
6. **FASE 2 — Environments**: `.env.example` (artifact/driver) + `ENVIRONMENT_GUIDE.md`.
7. **FASE 9 — Test**: `DownloadTokenTest`, `ArtifactSecurityTest`, `TenantIsolationBuildTest`, `ReleaseLifecycleTest`, `BetaFeedbackTest`.
8. **FASE 10 — Docs**: `BETA_TESTING_GUIDE.md`, `BETA_READY_REPORT.md`.

## Fuori scope (infra esterna — reale, non eseguibile qui)
Compilazione APK/IPA (Android SDK / Mac+Xcode), Firebase, account store. Per ognuno: codice/driver reale + config + documentazione; **nessun mock** del prodotto.
