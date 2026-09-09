# FINAL_REAL_BETA_AUDIT (FASE 0 — obbligatorio)

> Audit sul **codice reale** prima di toccare file. Obiettivo: *Control Room → genero app → APK reale → installo su Android → consegno al cliente → vedo errori/crash/feedback*, senza interventi tecnici per cliente. Legenda: 🟢 pronto · 🟡 migliorabile · 🔴 bloccante. Implemento **solo i gap**.

## Catena di build (FASE 1) — 🟢
`Control Room → AppProject → GenerateAppPackage → BuildService → queue → RunAppBuildJob → BuildDispatcher → flutter build`. Verificato: `LocalBuildDispatcher` esegue **davvero** `flutter pub get → analyze → test → build apk --release` (parametrico), salva `builds/{tenant}/{version}/app-release.apk` + checksum + `command`/`build_log`/`exit_code`/`duration` (anche su fallimento). Senza Android SDK fallisce **esplicitamente** col log reale. **Già completo.**

## Stato per pilastro
| Area | Stato | Nota |
|---|---|---|
| Build runner production-ready (FASE 1) | 🟢 | pipeline reale + osservabilità + storage/artifact/app_builds/logs. |
| Android signing (FASE 2) | 🟢 | `build.gradle.kts` firma release da ENV (`ANDROID_KEYSTORE_*`), default debug; package injection `-PAPP_ID`. Firma **di piattaforma** (no per-cliente). |
| Flutter runtime (FASE 3) | 🟢 | nessun hardcode cliente (verificato); tutto da `--dart-define` + `/app/config`; `validate()` fail-fast. |
| **Version management (FASE 4)** | 🔴 | **manca `AppVersion`** (version/build_number/release_notes/status, active/deprecated, prep forced-update). |
| Artifact delivery (FASE 5) | 🟡 | download via URL **firmato** (token+scadenza+checksum, non esposto). **Mancano `download_count` e `revoca`** → serve un token persistente. |
| Beta testing (FASE 6) | 🟢 | `BetaTester` (invited/active/blocked + device). *Versione installata* desumibile dal feedback (app_version). |
| Monitoring (FASE 7) | 🟡 | crash via **Sentry** (integrato); `AnalyticsService` presente ma **mancano** gli eventi `booking_failed` e `api_error`. |
| Control Room console (FASE 10) | 🟢 | scheda app (status/version/build/artifact/download/tester/feedback/log) + dashboard (totali/ok/fallite/beta attive). |

## Piano (solo i gap — additivo, niente core/UI/design)
1. **FASE 4 — AppVersion**: model + migrazione (per App Project: version, build_number, release_notes, status active|deprecated) + gestione in Control Room + esposizione "release" in `/app/config` (prep forced-update; il client per ora la ignora).
2. **FASE 5 — Download token revocabile**: `BetaDownloadToken` (token, scadenza, `max_downloads`, `download_count`, `revoked_at`) → sostituisce l'URL firmato stateless; endpoint pubblico per-token; Control Room genera/elenca/**revoca** con conteggio download.
3. **FASE 7 — Analytics**: aggiungere eventi `booking_failed`, `api_error`. Documentare il contesto Sentry (tenant/version/device/OS).
4. **FASE 8/9/12 — Docs**: `APP_PROVISIONING_RUNBOOK`, `BETA_DEBUG_RUNBOOK`, `RELEASE_PROCESS`, `REAL_BETA_ENVIRONMENT`, `FINAL_REAL_BETA_READY`.

## Fuori scope (infra esterna, reale, nessun mock)
Android SDK/compilazione (build-machine/CI), worker `queue:work` attivo, Firebase (Crashlytics/Analytics/App Distribution), account store/Apple. Driver/codice reali + config + doc.
