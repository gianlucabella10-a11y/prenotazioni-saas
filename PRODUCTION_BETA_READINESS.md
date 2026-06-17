# PRODUCTION_BETA_READINESS

> Da "architettura pronta" a **BETA reale utilizzabile con clienti veri**. Esito del Production Completion (audit `PRODUCTION_GAP_ANALYSIS.md` + chiusura gap). Verifiche: backend `php artisan test` **183/183** (689 asserzioni) · `flutter analyze` pulito · `flutter test` **36** · Pint pulito · booking/auth/payments/scheduling/notification core **non toccati**.

## 1. Completato
- **Pipeline di build a coda** (FASE 1): `BuildService` (valida → `app_builds(queued)` → enqueue) + `RunAppBuildJob` (worker: `building` → driver → artifact; su errore `failed`). Stato `queued` aggiunto. Coda Laravel `database`. *Sostituisce* il dispatch sincrono (niente doppione).
- **Timeline build** (FASE 6): `queued_at`/`started_at`/`finished_at` + `error_message`; `app:build-record` completa la build in volo (una sola riga per tentativo). Logging strutturato (`app_factory.build.*`).
- **Build parametrica** (FASE 2): app name/icon/splash/package/bundle/dart-define dal `AppProject` + manifest, zero hardcoding (gradle property + xcconfig + `make_app.sh`).
- **CI** (FASE 3): `.github/workflows/ci.yml` (Pint + `php artisan test` + `flutter analyze`/`test`) su `main`/`staging`. Build/distribuzione in workflow dedicati.
- **Beta distribution** (FASE 4): step Firebase App Distribution nel workflow Android (gated da secret) + `BETA_RELEASE.md`; iOS via TestFlight.
- **Environments** (FASE 5): `.env.example` con chiavi App Factory/driver/Firebase per LOCAL/STAGING/PROD.
- **Control Room premium** (FASE 7): dashboard Flotta con build riuscite/fallite/in corso; scheda app con timeline, storico errori, rollback, anteprima (logo/icona/splash/feature).
- **Test** (FASE 8): `BuildServiceTest`, `RunAppBuildJobTest`, `AppFactoryOperationsTest` (HTTP), oltre a transition/rollback/identity/isolation già presenti.
- **Docs** (FASE 9): `PRODUCTION_READY_RUNBOOK.md` (nuova app in 5 minuti), `BETA_RELEASE.md`, questo file.

## 2. Mancante (per uso quotidiano pieno)
- **Esecuzione reale della CI**: i workflow sono validati come YAML ma mai eseguiti (richiedono runner + segreti). Primo collaudo su un tenant pilota.
- **Compilazione nativa**: AAB/IPA si producono su Mac/CI (non in questo ambiente).
- **iOS beta**: richiede l'account Apple Developer del cliente.
- **Worker in esecuzione**: in produzione serve un `queue:work` attivo (supervisor/Horizon) per processare le build accodate.

## 3. Rischi
- **Pipeline non collaudata end-to-end** su runner: mitigato da timeline + `error_message` + driver `manual` sicuro; resta un primo run reale.
- **Policy store "app ripetitive"** (Play): mitigare con store listing reali.
- **Risorsa macOS** per iOS a scala: usare `max-parallel` basso / runner dedicati.
- **Segreti**: solo in GitHub Secrets/ENV, mai nel repo (verificato).

## 4. Prossimi passi
1. Configurare i segreti CI + Firebase su un tenant pilota; eseguire `app-factory-build` (track `internal`/beta).
2. Avviare un worker `php artisan queue:work` (o Horizon) in staging.
3. Validare l'AAB su device reale (applicationId/nome/icone) e il giro `app:build-record`.
4. Onboarding account Apple del cliente per il primo iOS.

## 5. Review finale (FASE 10)
- **Il prodotto può essere usato domani da un esercente?** *Sì, in beta Android*, una volta configurati i segreti CI + Firebase (setup una tantum): l'addetto crea l'app dalla Control Room in ~5 minuti e i tester la installano via Firebase. iOS richiede prima l'account Apple del cliente.
- **Cosa manca realmente?** Solo l'**attivazione dell'infrastruttura esterna** (runner CI, Firebase, account store) e un **worker di coda attivo**: codice e pipeline ci sono e sono testati.
- **Cosa richiede account esterni?** Compilazione (CI/Mac), distribuzione (Firebase/TestFlight), store (Play/Apple, con account del cliente per iOS).
- **Cosa è completamente automatizzato?** Creazione cliente, identità immutabile, generazione asset versionati, manifest + pacchetto self-contained, accodamento build + worker + tracking + timeline + errori, rollback, dashboard, build parametrica, CI di test. Tutto a **1 codice / 1 backend / N clienti**.

— Stato: **BETA reale utilizzabile** (Android pieno con setup infra; iOS con account cliente). Riferimenti: `PRODUCTION_GAP_ANALYSIS.md`, `PRODUCTION_READY_RUNBOOK.md`, `BETA_RELEASE.md`, `WHITE_LABEL_PRODUCTION_READY.md`, `APP_FACTORY_RELEASE_SECRETS.md`.
