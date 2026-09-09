# BETA_RELEASE

> Come distribuire un'app cliente ai **beta tester** senza pubblicazione sullo store. Android via **Firebase App Distribution** (o APK con link privato); iOS via **TestFlight**. La build nativa gira su CI/Mac (non in questo ambiente).

## Architettura della distribuzione
```
Control Room → Build (1 click) → app_builds(queued→building)
   → workflow app-factory-build (CI) → AAB/IPA firmato
   → Firebase App Distribution (Android) / TestFlight (iOS)
   → tester installano dal link/app
   → CI richiama `php artisan app:build-record … built` (timeline chiusa)
```
Beta e store sono **canali separati**: il workflow distribuisce in beta se `FIREBASE_APP_ID_ANDROID` è impostato, e/o pubblica sullo store se `PLAY_SERVICE_ACCOUNT_JSON` è impostato.

## 1. Come generare la build (operatore, dalla Control Room)
1. Crea/configura il cliente, carica il logo, **Genera pacchetto**.
2. **Build Android** / **Build iOS** (1 click): crea `app_builds(queued)` e accoda il worker (`RunAppBuildJob`).
3. Driver:
   - `manual` (default): registra l'intento; lancia la CI a mano (Actions ▸ *app-factory-build* ▸ `tenant_uuid`).
   - `github`: la build parte da sola (richiede `APP_FACTORY_GITHUB_REPO`/`TOKEN`).

## 2. Android — Firebase App Distribution
**Setup una tantum** (segreti repo, vedi `.env.example` / `APP_FACTORY_RELEASE_SECRETS.md`):
- `FIREBASE_APP_ID_ANDROID` — App ID Firebase (es. `1:123:android:abc`).
- `FIREBASE_SERVICE_ACCOUNT_JSON` — service account con ruolo *Firebase App Distribution Admin*.
- Gruppo tester `beta-testers` creato nella console Firebase.

**Flusso**: il workflow `app-factory-build` (job `android`) dopo aver prodotto l'AAB firmato esegue lo step *Distribute beta (Firebase App Distribution)* → i tester del gruppo ricevono l'email/notifica e installano.

**Alternativa senza Firebase**: scarica l'AAB/APK come artifact del workflow e condividi un **link privato** (l'APK è installabile direttamente; l'AAB va convertito con `bundletool`).

## 3. iOS — TestFlight
- Richiede l'**account Apple Developer del cliente** + API key App Store Connect (`APP_STORE_CONNECT_*`).
- Il job `ios` produce l'IPA firmato e lo carica su **TestFlight**; inviti i tester dalla console App Store Connect.
- Senza account del cliente non è possibile distribuire iOS (vincolo Apple, non aggirabile).

## 4. Come invitare i tester
- **Android (Firebase)**: console Firebase ▸ App Distribution ▸ gruppo `beta-testers` ▸ aggiungi email. Ogni nuova build li notifica.
- **iOS (TestFlight)**: App Store Connect ▸ TestFlight ▸ tester interni/esterni.

## 5. Stato in Control Room
La scheda app mostra la **timeline** (queued → building → built) e lo **storico** con `error_message` in caso di fallimento; la dashboard **Flotta** conta build riuscite/fallite/in corso.

## Cosa richiede account esterni
Firebase project (Android beta), account Apple Developer del cliente (iOS), runner CI. Tutto il resto (generazione, accodamento, tracking, parametrizzazione) è automatizzato.
