# REAL_FIRST_INSTALL_REPORT (FASE 6)

> Esito del tentativo di installare la prima APK su device reale, con i fatti verificati su questa macchina.

## Stato attuale (verificato, FASE 0/1)
- `flutter clean` ✅ · `flutter pub get` ✅ (dipendenze risolte).
- `flutter build apk --release` → **`[!] No Android SDK found`**.
- Quindi **su questa macchina l'APK NON è stato prodotto** (manca Android SDK + Java). Nessun errore di codice: la pipeline avanza fino al check dell'SDK.

Di conseguenza l'installazione su device **non è eseguibile da qui**: serve la build-machine con l'SDK (vedi i comandi in `FIRST_REAL_BETA_READY.md`). Quanto segue è la procedura + checklist da eseguire **una volta prodotto l'APK**.

## Procedura installazione (Android fisico)
1. Produci l'APK sulla build-machine (`php artisan app:build <uuid> android`).
2. Control Room ▸ build **Compilata** ▸ **Link beta** → apri il link sul telefono (o `adb install app-release.apk`).
3. Consenti "installa da sorgente sconosciuta" se richiesto ▸ Installa ▸ Apri.

## Checklist collaudo (da spuntare sul device)
- [ ] **Install**: APK installa senza "App non installata".
- [ ] **Open app**: avvio ok, brand/logo/colori del cliente (no placeholder).
- [ ] **API connection**: l'app carica `/app/config` e i dati (nessun errore rete).
- [ ] **Login**: accesso/registrazione ok.
- [ ] **Booking**: vedo servizi/disponibilità, creo una prenotazione, la ritrovo.
- [ ] **Notification**: ricevo la conferma (FCM o fallback email).
- [ ] **Feedback**: invio una segnalazione → compare in Control Room.

## Problemi noti / attesi
- "No Android SDK found": atteso senza toolchain → installa SDK (vedi report finale).
- "App non installata" in update: usa **sempre** la stessa keystore di piattaforma (`ANDROID_SIGNING_FINAL.md`).
- API error: verifica `API_BASE_URL` iniettato e `X-Tenant-Key`.

## Conclusione
Tutto pronto lato prodotto; l'unico passo mancante per avere l'APK in mano è il provisioning della toolchain Android sulla build-machine, dopodiché questa checklist è percorribile sul telefono. Comandi esatti: `FIRST_REAL_BETA_READY.md`.
