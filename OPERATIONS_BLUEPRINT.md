# OPERATIONS_BLUEPRINT — Cosa richiede il terminale oggi, e cosa no

> Nessun contenuto duplicato: per il "come si fa" di ogni operazione già disponibile da Control Room, vedi [`OWNER_GUIDE.md`](OWNER_GUIDE.md); per i comandi da terminale, [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md). Questo documento risponde a una domanda diversa: **chi/quando/quanto/automatizzabile**, operazione per operazione.

## Fase 1 — Classificazione: Control Room / Terminale / Automatizzabile

| Operazione | Oggi | Può passare a Control Room? | Deve restare terminale? | Automatizzabile del tutto? |
|---|---|---|---|---|
| Avviare il backend (`artisan serve`, o servizio systemd in produzione) | Terminale/script (`START_CONTROL_CENTER.command`) | No — è il processo che ospita la Control Room stessa | ✅ Sempre | ✅ Già automatizzato in produzione (systemd, da `platform-infra/server/user-data.sh`) |
| Worker di coda (`queue:work`) | Terminale/systemd | Il **monitoraggio** sì (stato attivo/inattivo), l'avvio no | ✅ Avvio/riavvio | ✅ Già gestito da systemd in produzione |
| Scheduler (`schedule:work`) | Terminale/systemd/cron | Il monitoraggio sì, l'avvio no | ✅ Avvio | ✅ Già automatizzato (cron/systemd) |
| **Creare un cliente** (`ProvisionTenant`) | Control Room | ✅ Già disponibile | — | Parzialmente (il trigger resta una decisione umana per design) |
| **Generare pacchetto app** (`app:generate` / azione "Genera") | Control Room | ✅ Già disponibile | — | Sì, potrebbe auto-generarsi al salvataggio del brand |
| **Lanciare una build** (`app:build` / azione "Build") | Control Room | ✅ Già disponibile | — | Sì per rebuild di flotta stale (vedi `AUTOMATION_CATALOG.md`) |
| **Link/revoca beta** | Control Room | ✅ Già disponibile | — | La scadenza è già automatica (7gg) |
| **Gestione tester** | Control Room | ✅ Già disponibile | — | No, richiede giudizio umano |
| **Cambio brand/logo** | Control Room (Dashboard/Control Room) | ✅ Già disponibile | — | No |
| Migration database (`artisan migrate`) | Terminale | **No, mai** | ✅ Sempre — è una modifica di schema, troppo rischiosa per un pulsante UI | No — richiede sempre revisione umana |
| Deploy del codice (`deploy.sh`) | Terminale/SSH | No, non in questa fase | ✅ Sempre, finché non esiste un pipeline di deploy con approvazione | Parzialmente possibile (CI con gate manuale), non raccomandato come pulsante diretto |
| Bootstrap primo super-admin (`control-room:create-admin`) | Terminale | No — problema dell'uovo e della gallina (serve un admin per usare la Control Room) | ✅ Sempre, ma solo una tantum per ambiente | No |
| Generazione chiavi JWT (`jwt:generate-keys`) | Terminale | No | ✅ Sempre, solo al primo deploy di un ambiente | ✅ Già nel deploy script |
| Cache config/route/view (`artisan config:cache` ecc.) | Terminale (deploy script) | No, è un dettaglio di deploy | ✅ Sempre | ✅ Già automatizzato nel deploy |
| **Backup** (`backup-control-center.sh`) | Terminale/script | **Sì — gap reale**, oggi non c'è un pulsante "Backup ora" in Control Room | Fino a quando non esposto | ✅ Automatizzabile con cadenza fissa (vedi `AUTOMATION_CATALOG.md`) |
| Ripristino da backup | Terminale, procedura manuale | No, troppo rischioso per un pulsante | ✅ Sempre | No — un ripristino deve sempre avere conferma umana esplicita |
| **Consultare i log applicativi** | Terminale (`tail -f storage/logs/laravel.log`) | **Sì — gap reale**, oggi non c'è una vista log in Control Room (solo i log di build, che sono un caso specifico già coperto) | Fino a quando non esposto | No, la lettura resta umana anche se esposta in UI |
| **Analytics** | Non esiste raccolta dati (gap noto, `TECHNICAL_DEBT.md` #3) | N/A — prima serve collegare `AnalyticsService`, poi si potrebbe esporre | N/A | N/A |
| Composer/npm/flutter pub install | Terminale (setup/deploy) | No | ✅ Sempre | ✅ Già nel deploy script per il backend |
| Config ambiente (`.env`) | Terminale/editor file | No — tocca segreti | ✅ Sempre | No, per design (i segreti non devono avere un percorso UI) |
| Rebuild in batch (`app:build-matrix`) | Terminale/CI (SSH) | **Sì — gap reale**, oggi solo la CI lo raggiunge, non un operatore da Control Room | Fino a quando non esposto | ✅ Ottimo candidato ad automazione schedulata |

**Sintesi**: le operazioni **quotidiane** relative al ciclo di vita del cliente (creare, brandizzare, generare, buildare, distribuire beta) **sono già** in Control Room — non è un gap. I gap reali sono tre, tutti operativi non di dominio: **backup**, **consultazione log**, **rebuild in batch** — nessuno richiede di toccare il core applicativo per essere colmato (sono tutti "esponi un'azione che il codice già sa fare", non nuova logica).

## Fase 2 — Elenco operazioni quotidiane con criticità e tempo

| Operazione | Chi la esegue | Frequenza | Criticità | Tempo richiesto | Automazione possibile |
|---|---|---|---|---|---|
| Creare un nuovo cliente | Platform Admin | Per vendita (variabile, 1-10/settimana in fase di crescita) | Alta (genera fatturazione futura) | ~5 min | Parziale — form già rapido, il trigger resta umano |
| Caricare/aggiornare brand di un cliente | Platform Admin o titolare | Per cliente, occasionale | Media | ~5 min | No |
| Generare pacchetto app | Platform Admin | Ogni volta che cambia identità/brand | Bassa | ~1 min | Sì — al salvataggio del brand |
| Lanciare una build | Platform Admin | Ogni release per cliente | Alta (produce l'artefatto distribuito) | ~2 min di trigger + tempo di compilazione reale (minuti) | Sì per rebuild massivi (già esiste `BuildFleet`/`app:build-matrix`) |
| Creare/revocare link beta | Platform Admin | Per distribuzione test | Media | ~1 min | Parziale — scadenza già automatica |
| Gestire tester (invito/stato) | Platform Admin | Occasionale | Bassa | ~2 min | No |
| Consultare feedback | Platform Admin | Periodico | Bassa | ~5 min | No — richiede lettura umana |
| Backup | Platform Admin (oggi, via terminale) | Dovrebbe essere quotidiano | Alta (unica rete di sicurezza contro perdita dati) | ~2 min oggi (manuale) | **Sì, priorità alta** — vedi `AUTOMATION_CATALOG.md` |
| Verifica log/errori | Platform Admin/Developer | Quotidiano se in produzione | Alta | ~5-15 min | Parziale — la raccolta può essere automatica (Sentry), la lettura resta umana |
| Deploy di una nuova versione del backend | Developer | Per release | Alta | ~10-15 min (script già idempotente) | Parziale — CI con gate umano, non pulsante diretto |
| Rotazione/controllo scadenza segreti (keystore, JWT) | Developer | Raro, ma critico se dimenticato | Alta | Variabile | Sì — un promemoria automatico è a basso costo (vedi `AUTOMATION_CATALOG.md`) |

Queste due tabelle sono la base fattuale per `CONTROL_ROOM_ROADMAP.md` (cosa manca in UI) e `AUTOMATION_CATALOG.md` (cosa automatizzare).
