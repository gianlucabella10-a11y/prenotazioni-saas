# FLEET_CURRENT_STATE — Singola app vs. piattaforma vs. cosa manca

> Verificato leggendo `BuildFleet.php`, `AppProjectController.php`, `fleet.blade.php`, `HomeController.php` (cruscotto) — non ipotizzato. Correzione rispetto a sessioni precedenti: la Control Room **ha già** una vista di flotta reale (`/control-room/apps/flotta`), più ricca di quanto valutato in `CONTROL_ROOM_ROADMAP.md` ("build queue aggregata" era segnalata come mancante — in realtà esiste, solo non filtrabile/paginata).

## Gestito oggi a livello SINGOLA APP (`/control-room/apps/{uuid}`)

- Identità (bundle id, package name, template, font)
- Brand associato, anteprima app, cronologia versioni asset con rollback
- Generazione pacchetto (manifest)
- Build (trigger, log completo, checksum, dimensione)
- Link beta (creazione/revoca), tester (invita/attiva/blocca)
- Feedback (ultimi 10, sola lettura)
- Versioni app (gestione manuale)
- Download build/pacchetto

## Gestito oggi a livello PIATTAFORMA/FLOTTA (`/control-room/apps/flotta`, `/control-room` cruscotto)

- **Release train**: versione core corrente, numero di app allineate/stale/costruibili
- **Build native aggregate**: conteggio riuscite/fallite/in corso su tutta la flotta
- **Beta attivi**: conteggio tester attivi su tutta la piattaforma
- **Distribuzione per stato**: quante app in ogni stato del ciclo di vita (bozza→pubblicata)
- **Build recenti cross-tenant**: ultime 15 build di qualunque cliente, con nome cliente
- **Ricostruzione a lotti** *(implementata in questa sessione)*: un pulsante accoda la build per tutte le app stale, senza terminale/CI
- **Cruscotto generale** (sessione precedente): ultimi 5 clienti, ultime 8 build, alert su backup/coda/disco/build fallite — trasversale, non specifico alle app

## Manca COMPLETAMENTE a livello piattaforma

- **Filtro/ricerca sulla flotta**: "build recenti" è un elenco fisso di 15, non paginabile/filtrabile per cliente o stato — diventa insufficiente oltre poche decine di build al giorno (già anticipato in `PLATFORM_SCALABILITY_REPORT.md`)
- **Invio di massa** (email/notifica a un gruppo di clienti) — nessun canale integrato
- **Rigenerazione di massa di asset/logo/manifest** per un sottoinsieme di clienti scelto arbitrariamente (oggi solo "tutte le stale", non "questi 5 clienti specifici")
- **Archiviazione/disattivazione di massa** — l'azione esiste solo per singolo cliente
- **Aggregazione crash** in Control Room — Sentry esiste ma è un dashboard esterno separato, zero dati di crash visibili qui
- **Aggregazione feedback fleet-wide** — il feedback è visibile solo app per app, non un "inbox" unico
- **CPU/RAM del server** — solo spazio disco è oggi visibile (cruscotto), nessuna metrica di processo
- **Scadenze/certificati SSL** — non monitorati da nessuna parte nel codice
- **Salute email/push** (tasso di consegna FCM/SMTP) — nessuna vista aggregata, solo il canale stesso con fallback silenzioso
- **Ultimo login Founder** — non tracciato (esiste `users.last_login_at` per singolo utente, ma nessuna vista che lo mostri in Control Room)
- **Versioni installate sui dispositivi reali** — impossibile da sapere oggi: nessuna telemetria arriva dai device (Analytics è codice morto, `TECHNICAL_DEBT.md` #3), e comunque l'APK non porta un numero di versione realmente distintivo per build (`TECHNICAL_DEBT.md` #2)

## Correzione rispetto a `CONTROL_ROOM_ROADMAP.md`

Quel documento (sessione precedente) classificava "Build queue aggregata" come 🔴 NON ESISTE. Verificato ora più a fondo: **esiste**, sotto forma di conteggi aggregati e ultime 15 build — la classificazione corretta è 🟡 PARZIALE (manca filtro/paginazione/ricerca, non l'aggregazione in sé). Questo file corregge quella voce.
