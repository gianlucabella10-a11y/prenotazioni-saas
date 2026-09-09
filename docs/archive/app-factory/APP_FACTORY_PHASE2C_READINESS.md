# APP_FACTORY_PHASE2C_READINESS

> Esito FASE 2C: **firma + pubblicazione store + CI** per la build per-tenant. Le parti **verificabili in questo ambiente** (tracking build lato backend, firma Android default-safe nel gradle, manifest stabile per la CI, orchestrazione script) sono fatte e testate. Le parti che richiedono **account store + segreti + runner reali** (firma con keystore/cert veri, upload Play/App Store, esecuzione CI) sono scaffoldate e documentate, ma **non eseguibili/verificabili qui**. Una sola codebase, N configurazioni: **nessun flavor, nessun fork.**
>
> Verifiche: backend `php artisan test` **139** · Pint pulito · `flutter analyze` pulito · `flutter test` **36** · `bash -n` make_app.sh OK + dry-run android/ios **senza mutazioni** · workflow YAML validato (jobs prepare/android/ios).

## 1. Cosa è stato fatto (e verificato qui)
- **Tracking della pipeline nel backend** (testabile, end-to-end):
  - `AppProjectStatus` esteso con `building` / `published` / `failed` (oltre a draft/ready/generated/ready_to_build).
  - Comando `php artisan app:build-record {uuid} {android|ios} {building|built|published|failed} [--app-version=] [--artifact=]`: callback che la CI invoca per registrare l'esito; crea una riga `app_builds` per-piattaforma e aggiorna lo stato dell'App Project. Validazione di piattaforma/stato; fallisce su tenant/App Project inesistente. Tenant-scoped via `CurrentTenant::bypass`.
  - Control Room: badge di stato (In build / Pubblicata / Fallita) in lista e dettaglio + storico build per-piattaforma con `artifact_path`.
  - 8 test (`RecordAppBuildCommandTest`) verdi.
- **Firma Android default-safe** (`android/app/build.gradle.kts`): `signingConfig` `release` letto dalle env `ANDROID_KEYSTORE_*`; **senza env si firma in debug** → la build locale/standard non cambia. Nessun segreto nel repo.
- **Manifest stabile per la CI** (`GenerateAppPackage`): oltre al versionato `manifest-vN.json` scrive `manifest-latest.json` (puntatore all'ultima versione) che il workflow recupera.
- **Orchestratore** `tool/app_factory/make_app.sh`: `--platform android|ios`; in dry-run stampa il comando di build per la piattaforma scelta (Android AAB / iOS IPA) **senza modificare file**; con `--build` prepara identità + asset e compila.
- **Workflow CI** `.github/workflows/app-factory-build.yml` (manuale, `workflow_dispatch`): job `prepare` (genera/recupera manifest + asset dal backend via SSH) → `android` (AAB firmato → Google Play, track interno) → `ios` (IPA → TestFlight) → callback `app:build-record`. Parametrico per `tenant_uuid` (matrix-ready), **non parte da solo**.
- **Segreti documentati** in `APP_FACTORY_RELEASE_SECRETS.md` (nessuno nel repo).

## 2. Cosa richiede account/segreti/runner reali (non eseguibile qui)
> Lo scaffold è completo, ma serve infrastruttura esterna per una pubblicazione vera.
- **Firma Android reale**: keystore `.jks` della piattaforma + service account Google Play (ruolo Release manager). Una sola chiave per tutte le app per-tenant.
- **Firma + upload iOS**: **account Apple Developer del cliente** (linee guida 4.2.6/4.3) + API key App Store Connect + cert/provisioning profile sul runner macOS. Non automatizzabile sotto un unico account.
- **Esecuzione CI**: i job non sono mai stati lanciati (richiedono i segreti e un runner). Il workflow è validato come YAML, non come run.
- **Build native**: `flutter build appbundle`/`ipa` non compilabili in questo ambiente (no Android SDK/Xcode build) — confermare su Mac/CI.

## 3. Rischi tecnici e mitigazioni
- **Workflow non eseguito**: YAML valido (jobs prepare/android/ios), ma il primo run reale può rivelare aggiustamenti (nome IPA, path artifact, versioni delle action). Mitigazione: prima esecuzione su un tenant pilota in track `internal`.
- **`manifest-latest.json`**: introdotto come puntatore stabile; il versionato resta la fonte storica. Coerente con `app_builds.artifact_path` (versionato).
- **Policy store "app ripetitive"** (Play): mitigare con store listing reali (descrizione/screenshot dal catalogo del cliente).
- **Firma**: chiavi/cert **solo** in GitHub Secrets/vault, mai nel repo; il gradle legge da env e ripiega su debug se assenti.
- **Scala 100+**: la build per-tenant va su CI matrix (un run per tenant); i runner macOS per iOS restano la risorsa scarsa.

## 4. Flusso end-to-end (FASE 2C)
1. Control Room: crea cliente + logo → `php artisan app:generate {uuid}` (Asset Factory + manifest 2.0 + `manifest-latest.json`, stato `ready_to_build`).
2. GitHub → Actions → **app-factory-build** → *Run workflow* (`tenant_uuid`, `platform`, `android_track`).
3. CI: `prepare` (manifest + asset dal backend) → build firmata (`make_app.sh --build`) → upload allo store → `app:build-record` aggiorna lo stato (`published`/`failed`).
4. Control Room: lo stato dell'App Project e lo storico build riflettono l'esito.

## 5. Definition of Done — verifica
backend `php artisan test` **139/139** · Pint pulito · `flutter analyze` pulito · `flutter test` **36** · `bash -n` + dry-run `make_app.sh` (android/ios) senza mutazioni · workflow YAML validato. Riferimenti: `APP_FACTORY_RELEASE_SECRETS.md`, `APP_FACTORY_PHASE2B_READINESS.md`, `APP_FACTORY_MASTER_PLAN.md` (§8 pubblicazione store).

## 6. Cosa resta dopo la 2C (FASE 3 — scala)
CI matrix multi-tenant dal registry · treno di rilascio batched del core + canary · osservabilità build · pool runner macOS per iOS · `app_templates` su DB con editor.
