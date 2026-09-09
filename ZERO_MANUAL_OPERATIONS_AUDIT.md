# ZERO_MANUAL_OPERATIONS_AUDIT — Ogni operazione terminale, verificata

> Estende `OPERATIONS_BLUEPRINT.md` (che già classificava Control Room / Terminale / Automatizzabile) con motivo di esistenza per ognuna, e riflette lo stato **dopo** l'implementazione fatta in questa sessione (Fase 3: terminazione cliente, audit log, backup — tutte e tre ora disponibili da Control Room, non più solo da terminale).

| Operazione | Motivo / perché esiste | Automatizzabile? | Control Room? | Deve restare tecnica? |
|---|---|---|---|---|
| `php artisan serve` / servizio backend | Deve esistere un processo che serve HTTP — è ciò che ospita la Control Room stessa | ✅ Già automatizzato in produzione (systemd) | ❌ No — non può esporsi un pulsante per avviare il processo che ospita quel pulsante | ✅ Sempre |
| `php artisan queue:work` | Esegue i job in coda (build, notifiche) fuori dal ciclo richiesta/risposta | ✅ Già systemd in produzione | 🟡 Solo il monitoraggio (stato attivo), non l'avvio | ✅ Avvio/riavvio |
| `php artisan schedule:work` / cron | Esegue `notifications:dispatch-due` ogni 15 minuti | ✅ Già automatizzato | 🟡 Solo monitoraggio | ✅ Avvio |
| `php artisan migrate` | Applica modifiche allo schema del database | ❌ Mai, per design — troppo rischioso | ❌ Mai — nessun pulsante deve poter alterare lo schema | ✅ Sempre |
| `flutter build apk` (diretto, fuori dal sistema) | Compilazione nativa dell'app | — | ✅ **Già** disponibile (`BuildService`→`LocalBuildDispatcher`, verificato in sessioni precedenti) | Solo se si usa Flutter fuori dal sistema orchestrato |
| `app:generate` / `app:build` (chiamata diretta CLI) | Genera pacchetto / lancia build | Parziale (rebuild flotta) | ✅ **Già** disponibile (pulsanti "Genera"/"Build") | No |
| `app:build-matrix` | Calcola quali app sono "stale" per rebuild in batch | ✅ Ottimo candidato a scheduling | ❌ Oggi solo CI via SSH | 🟡 Fino a quando non esposto (vedi `AUTOMATION_CATALOG.md` #3) |
| Variabili d'ambiente (`.env`) | Configurazione e segreti | ❌ Per design | ❌ Mai — un pulsante che scrive segreti è un rischio di sicurezza inaccettabile | ✅ Sempre |
| Copia manuale di file/configurazioni | Setup iniziale ambiente | ✅ Già nel deploy script | ❌ No | ✅ Sempre |
| Storage (dischi, permessi, `storage:link`) | Setup filesystem | ✅ Già nel deploy script | ❌ No, è un dettaglio infrastrutturale one-time | ✅ Sempre |
| `cloudflared` / tunnel | Esporre il backend pubblicamente senza deploy reale | ❌ No, è per sua natura un'operazione di rete manuale | ❌ No | ✅ Sempre |
| `adb install` | Installare l'APK su un device fisico via USB | ❌ No | 🟡 Sostituibile con il link beta già esistente per la distribuzione — `adb` resta utile solo per debug locale diretto | 🟡 Solo per debug hardware diretto |
| Keystore (creazione/gestione) | Firma release Android | ❌ No, per design (segreto) | ❌ Mai — le chiavi di firma non devono avere un percorso UI | ✅ Sempre |
| `queue:restart` | Riavvia i worker dopo un deploy | ✅ Già nel deploy script (`systemctl restart platform-queue`) | ❌ No | ✅ Sempre (tocca processi di sistema) |
| **Backup** (`backup-control-center.sh`) | Copia database + storage | ✅ **Automatizzabile** (schedulazione, vedi `AUTOMATION_CATALOG.md` #1) | ✅ **FATTO in questa sessione** — pulsante "Backup ora" in Control Room (`/control-room/backup`), stessa logica del terminale | No, ora è un'azione UI |
| Consultazione log applicativi (`tail -f laravel.log`) | Debug/diagnosi | No, la lettura resta umana | 🔴 Ancora da fare — non implementato in questa sessione (fuori dallo scope dei 3 gap essenziali scelti, vedi motivazione in `CONTROL_ROOM_AUTOMATION_PLAN.md`) | 🟡 Parzialmente |
| **Terminazione/archiviazione cliente** | Il modello dati (`TenantStatus::Terminated`) esisteva da sempre, nessuna azione la raggiungeva | — | ✅ **FATTO in questa sessione** — pulsante "Archivia" nella scheda cliente | No, ora è un'azione UI |
| **Consultazione audit log** | I dati (`audit_logs`) esistevano da sempre, nessuna vista li mostrava | — | ✅ **FATTO in questa sessione** — pagina `/control-room/audit`, con filtro per azione | No, ora è una vista UI |
| Deploy (`deploy.sh`) | Pubblica una nuova versione del backend | 🟡 Parziale (CI con gate umano) | ❌ No, in questa fase — troppo rischioso per un pulsante diretto senza revisione | ✅ Sì, per ora |
| Cache (`config:cache`, `route:cache`) | Ottimizzazione performance a deploy | ✅ Già nel deploy script | ❌ No, è un dettaglio di deploy | ✅ Sempre |

## Sintesi

Delle operazioni realmente marcate "🔴 richiede ancora terminale ed era evitabile" nell'audit di partenza (`OPERATIONS_BLUEPRINT.md`), **3 su 3 sono state chiuse in questa sessione**: backup, terminazione/archiviazione cliente, audit log. Le operazioni che restano da terminale (migration, deploy, segreti, keystore, avvio processi) **devono restarci per design** — esporle in UI non sarebbe un progresso, sarebbe un rischio di sicurezza o di integrità dati.
