# OPERATION_MANUAL — Uso quotidiano della piattaforma

Guida operativa, non tecnica. Per il funzionamento interno vedi `BUSINESS_FLOW.md`; per lo stato di ogni funzione vedi `REAL_PROJECT_STATE.md`.

## Avvio del centro operativo

Doppio click su [`START_CONTROL_CENTER.command`](START_CONTROL_CENTER.command) — avvia backend, worker di coda, scheduler, apre il browser su `http://127.0.0.1:8000/control-room`. Per fermare tutto: `stop-control-center.sh` o Ctrl-C nella finestra del terminale aperta. Dettaglio: [`docs/Operations/PREVIEW_ACCESS_GUIDE.md`](docs/Operations/PREVIEW_ACCESS_GUIDE.md).

## Attività quotidiane — Control Room (`/control-room`, operatore piattaforma)

| Attività | Dove |
|---|---|
| Creare un nuovo cliente | Clienti → "+ Nuovo cliente" — nome, settore, email titolare, piano, template, colore |
| Vedere lo stato di un cliente | Clienti → cerca per nome / filtra per stato |
| Attivare / sospendere / riattivare un cliente | Scheda cliente → pulsanti azione |
| Rigenerare l'invito del titolare | Scheda cliente → "Rigenera" (se scaduto/perso) |
| Caricare/aggiornare logo e colori per conto del cliente | Scheda cliente → Quick Setup brand |
| Generare il pacchetto app | Apps → scheda app → "Genera" |
| Lanciare una build | Apps → scheda app → "Build" (Android/iOS) |
| Creare un link beta per un tester | Apps → scheda app → build completata → "Link beta" |
| Invitare/gestire tester | Apps → scheda app → sezione Tester |
| Consultare il feedback ricevuto | Apps → scheda app → sezione Feedback (sola lettura) |
| Vedere lo stato dell'intera flotta | Apps → Flotta |

**Non ancora disponibile da qui** (verificato — non un'omissione di questa guida): terminare/archiviare definitivamente un cliente, vedere il registro di audit, gestire pagamenti/fatturazione, moderare il feedback.

## Attività quotidiane — Dashboard (`/dashboard`, titolare/staff del cliente)

| Attività | Dove |
|---|---|
| Vedere gli appuntamenti del giorno | Home |
| Confermare / completare / annullare una prenotazione | Prenotazioni |
| Gestire il catalogo servizi | Servizi |
| Gestire gli operatori | Operatori |
| Impostare orari di apertura e chiusure straordinarie | Disponibilità |
| Cambiare nome/colori/contatti dell'app | Personalizzazione |

## Manutenzione periodica

- **Backup**: `platform-infra/bin/backup-control-center.sh` — vedi [`docs/Operations/BACKUP_RECOVERY_GUIDE.md`](docs/Operations/BACKUP_RECOVERY_GUIDE.md) per ripristino.
- **Notifiche schedulate**: eseguono automaticamente ogni 15 minuti (`notifications:dispatch-due`) — nessuna azione manuale richiesta.
- **Pulizia dati storici**: 🟡 gap noto, nessun task automatico oltre le notifiche (`PROJECT_FREEZE_STATE.md` §9) — da fare manualmente finché non risolto strutturalmente.

## Se qualcosa non funziona

1. Log backend: `platform-backend/storage/logs/laravel.log`.
2. Build fallita: log completo visibile nella scheda build in Control Room; troubleshooting in [`docs/Runbooks/BETA_DEBUG_RUNBOOK.md`](docs/Runbooks/BETA_DEBUG_RUNBOOK.md).
3. Problema di accesso/credenziali: vedi [`docs/Operations/PREVIEW_ACCESS_GUIDE.md`](docs/Operations/PREVIEW_ACCESS_GUIDE.md).
4. Se il problema persiste: consultare `REAL_PROJECT_STATE.md`/`PROJECT_FREEZE_STATE.md` per verificare se è un limite noto del sistema, non un guasto.
