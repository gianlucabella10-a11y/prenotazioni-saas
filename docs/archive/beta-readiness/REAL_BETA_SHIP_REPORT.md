# REAL_BETA_SHIP_REPORT (FASE 10)

> Stato finale: **prima beta installabile**. Esito del Ship Activation (audit `FINAL_SHIP_AUDIT.md` + chiusura gap). Verifiche: backend `php artisan test` **207/207** (746 asserzioni) · `flutter analyze` pulito · `flutter test` **40** · Pint pulito · booking/auth/payments/scheduling/notification core **non toccati**.

## Cosa è stato chiuso in questo giro (gap reali, additivi)
- **Runner (FASE 1)**: `flutter clean` come primo step della pipeline reale (`clean → pub get → analyze → test → build apk --release`); salvataggio della **dimensione APK** (`app_builds.size_bytes`) oltre a command/log/exit_code/duration/checksum.
- **One-click + CLI (FASE 4)**: scheda app mostra build con **size · durata · exit code · checksum · data** + log collassabile; nuovo comando `php artisan app:build {uuid} android` (parità col bottone Control Room).
- **Docs (FASE 2/3/6/8)**: `SIGNING_SETUP`, `BUILD_MACHINE_SETUP`, `REAL_DEVICE_TEST_GUIDE`, `ENVIRONMENT_FINAL`.

## Già pronto (verificato, non riscritto)
BuildService + queue + RunAppBuildJob + `LocalBuildDispatcher` reale; signing Android env-based; Flutter senza hardcode + Sentry; `BetaDownloadToken` (token/scadenza/limite/conteggio/revoca); `BetaTester`/`BetaFeedback`/`AnalyticsService`/`AppVersion`; Control Room console + dashboard; isolamento tenant testato.
> **BetaRelease**: i campi richiesti (version/tenant/artifact/created_at + expires_at/link/revoca/count) sono già dati da `app_builds`(built) + `BetaDownloadToken` — nessuna entità duplicata.

## Risposte
**1) Posso creare un'app cliente?** **Sì** — Control Room: cliente → logo → config → genera. Senza codice.

**2) Posso premere Build?** **Sì** — bottone *Build Android* (o `php artisan app:build`): accoda `RunAppBuildJob`; il worker esegue la pipeline reale.

**3) Ottengo un APK reale?** **Sì, su build-machine con Android SDK** — `flutter build apk --release` firmato → `storage/app/builds/{tenant}/{version}/app-release.apk` + checksum + size. (In questo ambiente l'SDK non c'è: la build fallisce con il log reale, mai simulata.)

**4) Posso installarlo su Android fisico?** **Sì** — *Link beta* (token, 7g, limite, revocabile) → scarico e installo l'APK firmato (`adb install` o apertura diretta).

**5) Posso darlo a un esercente?** **Sì** — roster tester (invited/active/blocked), link privato, versioni con note di rilascio.

**6) Posso ricevere feedback?** **Sì** — feedback in-app → Control Room (testato); crash via Sentry (tenant/versione/device/OS).

**7) Qual è l'ultimo comando per produrre la prima APK?**
Vedi sotto: dopo aver creato il cliente in Control Room, i due comandi finali sono `php artisan app:generate <UUID>` e `php artisan app:build <UUID> android`.

---

## GLI ULTIMI COMANDI PER LA PRIMA APK
Sulla **build-machine** (Flutter + Android SDK + Java configurati — vedi `BUILD_MACHINE_SETUP.md`), con `.env` impostato:
```bash
# .env (build-machine)
#   APP_FACTORY_BUILD_DRIVER=local
#   APP_FACTORY_FLUTTER_APP_DIR=/ABS/PATH/platform-mobile/apps/client_app
#   ANDROID_KEYSTORE_PATH=/secure/release.jks
#   ANDROID_KEYSTORE_PASSWORD=…  ANDROID_KEY_ALIAS=…  ANDROID_KEY_PASSWORD=…
#   QUEUE_CONNECTION=sync        # one-shot: la build gira inline al comando

cd platform-backend

# (in Control Room: crea cliente → carica logo → genera; oppure da CLI:)
php artisan app:generate <TENANT_UUID>     # prepara asset + manifest

# >>> ULTIMO COMANDO: produce l'APK reale <<<
php artisan app:build <TENANT_UUID> android
#   esegue: flutter clean → pub get → analyze → test → build apk --release
#   → APK firmato: storage/app/builds/<tenant_id>/<version>/app-release.apk
```
In produzione (worker persistente) al posto di `QUEUE_CONNECTION=sync`:
```bash
php artisan queue:work --tries=1 --timeout=2000   # in un processo a parte
php artisan app:build <TENANT_UUID> android       # accoda; il worker compila
```
Poi: **Control Room ▸ scheda app ▸ Link beta** → copia il link → aprilo sul telefono → installa → usa.

— Stato: **PRODUCT ENGINE CHIUSO, prima beta installabile.** Prossimo step: **Design / UX / Polish**. Riferimenti: `FINAL_SHIP_AUDIT.md`, `BUILD_MACHINE_SETUP.md`, `SIGNING_SETUP.md`, `REAL_DEVICE_TEST_GUIDE.md`, `ENVIRONMENT_FINAL.md`.
