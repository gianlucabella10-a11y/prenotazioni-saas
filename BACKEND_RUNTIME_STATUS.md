# BACKEND_RUNTIME_STATUS — Diagnosi runtime del 12/06/2026

Verifica completa a seguito della notifica *"Background shell non riuscito — Start Laravel dev server on port 8000"*.

## A) Stato attuale backend

**SANO e PIENAMENTE OPERATIVO.** Tutte le verifiche eseguite, nessuna esclusa:

| Verifica | Esito |
|---|---|
| PHP disponibile | ✅ 8.4.22 (toolchain user-space `~/.local/php-toolchain`) |
| Composer disponibile | ✅ 2.10.1 |
| Dipendenze installate | ✅ `vendor/` integro |
| `.env` configurato | ✅ presente, `APP_KEY` valorizzata, chiavi JWT in `storage/keys/` |
| Database raggiungibile | ✅ `database/database.sqlite` (499 KB), dati intatti: tenant "Salone Verdi" attivo, 1 staff, 3 servizi, storico appuntamenti preservato |
| Migrazioni | ✅ 8/8 `Ran`, nessuna pendente |
| Suite test backend | ✅ **64/64 passed (216 assertion)** |
| Porta 8000 | ✅ libera prima del riavvio, nessun processo zombie, nessun conflitto |
| Endpoint live | ✅ `/up` 200 · `/app/config` 200 · `/catalog/services` 200 (3 servizi) · register → token · `/availability` 38 slot · `/appointments` 200 |
| **Comunicazione Flutter→Laravel** | ✅ E2E completo (config→registrazione→catalogo→slot→prenotazione→storico→cancellazione) eseguito **5 volte consecutive: 5/5 verdi** |

## B) Problema trovato

1. **La notifica originale NON era un errore**: exit code 144 = processo terminato da segnale — è il `pkill -f "artisan serve"` eseguito intenzionalmente a fine sessione per pulizia. `artisan serve` è un processo foreground: terminarlo produce sempre un exit code "failed" nella shell in background. **Nessun danno: zero stato corrotto.**
2. La diagnosi ha però scovato **due problemi latenti reali**, emersi rieseguendo l'E2E:
   - **Test E2E non ripetibile**: usava la chiave di idempotenza fissa `e2e-key-1`, persistita nel DB dalla prima esecuzione → il backend la rigiocava *correttamente* (docs/25 §6) restituendo il vecchio appuntamento cancellato → assertion fallita. Bug del test, comportamento backend corretto.
   - **`SQLSTATE: database is locked` intermittente (500)**: in dev `CACHE_STORE=database` faceva scrivere i contatori della cache disponibilità sulla **stessa SQLite** delle transazioni di booking; SQLite è single-writer → contesa intermittente. Limite solo dell'ambiente dev: in produzione cache=Redis e DB=MySQL (docs/21).

## C) Causa

| Sintomo | Causa |
|---|---|
| Notifica "Background shell non riuscito" | Terminazione intenzionale via `pkill` (SIGTERM) a fine sessione precedente |
| E2E fallito al retry | Chiave idempotenza hardcoded nel test vs. DB persistente |
| 500 intermittente su doppia prenotazione | Driver cache `database` in contesa di scrittura con le transazioni sulla stessa SQLite |

## D) Correzione applicata (minima, nessun codice applicativo toccato)

1. `test/e2e/booking_e2e_test.dart`: chiavi di idempotenza **univoche per esecuzione** (`e2e-<timestamp>-N`) — correzione del test, con commento che spiega il perché.
2. `.env` (solo dev): `CACHE_STORE=database` → `CACHE_STORE=file` — la cache non contende più il file SQLite. Nessuna modifica a codice, migrazioni o configurazioni di produzione.

Verifica post-correzione: **5/5 esecuzioni E2E consecutive verdi**, log Laravel privo di nuovi errori.

## E) Comandi corretti per riavvio futuro (senza sudo/Homebrew/installazioni globali)

```bash
export PATH="$HOME/.local/php-toolchain/bin:$HOME/.local/flutter/bin:$PATH"
cd "platform-backend"

# avvio in foreground (terminale dedicato):
php artisan serve --host=127.0.0.1 --port=8000

# oppure persistente in background con log:
nohup php artisan serve --host=127.0.0.1 --port=8000 > storage/logs/serve.log 2>&1 &

# stop pulito:
pkill -f "artisan serve"

# verifica rapida:
curl -s http://127.0.0.1:8000/up                      # → 200
lsof -nP -iTCP:8000 -sTCP:LISTEN                      # chi occupa la porta
```

Nota: l'exit code "144/143" alla terminazione di `artisan serve` in background è **normale** (processo ucciso da segnale), non un errore.

## F) Impatto sull'app Flutter

- **Nessun impatto sul codice dell'app**: il problema cache era server-side dev-only; l'app gestiva comunque l'errore (mappato in `ApiFailure`) senza crash.
- **URL base per ambiente** (`--dart-define=API_BASE_URL`):
  - **Simulatore iOS / test su questa macchina**: `http://127.0.0.1:8000/api/v1` (default dev) ✅ verificato
  - **Emulatore Android**: usare `http://10.0.2.2:8000/api/v1` (alias dell'host)
  - **Device fisico**: `http://<IP-LAN-del-Mac>:8000/api/v1` con device sulla stessa rete; su iOS il traffico `http` richiede eccezione ATS **solo in build di sviluppo** — staging/produzione devono essere `https` (nessuna eccezione da spedire negli store)
  - **Staging/produzione**: URL https iniettati dalla pipeline per ambiente; nessun valore hardcoded nell'app
- Prova finale richiesta eseguita: **Flutter app → API Laravel → risposta corretta**, sull'intero flusso di prenotazione, 5 volte di fila.
