# REAL_DEVICE_TEST (FASE 6)

> Test dell'APK su un telefono Android reale. Prerequisito: backend raggiungibile dal telefono (vedi `docs/Deployment/DEPLOYMENT_READY.md`) e APK compilato verso quell'`API_BASE_URL`.
>
> Consolida anche `REAL_DEVICE_TEST_GUIDE.md` (checklist quasi-duplicata di fase diversa, ora in `docs/archive/build-machine/`).

## Installazione
1. Apri il **Link beta** sul telefono (o trasferisci l'APK e aprilo / `adb install app-release.apk`).
2. Consenti "Installa app da sorgente sconosciuta" se richiesto.
3. Installa ▸ Apri.

## Permessi (al primo avvio)
- Notifiche (push): consenti per ricevere conferme prenotazione.
- Nessun permesso sensibile aggiuntivo richiesto dal flusso base.

## Checklist (spunta sul device)
- [ ] **Prima apertura**: splash + brand/logo/colori del cliente (no placeholder).
- [ ] **API connection**: l'app carica `/app/config` e il catalogo (nessun errore rete).
- [ ] **Login / registrazione**: accesso ok.
- [ ] **Booking**: servizi/operatori/disponibilità → crea prenotazione → la ritrovi.
- [ ] **Notifiche**: ricevi la conferma (FCM, o fallback email).
- [ ] **Feedback**: invia una segnalazione → compare in Control Room (sezione Feedback beta).

## Problemi comuni
- **API error / schermata di cortesia**: l'APK punta a un `API_BASE_URL` non raggiungibile → ricompila verso il PUBLIC_URL (vedi `DEPLOYMENT_READY.md`).
- **"App non installata" all'update**: usa sempre la stessa keystore di piattaforma.
- **Crash**: arriva su Sentry con tenant + versione; correla col feedback.

Riferimenti: `DEPLOYMENT_READY.md`, `SIGNED_APK_READY.md`, `CONTROL_ROOM_OPERATOR_GUIDE.md`.
