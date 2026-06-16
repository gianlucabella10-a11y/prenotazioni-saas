# PUSH_NOTIFICATION_READINESS

> Stato del sistema notifiche push dopo TASK 2. Foundation production-ready e white-label, **senza rotture**: backend **110/110**, Flutter **32 test + analyze pulito**. Push guardato: attivo appena fornisci i file Firebase (vedi `FIREBASE_CONFIGURATION_REQUIRED.md`).

## 1. Cosa è stato implementato (questa fase)
**Flutter**
- `firebase_core` + `firebase_messaging` aggiunti (risolti: 3.15.2 / 15.2.10).
- `lib/core/push/push_notification_service.dart`: init Firebase guardato, permessi (alert/badge/sound iOS, runtime Android), token FCM, refresh token, registrazione/rimozione backend, background handler top-level.
- Wiring in `lib/app/app.dart` (`ClientApp`): init all'avvio + `ref.listen(sessionController)` → registra il device per utente **autenticato e verificato**, lo rimuove al logout/cancellazione. `SessionController` **non** modificato (zero regressioni).
- `pushNotificationServiceProvider` agganciato a `me_repository` (PUT/DELETE `/me/devices` già esistenti).

**Backend**
- `devices`: aggiunti `tenant_id` (FK), `device_name`, `app_version` (migration additiva).
- `DeviceController`: salva i nuovi campi e **lega il device al tenant dell'utente**.
- `config/services.php`: sezione `fcm` (project_id, credentials_path) da `.env`.

## 2. Cosa è già funzionante (preesistente, riusato — non duplicato)
- **`FcmPushChannel`** completo (FCM HTTP v1 + OAuth service-account + pulizia token UNREGISTERED).
- **Outbox + orchestratore**: `AppointmentBooked` → `ScheduleAppointmentNotifications` (conferma + promemoria per offset tenant) → `SendNotificationJob`.
- **Channel chain `[FcmPush, Email]`**: prova push, **fallback automatico a email** → oggi conferme/promemoria **arrivano già via email**.
- **API devices** `PUT/DELETE /me/devices` (auth, isolamento per utente).
- Consenso marketing ri-controllato al momento dell'invio (GDPR).

## 3. Cosa richiede configurazione esterna
Vedi **`FIREBASE_CONFIGURATION_REQUIRED.md`**: progetto Firebase, `google-services.json`, `GoogleService-Info.plist`, APNs key, service-account JSON + `FCM_PROJECT_ID`/`FCM_CREDENTIALS_PATH`, e i passi nativi (plugin gradle Android, capability iOS, `pod install`). Senza, il push è disattivato ma l'app funziona (email fallback).

## 4. Come completare Firebase (sintesi)
1. Crea il progetto Firebase + app Android/iOS.
2. `flutterfire configure` (o aggiungi a mano i file nei percorsi indicati).
3. Applica plugin google-services (Android), capability Push + `pod install` (iOS).
4. Backend: metti il service-account JSON fuori dal repo + valorizza le 2 env.

## 5. Come testare end-to-end (dopo la config)
1. Avvia un **queue worker**: `php artisan queue:work` (i job notifica girano qui).
2. Login sull'app (device reale/emulatore con Google Play) come cliente verificato → concedi i permessi.
3. Verifica la registrazione: riga in `devices` con `fcm_token`, `tenant_id`, `platform` corretti.
4. Crea una prenotazione → arriva la **push di conferma** (e i promemoria agli offset del tenant). In assenza di device, la stessa notifica arriva via **email** (Mailpit in locale).
5. Test rapido alternativo: invia un messaggio di prova dalla **Firebase Console → Cloud Messaging** al token registrato.
6. Logout → la riga `devices` viene rimossa (nessun token ereditato da un altro account).

## 6. Sicurezza (verificata con test)
- Device legato a `user_id` (+ `tenant_id`): un utente registra/rimuove **solo** i propri device; lo stesso token-string per due utenti crea righe separate; guest → 401. (`DeviceRegistrationTest`, 5 test.)
- Push lato client: **mai** registrato senza Firebase disponibile (guard testato in `push_notification_service_test`).
- Nessuna logica cliente-specifica: il destinatario/brand derivano dal tenant (white-label, scala a 10→100 clienti senza codice dedicato).

## 7. Stato commerciale
| Scenario | Stato |
|---|---|
| **Demo** | ✅ Pronta: conferme/promemoria via email; push si attiva con Firebase |
| **Beta reale** | ✅ Pronta lato codice: serve solo creare il progetto Firebase + build nativa |
| **Cliente pagante** | ✅ OK una volta configurato Firebase e prodotta la build firmata (resta legato a deploy + App Factory per le app per-tenant) |

## Note
- Foundation: l'handler foreground e il deep-link da payload sono stub (l'OS mostra la notifica in background); si arricchiscono quando si definiscono i payload. `device_name`/`app_version` lato client si potranno popolare con `package_info_plus`/`device_info_plus` (oggi opzionali, nullable lato backend).
- Verifica: `php artisan test` (110) · `flutter analyze` (pulito) · `flutter test` (32). Nessun file core toccato oltre il wiring additivo.
