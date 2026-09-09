# DEVELOPER_GUIDE

Guida di riferimento per chi sviluppa su questo repository. Per lo stato reale del codice (cosa esiste, cosa manca), non questo documento ma [`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md).

## 1. Setup / installazione

**Prerequisiti verificati in questo ambiente**: PHP 8.3+ in user-space (`~/.local/php-toolchain/bin`, nessun sudo/brew richiesto), SQLite (default locale), Flutter **non richiesto** per lavorare solo sul backend.

```bash
export PATH="$HOME/.local/php-toolchain/bin:$PATH"
cd platform-backend
composer install          # vendor/ non è committato
cp .env.example .env      # DB_CONNECTION=sqlite di default
php artisan key:generate
php artisan migrate
```

Avvio one-click (backend + queue worker + scheduler + Control Room): doppio click su [`START_CONTROL_CENTER.command`](START_CONTROL_CENTER.command), oppure manualmente `php artisan serve`. Dettaglio credenziali demo e URL: [`docs/Operations/PREVIEW_ACCESS_GUIDE.md`](docs/Operations/PREVIEW_ACCESS_GUIDE.md).

**Frontend build (Vite/Tailwind)**: non ancora installato in questo checkout (`node_modules/`, `public/build/` assenti) — se necessario: `npm install && npm run build` dentro `platform-backend/`.

**Flutter**: non installato in questo ambiente. Per lavorare sull'app cliente serve Flutter 3.44.2 (versione usata in CI, `.github/workflows/ci.yml`) + Android SDK/JDK 17 per build reali (`platform-mobile/apps/client_app/`).

## 2. Build

- **Backend**: nessuna build — Laravel gira direttamente da sorgente (`php artisan serve`).
- **Frontend web (dashboard)**: `npm run dev` (Vite, hot reload) o `npm run build` (produzione).
- **App mobile**: `flutter build apk --release -PAPP_ID=... -PAPP_NAME=... --dart-define=...` — normalmente **non lanciato manualmente**, ma orchestrato dal Build Engine (Control Room → `BuildService` → `LocalBuildDispatcher`/`GithubBuildDispatcher`). Dettaglio pipeline: `PROJECT_FREEZE_STATE.md` §8.

## 3. Debug

- **Log backend**: `platform-backend/storage/logs/laravel.log` (+ `worker.log`, `scheduler.log` se avviato con lo script one-click).
- **Log build APK**: salvato per intero in `app_builds.build_log` (DB), consultabile da Control Room → scheda app → build.
- **Posta di test**: se configurato Mailpit/Mailhog, `http://127.0.0.1:8025` (vedi `PREVIEW_ACCESS_GUIDE.md`).
- **Crash reporting**: Sentry, condizionale a `SENTRY_LARAVEL_DSN` (backend) / `SENTRY_DSN` dart-define (Flutter) — vuoto in sviluppo per design, nessun dato lascia la macchina.

## 4. Test

```bash
# Backend
cd platform-backend && php artisan test          # 45 file (Unit/ + Feature/)
./vendor/bin/pint --test                          # lint

# Flutter
cd platform-mobile/apps/client_app
flutter analyze
flutter test                                       # 13 file (unit/widget/e2e)
```

Standard obbligatori per nuovi test: vedi [`PROJECT_STANDARD.md`](PROJECT_STANDARD.md) §5 — in particolare, **ogni nuovo modello con `tenant_id` richiede un test di isolamento cross-tenant** (pattern: `tests/Feature/TenantIsolationTest.php`), e **ogni nuova schermata Flutter richiede almeno un test in `test/widget/`**.

## 5. Convenzioni / coding style / naming

Regole vincolanti complete: [`PROJECT_STANDARD.md`](PROJECT_STANDARD.md). Sintesi:
- PHP: `PascalCase` classi, namespace `App\Modules\<Modulo>\<Layer>\...` o `App\Foundation\<Area>\...`, lint via `laravel/pint` (`./vendor/bin/pint`).
- Flutter: `snake_case.dart`, suffisso per ruolo (`_screen`, `_repository`, `_controller`, `_service`), lint via `flutter_lints` (`flutter analyze`).
- Route API: inglese, kebab-case. Route Dashboard/Control Room: italiano (interfacce operatore).
- Documentazione: una fonte di verità per argomento — aggiornare il documento esistente, non crearne uno nuovo (vedi lo standard di documentazione).

## 6. Workflow Git / branch

- Branch osservato in questo repository al momento dell'audit: `docs/production-readiness-audit` (branch di lavoro), `main` come branch principale.
- Nessuno schema di branching formale documentato nel codice — segue convenzione trunk-based implicita (branch di lavoro brevi, merge su `main`).
- `.gitignore` correttamente esclude: `.env`, `vendor/`, `node_modules/`, `build/`/`.dart_tool/` Flutter, sqlite di sviluppo, log, segreti/chiavi/keystore — verificato, nessun segreto risulta tracciato.

## 7. Release / deploy

- **Backend**: `platform-infra/bin/deploy.sh` (rsync + SSH verso l'istanza, `composer install --no-dev`, `migrate --force`, cache config/route/view, restart worker). Idempotente, ripetibile.
- **Mobile**: via CI (`app-factory-build.yml`/`app-factory-batch.yml`, driver `github`) o Control Room (driver `local`, solo sviluppo — vedi gap noto sulla firma in `PROJECT_FREEZE_STATE.md` §8).
- **Ambienti**: LOCAL / STAGING (pianificato, non ancora esistente) / BETA / PRODUCTION — dettaglio in [`docs/Deployment/ENVIRONMENT_GUIDE.md`](docs/Deployment/ENVIRONMENT_GUIDE.md).
- Prima di ogni deploy in produzione: leggere [`docs/Deployment/RELEASE_PROCESS.md`](docs/Deployment/RELEASE_PROCESS.md).

## Dove andare dopo

- Struttura completa del repository: [`PROJECT_MAP.md`](PROJECT_MAP.md)
- Dipendenze tra moduli: [`DEPENDENCY_GRAPH.md`](DEPENDENCY_GRAPH.md)
- Stato verificato del codice: [`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md) / [`PROJECT_FREEZE_STATE.md`](PROJECT_FREEZE_STATE.md)
- Indice assoluto: [`PROJECT_INDEX.md`](PROJECT_INDEX.md)
