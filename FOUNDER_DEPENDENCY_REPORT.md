# FOUNDER_DEPENDENCY_REPORT — Tutto ciò che dipende ancora dal Founder

> Ogni voce verificata contro `FOUNDER_DAILY_WORKFLOW.md` e il codice reale — non ipotizzata.

## Click

- Creare un cliente (form).
- Genera / Build (2 click separati, non concatenati).
- Link beta / gestione tester.
- **Backup manuale** — non più necessario quotidianamente: ora schedulato automaticamente ogni notte (questa sessione), il pulsante "Backup ora" resta solo per un backup fuori-ciclo su richiesta.
- Archiviazione cliente (azione irreversibile, richiede conferma).

## Decisioni

- Quale piano/settore assegnare a un nuovo cliente (commerciale, corretto che resti umano).
- Quando sospendere/riattivare/archiviare un cliente.
- Quale build distribuire come link beta (nessuna promozione automatica "l'ultima build buona" a beta).
- Come rispondere a un feedback (nessun sistema lo fa per lui).

## Terminale

Solo per le operazioni che devono restarci per design (`FOUNDER_EXPERIENCE_AUDIT.md`): migration, deploy, segreti/keystore, bootstrap iniziale. **Zero terminale per l'operatività quotidiana**, confermato da questa e dalle due sessioni precedenti.

## Controlli

- Leggere il cruscotto ogni mattina (`/control-room`) — l'unico modo di sapere se qualcosa richiede attenzione, dato che non esistono ancora notifiche push/email verso il Founder (nessun canale di notifica proattiva implementato — il cruscotto è "pull", non "push").
- Controllare `/control-room/logs` in caso di segnalazione anomala.
- Controllare `/control-room/audit` per ricostruire cosa è successo.

## Riavvii

Nessuno richiesto quotidianamente — il backend/worker/scheduler girano come processi persistenti. Un riavvio serve solo dopo un deploy (attività Developer, non Founder).

## Configurazioni

Nessuna configurazione tecnica quotidiana. Le uniche "configurazioni" che il Founder tocca sono di dominio (brand, servizi, orari) — corrette da lasciare a lui/al titolare cliente.

## Invio file

- Link di invito al titolare (copia/incolla, fuori piattaforma).
- Link beta al cliente (copia/incolla, fuori piattaforma).

Nessun canale di invio integrato (email/SMS automatico) — è un gap reale ma sarebbe una funzionalità commerciale nuova, non un'automazione operativa (fuori scope per queste sessioni).

## Risposte ai clienti

Interamente manuale, fuori dalla piattaforma (non esiste un sistema di messaggistica/ticketing).

## Monitoraggio

Prima di questa sessione: richiedeva conoscere a memoria dove guardare (log file, query dirette al DB). Ora: **un solo punto d'ingresso** (`/control-room`), con alert che dicono esplicitamente cosa richiede attenzione, invece di dati grezzi da interpretare.

## La dipendenza residua più significativa

**Il cruscotto è "pull", non "push".** Il Founder deve ricordarsi di aprire la Control Room per scoprire un problema — non riceve una notifica (email/SMS/push) se una build fallisce di notte o se il worker si blocca. Questa è la singola automazione mancante con il rapporto beneficio/costo più alto rimasto (coerente con `AUTOMATION_CATALOG.md` #8, non ancora implementata — richiederebbe un canale di invio, es. email via `Mail`, già disponibile nello stack ma non collegato a questo scopo).
