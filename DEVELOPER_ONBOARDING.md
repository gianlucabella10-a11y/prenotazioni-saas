# DEVELOPER_ONBOARDING — Da zero a produttivo in 30 minuti

Percorso guidato, in ordine. Per il riferimento completo di ogni comando: [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md). Per capire *perché* il sistema è fatto così: [`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md).

## 1. Installare tutto (~10 min)

```bash
export PATH="$HOME/.local/php-toolchain/bin:$PATH"   # PHP user-space, nessun sudo richiesto
cd platform-backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Flutter è necessario solo se lavori sull'app mobile (versione 3.44.2, vedi `.github/workflows/ci.yml`) — non serve per il backend.

## 2. Capire il progetto (~5 min)

Leggi, in quest'ordine: [`README.md`](README.md) (visione + come funziona) → questo file (§3-8 sotto) → se serve andare più a fondo, [`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md). Un'unica frase da tenere a mente: **un solo backend, un solo database, un solo codice Flutter — servono N clienti senza N copie di codice.**

## 3. Avviare il backend (~2 min)

```bash
./START_CONTROL_CENTER.command      # doppio click, oppure da terminale
```
Avvia backend + queue worker + scheduler, apre `http://127.0.0.1:8000/control-room`. Credenziali demo: [`docs/Operations/PREVIEW_ACCESS_GUIDE.md`](docs/Operations/PREVIEW_ACCESS_GUIDE.md). Per fermare: `stop-control-center.sh`.

## 4. Avviare Flutter (~5 min, solo se necessario)

```bash
cd platform-mobile/apps/client_app
flutter pub get
flutter run -d chrome \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1 \
  --dart-define=TENANT_KEY=<api_key del tenant demo>
```
`TENANT_KEY` è obbligatorio — l'app **fallisce all'avvio** (per design) se assente, non al primo errore di rete. Recupera la chiave da Control Room → scheda cliente → "Tecnico".

## 5. Creare un cliente (~3 min)

Da Control Room (`http://127.0.0.1:8000/control-room`): Clienti → "+ Nuovo cliente" → compila nome/settore/email/piano/colore → Salva. In background: `ProvisionTenant` crea tenant + abbonamento + brand + sede/orari + catalogo di partenza + primo utente titolare, tutto in una transazione. Dettaglio: `BUSINESS_FLOW.md` passaggi 2-3.

## 6. Generare l'app (~2 min)

Apps → scheda del progetto appena creato → "Genera". Crea manifest + asset brandizzati (icone/splash). Nessuna build nativa avviene qui.

## 7. Fare una build (~3 min + tempo di compilazione reale)

Apps → scheda progetto → "Build" (Android). Con driver `local` (solo sviluppo) esegue davvero `flutter build apk` — richiede Android SDK/JDK installati. Con driver `manual` (default) registra solo l'intento, nessuna compilazione. **Gap noto**: la build `local` firma in debug, non release, salvo impostare manualmente le variabili keystore (`docs/Deployment/SIGNING_SETUP.md`) — non è un errore tuo, è un limite tracciato in `TECHNICAL_DEBT.md`.

## 8. Fare debug

| Cosa | Dove |
|---|---|
| Log applicativi backend | `platform-backend/storage/logs/laravel.log` |
| Log di una build fallita | Control Room → scheda build → log completo |
| Errori runtime (crash) | Sentry, solo se `SENTRY_LARAVEL_DSN`/`SENTRY_DSN` configurati — vuoto in locale per design |
| "È un bug o è già un limite noto?" | Verifica prima in [`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md)/[`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md) — risparmia tempo di indagine |

## Dopo i 30 minuti

- Convenzioni complete, workflow git, standard di naming: [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md) + [`PROJECT_STANDARD.md`](PROJECT_STANDARD.md)
- Dove vive ogni cosa: [`PROJECT_MAP.md`](PROJECT_MAP.md)
- Cosa il sistema può/non può fare prima di proporre una feature: [`SYSTEM_BOUNDARIES.md`](SYSTEM_BOUNDARIES.md)
