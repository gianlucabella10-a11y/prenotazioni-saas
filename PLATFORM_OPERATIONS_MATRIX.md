# PLATFORM_OPERATIONS_MATRIX — Ogni processo operativo, classificato

> Automatizzato / Semi automatico / Manuale / Critico. "Critico" = un errore qui ha impatto ampio o irreversibile, indipendentemente dal livello di automazione. Stato aggiornato con l'automazione del backup implementata in questa sessione.

| Processo | Classificazione | Note |
|---|---|---|
| **Creazione cliente** | Semi automatico | 1 form, `ProvisionTenant` fa tutto il resto in una transazione — il trigger resta e deve restare umano |
| **Configurazione** (orari, servizi, staff) | Manuale | Fatto dal titolare stesso dalla propria Dashboard — corretto che sia manuale, è la sua attività |
| **Logo** | Manuale | Upload umano, per natura |
| **Assets** (icone/splash/store) | Automatizzato | Generati da `GenerateBrandAssets` (GD) al caricamento del logo, zero intervento umano |
| **Generazione app** (manifest) | Semi automatico | 1 click ("Genera"), logica interamente automatica dietro |
| **Build** | Semi automatico | 1 click ("Build"), compilazione reale automatica; **critico**: nessun fail-fast se manca il toolchain, l'errore emerge solo a build in corso |
| **Firma** | 🔴 Manuale/rischiosa | Ricade su firma debug se le variabili keystore non sono impostate (`TECHNICAL_DEBT.md` #1) — **critico**, non risolto in questa sessione (richiede codice del Build Engine, fuori scope "solo operatività") |
| **APK** (artefatto) | Automatizzato | Checksum, dimensione, path calcolati automaticamente |
| **Distribuzione** (beta) | Semi automatico | 1 click per generare il link; scadenza/limite download già automatici |
| **Aggiornamenti** | 🔴 Manuale | Nessun forced-update — ogni versione successiva ripete l'intero ciclo manuale (`PLATFORM_LIFECYCLE.md` fase 9) |
| **Feedback** | Manuale (lettura) | Raccolto automaticamente dall'app, ma letto e non gestito da un umano |
| **Crash** | Semi automatico | Raccolto da Sentry se configurato (automatico), ma nessuna vista in Control Room — richiede dashboard esterno |
| **Analytics** | 🔴 Non esistente | `AnalyticsService` mai collegato — non classificabile perché non produce dati (`TECHNICAL_DEBT.md` #3) |
| **Backup** | ✅ **Automatizzato** *(in questa sessione)* | Schedulato giornaliero (`routes/console.php`), con retention automatica (ultimi 14) — prima era manuale (pulsante), ancora prima da terminale |
| **Storage** | Manuale (monitoraggio) | Spazio disco visibile nel cruscotto, ma nessuna pulizia automatica di artefatti vecchi |
| **Queue** | Semi automatico | Worker gira come processo di sistema (automatico); stato visibile nel cruscotto (pending/failed), ma nessun riavvio automatico se si ferma — **critico** se si blocca senza che nessuno lo noti (mitigato dall'alert nel cruscotto) |
| **Monitoring** | Semi automatico | Cruscotto calcola automaticamente salute (coda/disco/backup/build), ma la lettura e l'azione restano umane |
| **Supporto** | Manuale | Nessun sistema di ticketing — comunicazione fuori piattaforma |
| **Eliminazione cliente** | Semi automatico *(sessione precedente)* | 1 click ("Archivia"), transizione di stato validata e audit-loggata — **critico**: irreversibile, protetto solo da una conferma JS, nessun secondo livello di conferma |
| **Ripristino** (da backup) | 🔴 Manuale, mai testato end-to-end | Procedura scritta (`docs/Operations/BACKUP_RECOVERY_GUIDE.md`), ma nessun test automatico la esercita — **critico**: la prima esecuzione reale sarebbe durante un incidente |
| **Rollback** (build/APK) | 🔴 Non esistente | Solo gli asset brand hanno rollback; una build/APK pubblicato non può essere "ritirato" a favore del precedente da nessuna interfaccia |

## Sintesi

| Classificazione | Conteggio |
|---|---|
| ✅ Automatizzato | 3 (Assets, APK/artefatto, Backup) |
| 🟡 Semi automatico | 8 (Creazione cliente, Generazione app, Build, Distribuzione, Crash, Queue, Monitoring, Eliminazione cliente) |
| ⚪ Manuale (per scelta corretta) | 4 (Configurazione, Logo, Feedback-lettura, Storage-monitoraggio, Supporto) |
| 🔴 Critico/gap reale | 5 (Firma, Aggiornamenti, Analytics, Ripristino mai testato, Rollback assente) |

I 5 processi 🔴 sono esattamente gli stessi identificati in `TECHNICAL_DEBT.md` e `CONTROL_ROOM_ROADMAP.md` nelle sessioni precedenti — nessuna sorpresa nuova, solo conferma che restano aperti perché richiedono codice di dominio (Build Engine, Analytics) o test operativi (Ripristino), non altre automazioni di superficie come quelle implementate finora.
