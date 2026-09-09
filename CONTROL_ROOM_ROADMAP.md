# CONTROL_ROOM_ROADMAP — Funzionalità operative di una Control Room enterprise

> Solo elenco e classificazione — nessuna implementazione in questa fase. Stato "ESISTE" verificato in `REAL_PROJECT_STATE.md` §Fase 6 / `PROJECT_FREEZE_STATE.md` §5, non riverificato qui da zero.

**Legenda priorità**: 🔵 ESSENZIALI (senza, la piattaforma non è gestibile in autonomia oggi) · 🟣 UTILI (migliorano efficienza operativa, non bloccanti) · ⚪ FUTURE (rilevanti solo a scala maggiore o con un modello di business più maturo).

| Funzionalità | Priorità | Stato oggi |
|---|---|---|
| **Gestione clienti** (crea/vedi/cerca/filtra) | 🔵 | ✅ ESISTE |
| **Attivazione/sospensione/riattivazione cliente** | 🔵 | ✅ ESISTE |
| **Terminazione/archiviazione cliente** | 🔵 | ✅ ESISTE *(implementata in ZERO_MANUAL_OPERATIONS_AUDIT.md — sessione "ZERO MANUAL OPERATIONS")* |
| **Gestione invito titolare** (rigenera/revoca) | 🔵 | ✅ ESISTE |
| **Brand/logo/colori** (quick setup) | 🔵 | ✅ ESISTE |
| **Generazione pacchetto app** | 🔵 | ✅ ESISTE |
| **Build** (trigger, stato, log) | 🔵 | ✅ ESISTE (driver `local`/`manual`/`github`) |
| **Build queue** (vista globale di tutte le build in corso/in coda su tutti i clienti) | 🟣 | 🟡 PARZIALE *(corretto in FLEET_CURRENT_STATE.md)* — `/control-room/apps/flotta` mostra già conteggi aggregati (riuscite/fallite/in corso) e le ultime 15 build cross-tenant; manca filtro/ricerca/paginazione oltre le 15 più recenti |
| **Versioni app** | 🟣 | ✅ ESISTE ma gestito manualmente, scollegato dalla build reale (`TECHNICAL_DEBT.md` #2) |
| **Rollback build/APK** ("torna alla versione precedente pubblicata") | 🟣 | 🔴 NON ESISTE — esiste solo rollback degli **asset brand**, non della build/APK pubblicata |
| **APK** (download, checksum, dimensione) | 🔵 | ✅ ESISTE |
| **Beta: link, scadenza, revoca, limite download** | 🔵 | ✅ ESISTE |
| **Tester** (invita/attiva/blocca) | 🔵 | ✅ ESISTE |
| **Feedback** (visualizzazione) | 🟣 | ✅ ESISTE, sola lettura |
| **Feedback** (gestione/risposta/moderazione) | ⚪ | 🔴 NON ESISTE |
| **Cronologia/audit log** (vista in Control Room di ciò che è già scritto in `audit_logs`) | 🔵 | ✅ ESISTE *(implementata in ZERO_MANUAL_OPERATIONS_AUDIT.md — `/control-room/audit`, paginata e filtrabile)* |
| **Analytics** (utilizzo app, funnel prenotazioni) | 🟣 | 🔴 NON ESISTE — l'infrastruttura lato app non è nemmeno collegata (`TECHNICAL_DEBT.md` #3) |
| **Crash reporting** (vista errori in Control Room, oggi solo su Sentry esterno) | 🟣 | 🔴 NON ESISTE in Control Room (Sentry esiste ma è un dashboard separato) |
| **Gestione utenti piattaforma** (super-admin multipli, ruoli) | 🟣 | 🟡 PARZIALE — `control-room:create-admin` crea un super-admin da terminale, nessuna UI per gestirne altri o revocarli |
| **Permessi granulari** (oltre i 4 ruoli fissi) | ⚪ | 🔴 NON ESISTE (nessun layer `Policies`, solo guard/middleware — vedi `PROJECT_FREEZE_STATE.md`) |
| **Backup** (trigger/stato da UI) | 🔵 | ✅ ESISTE *(implementata in ZERO_MANUAL_OPERATIONS_AUDIT.md — `/control-room/backup`, stessa logica del comando `platform:backup`)* |
| **Storage** (spazio disco usato, pulizia artefatti vecchi) | 🟣 | 🔴 NON ESISTE |
| **Build machine / stato toolchain** (Flutter/Android SDK disponibili e aggiornati) | 🟣 | 🔴 NON ESISTE — oggi si scopre un problema di toolchain solo quando una build fallisce |
| **Licenze** (chiave/piano per tenant, oltre l'`api_key` tecnica) | ⚪ | 🟡 PARZIALE — esiste `Subscription`/`Plan`, non un concetto di licenza separato |
| **Fatturazione** | ⚪ | 🔴 NON ESISTE (nessuna integrazione di pagamento nel codice) |
| **Monitoraggio applicativo** (uptime, tempo di risposta, errori recenti) | 🟣 | 🔴 NON ESISTE in Control Room |
| **Stato server/infrastruttura** (CPU, disco, coda job in ritardo) | ⚪ | 🔴 NON ESISTE |
| **Notifiche/allarmi verso il proprietario** (build fallita, disco pieno, backup non eseguito) | 🟣 | 🔴 NON ESISTE |

## Sintesi per priorità

**🔵 ESSENZIALI: 0 mancanti** *(erano 3: terminazione/archiviazione cliente da UI, vista audit log, backup da Control Room — tutte e tre implementate nella sessione "ZERO MANUAL OPERATIONS", vedi `ZERO_MANUAL_OPERATIONS_AUDIT.md` e `CONTROL_ROOM_AUTOMATION_PLAN.md`)*. Erano le tre lacune che davvero impedivano di "non aprire mai il terminale" — tutto il flusso quotidiano cliente→app→build→beta→backup→audit è ora coperto da Control Room.

**🟣 UTILI mancanti (7)**: build queue aggregata, rollback build/APK, gestione feedback, gestione utenti piattaforma, storage, stato build machine, monitoraggio applicativo, notifiche/allarmi — migliorano l'efficienza ma non bloccano l'operatività di base.

**⚪ FUTURE (4)**: permessi granulari, licenze come concetto separato, fatturazione, stato infrastruttura — rilevanti solo con una crescita del team/base clienti che oggi non è ancora richiesta.
