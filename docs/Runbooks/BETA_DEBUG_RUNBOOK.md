# BETA_DEBUG_RUNBOOK

> Diagnosi rapida dei problemi più comuni in beta. La Control Room mostra log/errori senza accedere al server.

## Build fallita (stato `failed`)
1. Control Room ▸ scheda app ▸ build ▸ apri **log** (collassabile): `command`, output, `exit_code`, durata.
2. Cause tipiche:
   - **Android SDK assente / `flutter` non in PATH** sulla build-machine → installa SDK, esporta `ANDROID_HOME`.
   - **`flutter analyze`/`flutter test` falliti** → la pipeline si ferma prima della build (per scelta): correggi e rilancia.
   - **Manifest assente** → *Genera pacchetto* prima della build.
   - **Worker non attivo** → la build resta `queued`: avvia `php artisan queue:work`.
3. Rilancia *Build Android*.

## APK non si installa sul telefono
- **"App non installata"**: disinstalla una versione precedente con stessa `applicationId` ma firma diversa (in beta usa sempre lo stesso keystore di piattaforma).
- **"Sorgente sconosciuta"**: Impostazioni ▸ App ▸ accesso speciale ▸ consenti installazione.
- **Link non valido/scaduto/revocato** (404): rigenera *Link beta* (scade a 7g, ha limite download, può essere revocato).
- **AAB invece di APK**: la distribuzione beta usa l'APK (`flutter-apk/app-release.apk`); l'AAB è per lo store.

## API error in app
- L'app punta all'`API_BASE_URL` iniettato a build-time: verifica che la build punti all'ambiente giusto (`REAL_BETA_ENVIRONMENT.md`).
- **401/403**: `TENANT_KEY` errato o tenant sospeso/terminato (l'app mostra la courtesy screen).
- **Tenant key mancante**: l'app fallisce all'avvio (`AppEnvironment.validate()`), packaging errato → rigenera il pacchetto.
- Evento `api_error` tracciato (AnalyticsService) + breadcrumb Sentry.

## Push non arriva
- Config FCM mancante (`FCM_*`) → vedi push readiness; il canale ha fallback email.
- Device non registrato: l'app registra il token a login (`PUT /me/devices`); verifica login/permessi notifiche.

## Crash in app
- Sentry raccoglie crash con tenant/versione (contesto). Cerca per release/tenant nella dashboard Sentry.
- Chiedi all'esercente la **versione** (in app) e il **device/OS**; correlali al feedback (*Feedback beta* in Control Room).

## Dove guardo
- **Control Room**: stato build, log, checksum, link beta + download count, feedback.
- **Sentry**: crash/exception. **Log applicativo**: `app_factory.build.*`.
