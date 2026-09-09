# APP_PROVISIONING_RUNBOOK

> Procedura operativa per portare un nuovo esercente da zero a APK installato. Solo Control Room, **nessun intervento sul codice**. Esempio: «Giuffrida Barber».
>
> Consolida anche il contenuto di `PRODUCTION_READY_RUNBOOK.md` (stesso scopo operativo, versione precedente — ora in `docs/archive/app-factory/`).

## Prerequisiti
- Accesso Control Room (super-admin + MFA): `/control-room`.
- Per la build reale: build-machine/CI con Android SDK + worker `php artisan queue:work` attivo + `APP_FACTORY_BUILD_DRIVER=local` (vedi `REAL_BETA_ENVIRONMENT.md`).

## Passi
1. **Crea cliente** — *Clienti ▸ + Nuovo cliente*: nome attività, settore, email titolare, piano, template, colore. → tenant + titolare + brand + sede + orari + catalogo + invito; identità app unica/immutabile. Stato: `draft`.
2. **Carica logo** — *App ▸ [cliente]* (o brand quick-setup): PNG/JPG/WebP ≥256px (validato). → stato `configured`.
3. **Configura dati attività** — il titolare completa servizi/operatori/orari dalla sua dashboard; nome/telefono/WhatsApp/social/colori dal brand. (Arrivano all'app via `/app/config`, senza build.)
4. **Genera app** — *Genera pacchetto*: Asset Factory (icone/splash/store versionati) + manifest 2.0 + cartella self-contained. Stato `ready_to_build`. Anteprima: logo/icona/splash/feature/colori.
   - CLI: `php artisan app:generate {tenant-uuid}`.
5. **Avvia build** — *Build Android*: `app_builds(queued)` → worker (`RunAppBuildJob`) → driver `local` esegue `flutter pub get → analyze → test → build apk`. Esito `built` con APK + checksum + log/durata (visibili in scheda).
6. **Registra versione** (opz.) — *Versioni*: version + build_number + note di rilascio (`active`).
7. **Genera link beta** — sul build `built`: *Link beta* → link con token (7g, limite download). Copia e invia all'esercente.
8. **Consegna** — l'esercente apre il link, scarica e installa l'APK; aggiungilo come **tester** (*Beta tester ▸ Invita*).

## Verifica
- Scheda app: stato/timeline, versioni, build + log + checksum, link beta + download count, tester, feedback.
- Dashboard *Flotta*: app totali, build ok/fallite/in corso, beta attive.

## Se qualcosa va storto
Vedi `BETA_DEBUG_RUNBOOK.md` (build failed / APK non installa / API error / push error).
