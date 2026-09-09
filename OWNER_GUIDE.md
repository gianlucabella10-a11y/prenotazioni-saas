# OWNER_GUIDE — Guida per il proprietario della piattaforma

Per l'uso quotidiano sintetico vedi anche [`OPERATION_MANUAL.md`](OPERATION_MANUAL.md) — questa guida approfondisce ogni singola responsabilità del proprietario, incluse quelle non quotidiane (monitoraggio, backup, ripristino, manutenzione).

## Come creare clienti

Control Room → Clienti → "+ Nuovo cliente". Servono: nome attività, settore (determina il catalogo di partenza), email del titolare, telefono, piano, colore principale, template. Al salvataggio ricevi **una sola volta** il link di invito per il titolare e la sua API key — se li perdi, si rigenerano da scheda cliente ("Rigenera invito"), non si recuperano in chiaro dopo. Dettaglio passo-passo: [`docs/Runbooks/APP_PROVISIONING_RUNBOOK.md`](docs/Runbooks/APP_PROVISIONING_RUNBOOK.md).

## Come gestire le build

Apps → scheda app → "Genera" (assembla manifest+asset) → "Build" (compila). Segui lo stato in tempo reale nella scheda; ogni build conserva il log completo, il checksum, la dimensione del file. Se una build fallisce, il log ti dice a quale passo (`flutter clean`/`pub get`/`analyze`/`test`/`build`) — vedi [`docs/Runbooks/BETA_DEBUG_RUNBOOK.md`](docs/Runbooks/BETA_DEBUG_RUNBOOK.md).

**Attenzione**: la build firma in modalità debug se non hai configurato manualmente il keystore di release (gap noto, non un errore da parte tua — vedi `TECHNICAL_DEBT.md` #1). Prima di distribuire fuori dal test interno, verifica la firma.

## Come aggiornare le app già distribuite

Non esiste oggi un meccanismo di forced-update automatico (la tabella che lo prepara, `app_versions`, è gestita a mano da Control Room e non confrontata con la versione installata sui device — gap noto). Per aggiornare un cliente: modifica brand/servizi come necessario → "Genera" di nuovo → "Build" di nuovo → distribuisci il nuovo APK con lo stesso meccanismo (link beta o store).

## Come gestire la beta

Apps → scheda app → build completata → "Link beta": genera un token scaricabile, valido 7 giorni, con un limite di download (default 50). Puoi revocarlo in ogni momento dalla stessa schermata. Guida per chi riceve il link: [`docs/Runbooks/BETA_TESTING_GUIDE.md`](docs/Runbooks/BETA_TESTING_GUIDE.md).

## Come gestire i tester

Apps → scheda app → sezione Tester: "Invita" (nome/email/device) → stato iniziale `invited`. Puoi passare un tester ad `active` o `blocked` in ogni momento. Il feedback che inviano dall'app è visibile nella stessa scheda (ultimi 10, sola lettura) — oggi non puoi rispondere o moderarlo da qui.

## Come monitorare gli errori

- **Crash app/backend**: Sentry, se configurato (`SENTRY_LARAVEL_DSN` backend, `SENTRY_DSN` build Flutter) — in ambiente locale è disattivato per design, nessun dato esce dalla macchina finché non imposti queste variabili in produzione.
- **Log applicativi**: `platform-backend/storage/logs/laravel.log` (+ `worker.log`, `scheduler.log` se avviato con lo script one-click).
- **Analytics prodotto**: 🔴 non disponibile oggi — l'infrastruttura esiste lato app ma non è collegata a nulla (nessun evento viene raccolto, vedi `TECHNICAL_DEBT.md`). Non aspettarti dati di utilizzo finché questo gap non è risolto.

## Come fare backup

```bash
platform-infra/bin/backup-control-center.sh
```
Copia database + storage. Dettaglio e frequenza consigliata: [`docs/Operations/BACKUP_RECOVERY_GUIDE.md`](docs/Operations/BACKUP_RECOVERY_GUIDE.md).

## Come ripristinare il sistema

Segui esattamente la procedura in [`docs/Operations/BACKUP_RECOVERY_GUIDE.md`](docs/Operations/BACKUP_RECOVERY_GUIDE.md) — non è descritta qui per evitare due fonti di verità sulla stessa procedura (vedi `PROJECT_STANDARD.md` §4).

## Come fare manutenzione

- **Notifiche schedulate**: automatiche ogni 15 minuti, nessuna azione richiesta.
- **Pulizia dati storici**: 🟡 nessun task automatico esiste oltre le notifiche — richiede intervento manuale periodico finché non risolto strutturalmente (`PROJECT_EVOLUTION_ROADMAP.md`).
- **Aggiornamento dipendenze** (Laravel, pacchetti Composer/npm, Flutter/pub): non documentato con una cadenza fissa in questo repository — responsabilità dello sviluppatore, non dell'operatore.
- **Verifica periodica di questa stessa documentazione**: raccomandata trimestrale (`PROJECT_EVOLUTION_ROADMAP.md` punto 20) — una guida operativa non aggiornata è più pericolosa di nessuna guida.
