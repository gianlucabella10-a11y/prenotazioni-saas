# OPERATING_PROCEDURES — Standard Operating Procedures

> Procedure da seguire passo-passo in un momento operativo reale (non spiegazioni concettuali — per quelle, vedi `OWNER_GUIDE.md`/`DEVELOPER_GUIDE.md`, qui solo linkati dove il dettaglio già esiste).

## SOP-01 — Nuovo cliente

1. Verifica di avere: nome attività, settore, email titolare, piano commerciale concordato.
2. Control Room → Clienti → "+ Nuovo cliente" → compila il form.
3. Salva l'invito e l'API key mostrati **una sola volta** in un posto sicuro (password manager), non solo nello schermo.
4. Invia il link di invito al titolare (canale a tua scelta — email, messaggio).
5. Carica logo/colori se il cliente te li ha forniti; altrimenti lascia il titolare farlo dalla propria Dashboard dopo l'accesso.
6. Procedi con SOP-02 quando il brand è pronto.

Dettaglio esteso: [`docs/Runbooks/APP_PROVISIONING_RUNBOOK.md`](docs/Runbooks/APP_PROVISIONING_RUNBOOK.md).

## SOP-02 — Nuova build

1. Verifica che il brand (logo, colori) sia già caricato e corretto — una build con brand sbagliato è tempo perso.
2. Control Room → Apps → scheda del progetto → "Genera".
3. Attendi conferma (manifest+asset generati), poi "Build" (Android).
4. Segui lo stato nella scheda finché non diventa `built`.
5. Verifica il checksum e la dimensione del file prima di distribuirlo.
6. Procedi con la distribuzione (link beta o pubblicazione store, `docs/Release/`).

## SOP-03 — Errore build

1. Apri la scheda build fallita in Control Room → leggi il log completo (mostra a quale passo si è fermata: `clean`/`pub get`/`analyze`/`test`/`build`).
2. Se l'errore è `flutter analyze`/`flutter test`: è un problema di codice Flutter — **non correggibile da Control Room**, richiede intervento Developer.
3. Se l'errore è "cartella Flutter non trovata" o simile: problema di configurazione della build machine (`APP_FACTORY_FLUTTER_APP_DIR`) — richiede Developer.
4. Se l'errore è intermittente (timeout, rete): ritenta semplicemente il "Build".
5. Se il problema persiste dopo un ritentativo, apri un'indagine seguendo [`docs/Runbooks/BETA_DEBUG_RUNBOOK.md`](docs/Runbooks/BETA_DEBUG_RUNBOOK.md).

## SOP-04 — APK corrotta / non installabile

1. Confronta il checksum del file scaricato con quello mostrato in Control Room — se non coincide, il file è corrotto in transito: rigenera il link beta (non serve una nuova build).
2. Se il checksum coincide ma l'installazione fallisce comunque: verifica che il dispositivo Android accetti "origini sconosciute" (installazione fuori Play Store).
3. Se il problema è una firma non valida: verifica se la build è stata firmata in debug invece che release (gap noto, `TECHNICAL_DEBT.md` #1) — un dispositivo con una versione precedente firmata diversamente rifiuta l'aggiornamento; serve disinstallare la versione precedente prima di installare la nuova.
4. Se nessuno dei punti sopra risolve, richiedi supporto Developer con il log della build allegato.

## SOP-05 — Aggiornamento cliente

1. Applica le modifiche richieste (brand, servizi, orari) dalla Dashboard del cliente o per suo conto.
2. Se le modifiche toccano il brand/identità dell'app: ripeti SOP-02 (nuova build necessaria).
3. Se le modifiche sono solo di contenuto (servizi, orari, testi): **non serve una nuova build** — questi dati sono letti dal backend a runtime, non compilati nell'APK.
4. Distribuisci la nuova build solo se generata al passo 2, con lo stesso canale già in uso col cliente.

## SOP-06 — Bug critico

1. Conferma la severità: dati a rischio? Servizio interrotto per più clienti? Solo un cliente specifico?
2. Se coinvolge isolamento dati tra clienti (sospetto di fuga cross-tenant): tratta come priorità massima, coinvolgi immediatamente un Developer — non tentare correzioni da Control Room, non esistono azioni di mitigazione lì per questo tipo di problema.
3. Se è un errore applicativo isolato: consulta `storage/logs/laravel.log` (Developer) o Sentry se configurato.
4. Comunica lo stato ai clienti impattati se il problema è visibile lato loro.
5. Dopo la risoluzione, documenta cosa è successo — se il problema rivela un gap non ancora tracciato, aggiungilo a `TECHNICAL_DEBT.md`.

## SOP-07 — Backup

1. Esegui `platform-infra/bin/backup-control-center.sh` (oggi manuale — vedi gap in `AUTOMATION_CATALOG.md`).
2. Verifica che l'archivio prodotto non sia vuoto/corrotto (apri e controlla dimensione).
3. Copia l'archivio fuori dalla macchina locale (storage esterno/cloud) — un backup che vive solo sulla stessa macchina del sistema non protegge da un guasto hardware.

Dettaglio: [`docs/Operations/BACKUP_RECOVERY_GUIDE.md`](docs/Operations/BACKUP_RECOVERY_GUIDE.md).

## SOP-08 — Ripristino

1. **Non improvvisare** — segui esattamente [`docs/Operations/BACKUP_RECOVERY_GUIDE.md`](docs/Operations/BACKUP_RECOVERY_GUIDE.md).
2. Prima di ripristinare, metti da parte lo stato attuale (anche se sospetto compromesso) — non sovrascrivere senza una copia di sicurezza dello stato "rotto".
3. Verifica dopo il ripristino: login super-admin funzionante, almeno un tenant visibile, un appuntamento di prova prenotabile.
4. Comunica ai clienti se il ripristino ha comportato perdita di dati recenti (tra l'ultimo backup e l'incidente).

## SOP-09 — Cambio logo

1. Dashboard del cliente (o Control Room per suo conto) → Personalizzazione/Quick Setup → carica il nuovo file (PNG/JPG/WebP, minimo 256×256, massimo 4MB).
2. Il sistema genera automaticamente le 14 varianti (icone/splash/store) — non serve caricarle manualmente.
3. Segui SOP-02 (nuova build necessaria — il logo è compilato negli asset nativi dell'app).

## SOP-10 — Cambio dominio

1. Non esiste un'azione automatizzata per questo in Control Room — è un'operazione infrastrutturale.
2. Aggiorna la configurazione DNS verso l'istanza corrente (fuori dal sistema applicativo).
3. Aggiorna `APP_URL`/certificati TLS lato server (Developer, terminale).
4. **Se il dominio cambia, ogni app già distribuita che punta al vecchio `API_BASE_URL` smette di funzionare** finché non viene ricompilata — questa è una conseguenza diretta dell'assenza di forced-update (`PLATFORM_LIFECYCLE.md` fase 9). Pianifica il cambio dominio con largo anticipo rispetto a un ciclo di rebuild di flotta.

## SOP-11 — Cambio API (contratto/versione)

1. Non esiste oggi un meccanismo di versioning API oltre `/api/v1` (nessuna `v2`, nessuna deprecazione formale — `REAL_PROJECT_STATE.md`).
2. Qualunque modifica non retrocompatibile a `/api/v1` rompe **tutte** le app già installate di **tutti** i tenant simultaneamente — non esiste un meccanismo di rollout graduale.
3. Prima di un cambiamento di contratto: verifica con un Developer se è additivo (sicuro) o distruttivo (richiede pianificazione — vedi `PROJECT_STANDARD.md` §10).
4. Se distruttivo, non procedere senza prima progettare `/api/v2` — non è un'operazione che questa SOP può coprire da sola, richiede una decisione architetturale.
