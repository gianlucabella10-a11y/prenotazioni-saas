# FIREBASE_CONFIGURATION_REQUIRED

> Il **codice** push è integrato e guardato (l'app funziona già senza Firebase; le notifiche restano disattivate e c'è il fallback email). Per attivare il push **reale end-to-end** servono questi file esterni — **nessuna credenziale va nel codice/repo**.

## File mancanti (a tuo carico)

| # | File | Dove va messo | Perché serve |
|---|---|---|---|
| 1 | **Progetto Firebase** | console.firebase.google.com (1 progetto per la piattaforma) | Contiene l'app Android/iOS e abilita FCM |
| 2 | `google-services.json` | `platform-mobile/apps/client_app/android/app/google-services.json` | Config FCM Android (app id, sender id) |
| 3 | `GoogleService-Info.plist` | `platform-mobile/apps/client_app/ios/Runner/GoogleService-Info.plist` | Config FCM iOS |
| 4 | **APNs Auth Key** (`.p8`) | caricata su Firebase → Project Settings → Cloud Messaging → Apple app config | Apple richiede APNs per il push iOS |
| 5 | **Service-account JSON** (Admin SDK) | sul server, percorso fuori dal repo (es. `storage/keys/fcm.json`) | Il backend (`FcmPushChannel`) invia via FCM HTTP v1 |

## Variabili ambiente backend (`.env`)
```
FCM_PROJECT_ID=il-tuo-project-id
FCM_CREDENTIALS_PATH=/percorso/assoluto/storage/keys/fcm.json
```
(Già lette da `config/services.php` → `services.fcm.*`, consumate da `FcmPushChannel`.)

## Passi nativi da completare quando hai i file (oggi NON fatti, per non rompere la build)
**Android** — `android/app/build.gradle.kts` + `android/build.gradle.kts`:
- applicare il plugin `com.google.gms.google-services`.
- (Il `bundle_id` per-tenant arriva con l'App Factory; per la build singola attuale resta `com.platform.client_app`.)

**iOS** — `ios/Runner`:
- aggiungere la capability **Push Notifications** + **Background Modes → Remote notifications**.
- `pod install` (le dipendenze Firebase iOS).

## Modo più semplice (consigliato)
```
dart pub global activate flutterfire_cli
cd platform-mobile/apps/client_app
flutterfire configure   # genera firebase_options.dart + i file nativi
```
> `PushNotificationService` oggi usa `Firebase.initializeApp()` (config nativa). Con `flutterfire configure` puoi anche passare a `Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform)` — entrambe vanno bene, dimmelo e adeguo la riga.

## Cosa succede senza questi file (stato attuale)
- App, web demo, dashboard, Control Room: **funzionano**.
- `PushNotificationService.init()` fallisce in silenzio → `isAvailable=false` → nessun token registrato.
- Conferme/promemoria: **consegnati via email** (channel chain `[FcmPush, Email]` con fallback già attivo).
