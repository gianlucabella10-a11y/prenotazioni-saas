# FOUNDER_DAILY_WORKFLOW — Una giornata tipo, minuto per minuto

> Basato sulle schermate e azioni realmente esistenti dopo questa sessione (cruscotto, audit, backup, log, gestione cliente/build/beta). Nessun passaggio inventato.

```
08:30  Accendo il PC, apro il browser su /control-room.
08:31  Login (email + password, MFA se attiva).
08:32  Atterro sul Cruscotto. Leggo la sezione "Cosa devo fare adesso?":
       se non ci sono alert, tutto regolare — passo oltre.
       Se c'è un alert (es. "Ultimo backup: 3 giorni fa"), clicco
       direttamente "Crea backup ora" dal pulsante nell'alert stesso.
08:35  Do un'occhiata a "Ultimi clienti" e "Ultime build" nel cruscotto —
       verifico che non ci siano build fallite nella notte.
08:37  Arriva una richiesta: un nuovo cliente. Clicco "+ Nuovo cliente".
08:38  Compilo il form (nome, settore, email titolare, piano, colore) → Salva.
08:39  Copio il link di invito mostrato (una sola volta) e lo invio al titolare
       via email/messaggio (fuori dal sistema).
08:40  Il titolare, più tardi in giornata, accede alla propria Dashboard e
       carica logo/colori da sé — non serve la mia presenza per questo passo.
--------------------------------------------------------------------------
10:15  Il titolare mi scrive: "logo caricato, sono pronto".
10:16  Apro la scheda del suo cliente in Control Room → "Genera".
10:17  Attendo la conferma (manifest+asset pronti) → "Build".
10:18  Torno al Cruscotto e continuo altro lavoro mentre la build compila
       in background (minuti, non richiede la mia attenzione continua).
--------------------------------------------------------------------------
10:35  Controllo la scheda build: stato "built". Clicco "Link beta".
10:36  Copio il link e lo invio al cliente per l'installazione.
10:37  Il cliente installa l'APK sul proprio telefono (fuori dal sistema).
--------------------------------------------------------------------------
14:00  Un cliente segnala "l'app si comporta in modo strano".
14:01  Apro /control-room/logs, cerco errori recenti nelle ultime righe.
14:03  Se trovo qualcosa di anomalo che non capisco, scrivo al Developer
       allegando quanto letto — non ho bisogno di accedere al server.
14:05  Se invece è un problema di configurazione (es. orari sbagliati),
       lo risolvo io stesso dalla Dashboard del cliente.
--------------------------------------------------------------------------
16:30  Controllo /control-room/audit per rivedere le azioni della giornata
       (chi ha fatto cosa, utile se un cliente contesta un cambiamento).
16:40  Se non ho già creato un backup stamattina, lo creo ora da
       /control-room/backup — un click, conferma, fatto.
--------------------------------------------------------------------------
18:00  Ultimo giro sul Cruscotto: nessun alert attivo → spengo il PC.
```

## Cosa NON è successo in questa giornata

Nessuna apertura di terminale, VS Code, Flutter CLI, `artisan`, `git`, shell, `php`, `adb`, `cloudflared` — coerente con l'obiettivo dichiarato di questa sessione. L'unica eccezione strutturale (non toccata oggi, ma vera): se il PC del Founder venisse riavviato e la piattaforma non ripartisse da sola, il doppio click su `START_CONTROL_CENTER.command` apre comunque una finestra Terminal visibile (limite di macOS, non del codice — vedi `FOUNDER_EXPERIENCE_AUDIT.md`).
