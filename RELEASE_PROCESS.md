# RELEASE_PROCESS

> Gestione versioni, beta, rollback e update per le app per-tenant. Tutto dalla Control Room; `AppVersion` è il registro delle versioni.

## Versioni
- Ogni rilascio = una `AppVersion` (per App Project): `version` (es. 1.2.0), `build_number` (intero crescente), `release_notes`, `status` (`active`/`deprecated`).
- Registra la versione in Control Room ▸ scheda app ▸ *Versioni* prima/dopo la build.
- `build_number` deve **crescere** ad ogni build pubblicata (Android richiede versionCode crescente).

## Beta
1. Build Android (`built`) → *Link beta* (token, 7g, limite download, revocabile).
2. Invita i tester (roster invited/active/blocked) e invia il link.
3. Raccogli feedback (in-app → Control Room) e crash (Sentry).

## Update
- Nuova versione: bump `build_number`, *Genera pacchetto* → *Build Android* → nuovo *Link beta*.
- I tester aggiornano installando il nuovo APK (o via Firebase App Distribution / TestFlight).
- `/app/config` espone `release` (version + build_number dell'ultima `active`): **prep forced-update** — il client potrà confrontare il proprio build_number e invitare/forzare l'update (logica lato app: fase futura, additiva).

## Rollback
- **Contenuti/brand**: rollback asset (storico mai cancellato) + rigenera pacchetto.
- **Binario**: marca la versione problematica `deprecated`; ridistribuisci il link beta della versione precedente (lo storico build resta in Control Room). Su Play, ripubblica un `build_number` superiore con il codice precedente (Android non consente downgrade del versionCode).

## Store (oltre la beta)
- Android: AAB firmato → Google Play (track internal→production).
- iOS: IPA → TestFlight/App Store (account Apple del cliente).
- Vedi `BETA_RELEASE.md` e `APP_FACTORY_RELEASE_SECRETS.md`.

## Checklist rilascio
1. `build_number` incrementato · 2. pacchetto generato · 3. build `built` (log verde) · 4. `AppVersion` registrata · 5. link beta generato · 6. tester notificati · 7. feedback/crash monitorati.
