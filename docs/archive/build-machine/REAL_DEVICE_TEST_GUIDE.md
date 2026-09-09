# REAL_DEVICE_TEST_GUIDE

> Test della beta su un telefono Android reale. Per i beta tester e per il collaudo interno.

## Installazione (Android)
1. Apri il **link beta** ricevuto (token, valido 7 giorni).
2. Scarica `app-<versione>.apk`.
3. Se richiesto: Impostazioni ▸ App ▸ accesso speciale ▸ *Installa app sconosciute* → consenti per il browser.
4. Apri l'APK ▸ **Installa** ▸ **Apri**.

## Aggiornamento
- Apri il nuovo link beta, scarica e installa sopra (i dati restano; stessa keystore di piattaforma → update pulito).

## Disinstallazione
- Tieni premuta l'icona ▸ *Disinstalla* (o Impostazioni ▸ App).

## Rollback
1. Disinstalla la versione attuale.
2. Chiedi all'operatore il link della versione precedente (storico build in Control Room; versione marcata `deprecated`).
3. Installa l'APK precedente.

## Checklist di collaudo (su device reale)
- [ ] **Avvio**: l'app apre, mostra brand/logo/colori del cliente (no placeholder).
- [ ] **Login / registrazione**: accesso ok; verifica email se richiesta.
- [ ] **Booking**: vedo servizi/operatori/disponibilità; creo una prenotazione; la vedo in elenco.
- [ ] **Notifiche**: ricevo la notifica di conferma (se FCM configurato; altrimenti email).
- [ ] **Pagamenti**: se abilitati per il piano, il flusso si apre correttamente.
- [ ] **Logout**: esco e rientro senza errori.
- [ ] **Feedback**: invio una segnalazione → arriva in Control Room.

## Cosa segnalare nei bug
Versione app (in-app), modello/OS del telefono, passi per riprodurre. I crash vanno automaticamente a Sentry (con tenant/versione).

Riferimenti: `BETA_DEBUG_RUNBOOK.md`, `APP_PROVISIONING_RUNBOOK.md`.
