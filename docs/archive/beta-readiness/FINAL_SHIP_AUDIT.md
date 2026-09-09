# FINAL_SHIP_AUDIT (FASE 0 — obbligatorio)

> Audit sul **codice reale** prima di toccare file. Obiettivo: prima **beta installabile su un telefono**. Legenda: 🟢 funziona ora · 🟡 migliorabile · 🔴 blocca il rilascio. Implemento **solo i gap**.

## BUILD MACHINE
| Area | Stato | Nota |
|---|---|---|
| `BuildService` → queue → `RunAppBuildJob` | 🟢 | pipeline a coda (`database`), worker, timeline. |
| `LocalBuildDispatcher` | 🟡 | esegue `pub get → analyze → test → build apk --release` reali + artifact + checksum + log/exit/durata; **manca `flutter clean`** iniziale e la **dimensione APK** (size). |
| storage/artifacts/app_builds/logs | 🟢 | `builds/{tenant}/{version}/app-release.apk`; log/exit/durata su `app_builds`. **Manca `size_bytes`**. |

## ANDROID
| Area | Stato | Nota |
|---|---|---|
| AndroidManifest / applicationId / package injection | 🟢 | `-PAPP_ID`/`-PAPP_NAME`, `android:label`. |
| signingConfig / gradle / keystore | 🟢 | release firmata da ENV (`ANDROID_KEYSTORE_PATH`, `ANDROID_KEYSTORE_PASSWORD` = store password, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`); default debug se assenti. Manca solo un doc dedicato. |
| versionCode / versionName | 🟢 | da `flutter.versionCode/Name` (gestiti via `AppVersion.build_number`). |

## FLUTTER
| Area | Stato |
|---|---|
| environment config / API URL / tenant injection (`--dart-define` + `validate()`) | 🟢 |
| asset loading (logo runtime) | 🟢 |
| crash reporting (Sentry) | 🟢 |
| nessun hardcode cliente | 🟢 (verificato) |

## DELIVERY
| Area | Stato | Nota |
|---|---|---|
| APK download / token / expiry / permission / count / revoca | 🟢 | `BetaDownloadToken` (token opaco, scadenza, limite, conteggio, revoca); endpoint `/beta/download/{token}`. |
| `BetaRelease` | 🟢 (mappato) | i campi richiesti (version/tenant/artifact/created_at + expires_at/link) sono **già** dati da `app_builds`(built: version/tenant/artifact/created_at) + `BetaDownloadToken`(expires_at/revoca/count). Niente entità duplicata. |

## Piano (solo i gap — additivo, niente core/UI/design)
1. **FASE 1**: `flutter clean` come primo step del runner; salvare la **dimensione** dell'APK (`app_builds.size_bytes`).
2. **FASE 4**: mostrare in Control Room "BUILD COMPLETED" con size + version + checksum + data (già presenti tranne size).
3. **Docs**: `SIGNING_SETUP` (FASE 2), `BUILD_MACHINE_SETUP` (FASE 3), `REAL_DEVICE_TEST_GUIDE` (FASE 6), `ENVIRONMENT_FINAL` (FASE 8), `REAL_BETA_SHIP_REPORT` (FASE 10) — con gli **ultimi comandi** per la prima APK.

## Già pronto (non riscrivere)
Build pipeline + driver locale reale, signing env-based, runtime Flutter senza hardcode, BetaTester/BetaFeedback/AnalyticsService/AppVersion, Control Room console + dashboard, isolamento tenant testato. Backend 204 test verdi.

## Realtà ambiente
Android SDK assente **qui** → la compilazione gira su build-machine/CI (driver reale, fallisce esplicitamente se l'SDK manca). Lo SHIP report chiude con i comandi esatti da eseguire su quella macchina.
