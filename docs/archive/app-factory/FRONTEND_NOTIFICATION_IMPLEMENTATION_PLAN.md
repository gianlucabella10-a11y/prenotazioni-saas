# FRONTEND_NOTIFICATION_IMPLEMENTATION_PLAN

> Piano FASE 0 (analisi + proposta) per due gap commerciali: **(1) Business Info premium** e **(2) Push notification end-to-end**. Nessun codice applicativo modificato finché non approvi. Stack: Laravel 11 + Flutter 3.44 (Riverpod, go_router, Dio, url_launcher).

---

# TASK 1 — BUSINESS INFO SCREEN PREMIUM

## 1. File coinvolti
**Backend**
- `database/migrations/…_add_business_contacts_to_brand_profiles.php` (NUOVO)
- `app/Modules/Branding/Application/BuildWhiteLabelConfig.php` (estensione payload)
- `app/Modules/Dashboard/Http/Controllers/BrandingController.php` + `resources/views/dashboard/branding/index.blade.php` (form di editing — self-service esercente)

**Flutter**
- `lib/features/white_label/domain/white_label_config.dart` (estendere il modello)
- `lib/features/business/presentation/business_info_screen.dart` (rebuild premium)
- riuso: `staffProvider` (`GET /staff` già esistente), `url_launcher` (già in pubspec)

## 2. Modello dati usato (REALE, oggi)
- `BrandProfile`: `app_name`, `tagline`, `primary/secondary_color`, `theme`, `config_version`, `privacy_policy_url`, `terms_url`, `support_url`. → **nessun** campo contatti/social/website.
- `Location`: `name`, `address`, `phone`, `timezone`, booking knobs, `settings` (json). → telefono/indirizzo per-sede sì; email/website/social no.
- `LocationSchedule`: orari settimanali per sede (esistono ma **NON** esposti in `/app/config`).
- `StaffMember` / `GET /staff`: `display_name`, `role_label`, `service_uuids`. → **nessuna foto**.

## 3. API chiamate dall'app
- `GET /app/config` (white-label config) → **da estendere** con `logo_url`, `contacts`, `social`, e `opening_hours` per location.
- `GET /staff` (già esistente) → lista operatori (senza foto).

## 4. Cosa manca → Modifica proposta (minima, additiva, white-label)
**A) Migration — colonne nullable su `brand_profiles`** (livello business, 1 per tenant):
`contact_email`, `website_url`, `whatsapp_number`, `whatsapp_message` (precompilato opz.), `instagram_url`, `facebook_url`. *(Il `phone`/`address` restano su `Location`.)*

**B) `BuildWhiteLabelConfig`** — aggiungere al payload (retrocompatibile):
- `logo_url`: URL del logo da `brand_assets` (kind=logo) servito dal disco — collega l'asset già caricabile dalla Control Room.
- `contacts`: `{ phone (da location principale), email, website }`.
- `social`: `{ instagram_url, facebook_url, whatsapp: { number, message } }` (chiavi `null` se mancanti).
- per ogni `location`: `opening_hours` (da `LocationSchedule`, formato `weekday → [{start,end}]`, con giorni chiusi = assenti).

**C) Dashboard branding** — campi per editare contatti/social (self-service esercente, oggi assenti) + l'upload logo lato esercente (oggi solo super-admin).

**D) Flutter `WhiteLabelConfig`** — aggiungere `logoUrl`, `BusinessContacts`, `BusinessSocial`, e `openingHours` dentro `TenantLocation`.

**E) `business_info_screen.dart` — rebuild premium** con fallback elegante (sezione nascosta se dato `null`, niente spazi vuoti):
- **Header**: logo (o iniziali), nome, categoria (settore), descrizione/tagline.
- **Contatti**: telefono (`tel:`), email (`mailto:`), sito (`https`) — via `url_launcher`.
- **Social/WhatsApp**: pulsanti Instagram, Facebook, **WhatsApp** (`https://wa.me/<num>?text=<msg>`), solo se configurati. Nessuna chat interna/CRM: solo deep-link esterno.
- **Orari**: calendario settimanale Lun–Dom (chiuso / doppia fascia pausa pranzo gestiti dalle regole).
- **Staff**: card con **avatar a iniziali** (no foto: vedi nota), nome, ruolo.

> **Nota onesta — foto staff**: oggi non esiste un campo/upload avatar per `StaffMember`. Propongo **fallback a iniziali** in FASE 1 e di trattare l'upload foto staff come aggiunta separata (richiede pipeline asset come il logo). Così evitiamo scope creep.

## 5. Rischio TASK 1
- **Basso-medio**: tutto additivo (colonne nullable, campi config opzionali, modello Flutter retrocompatibile). Nessun impatto su booking/auth.
- Attenzione a `config_version` (bump al salvataggio brand → cache-bust client, meccanismo già esistente).
- Le foto staff restano un gap dichiarato (non bloccante per "premium").

---

# TASK 2 — PUSH NOTIFICATION FOUNDATION (end-to-end)

## Cosa ESISTE GIÀ (riuso, niente duplicati)
- **Backend API devices**: `PUT /me/devices` (`{platform: ios|android, fcm_token, locale?}`, upsert su user+token) e `DELETE /me/devices` — `DeviceController` (Notifications). **Da riusare così com'è.**
- **Backend invio push**: `FcmPushChannel` completo (FCM HTTP v1 + OAuth service-account + pulizia token UNREGISTERED) + fallback email già attivo.
- **Flutter — metodi già pronti**: `me_repository.dart` ha **già** `registerDevice({fcmToken, platform})` e `unregisterDevice(token)` (commento: "called once FCM is integrated client-side"). → manca solo **chi produce il token**.

## Cosa MANCA
- **Flutter**: nessun `firebase_core`/`firebase_messaging`; nessun `PushNotificationService`; nessun wiring login/logout → device.
- **Nativo**: nessun `google-services.json` (Android) né `GoogleService-Info.plist` (iOS); nessun init Firebase.
- **Backend env**: `services.fcm.project_id` + `credentials_path` (service-account JSON) da configurare per inviare davvero.

## ⚠️ Prerequisito a tuo carico (come fu per AWS)
Serve un **progetto Firebase** con:
1. `google-services.json` (Android) + `GoogleService-Info.plist` (iOS) → li metto io nei posti giusti.
2. **APNs key** (iOS) caricata su Firebase (push iOS).
3. **Service-account JSON** per il backend (per l'invio FCM v1).
Senza questi, integro il codice ma il push non parte realmente.

## File coinvolti
- `pubspec.yaml` (+ `firebase_core`, `firebase_messaging` — versioni compatibili Dart 3.12/Flutter 3.44).
- `lib/core/notifications/push_notification_service.dart` (NUOVO).
- `lib/main.dart` (init Firebase + background handler top-level).
- `lib/core/session/session_controller.dart` (hook register/unregister) + `lib/app/providers.dart`.
- Android: `android/app/build.gradle.kts` + `android/build.gradle.kts` (plugin google-services). iOS: entitlements + `Info.plist` (background modes/APNs).
- Backend: solo `config/services.php` (chiavi fcm) — **nessun endpoint nuovo**.

## Flow (rispetta i vincoli di sicurezza)
```
app avviata → Firebase.init
        ↓
utente autenticato? ── NO → niente token (non si registra da guest)
        │ SI
   richiedi permessi → ottieni FCM token
        ↓
   meRepository.registerDevice(token, platform)   // PUT /me/devices (già esistente)
        ↓
   onTokenRefresh → re-register
   logout / deleteAccount → meRepository.unregisterDevice(token)  // DELETE /me/devices
```

## Sicurezza (verifiche richieste)
- **Token legato al tenant/user corretto**: `/me/devices` autenticato via JWT → `Device.user_id` = utente loggato (che ha il suo `tenant_id`); `FcmPushChannel` invia solo ai device di quell'utente. ✓ per costruzione.
- **Logout rimuove device**: `unregisterDevice(token)` in `logout()` **prima** di azzerare la sessione (token salvato dal service).
- **Cambio account non mantiene token**: unregister al logout + register fresco al nuovo login; il token FCM è per-installazione → l'associazione user cambia correttamente.

## Rischio TASK 2
- **Medio**: dipendenza nativa Firebase + build iOS (già avuto attriti Xcode 26/Sentry) → verificare `flutter analyze` e build iOS dopo l'aggiunta.
- **Bloccante esterno**: senza il progetto Firebase il push non è testabile end-to-end (resta il fallback email già funzionante).

---

# CONTATTI / SOCIAL / WHATSAPP — risposta alla verifica esplicita
`BrandProfile`/tenant **NON** supportano oggi: `whatsapp_number`, `instagram_url`, `facebook_url`, `website_url`, `email`. `phone` esiste su `Location`.
**Proposta minima** = le colonne nullable su `brand_profiles` del punto 4A + esposizione in `/app/config` (`contacts`/`social`) + editing dashboard. Tutto configurabile dal tenant, fallback = pulsante nascosto se `null`. Nessuna chat/CRM: solo deep-link esterni (`wa.me`, `instagram.com`, `facebook.com`, `tel:`, `mailto:`).

---

# TEST OBBLIGATORI (prima del "completato")
**Flutter**: `flutter analyze`; widget test `business_info_screen` (rende sezioni presenti, nasconde quelle null, tap apre i link via launcher mockato); unit test `PushNotificationService` (non registra da guest; register dopo login; unregister al logout).
**Backend**: test registrazione device (`PUT /me/devices` upsert), autorizzazione (guest → 401, user solo i propri device), **isolamento tenant** (un device non finisce a utenti di altri tenant); test estensione `/app/config` (contacts/social/hours presenti e null-safe). Riuso pattern test esistenti.
**Regressione**: `php artisan test` (i 104 restano verdi) + `flutter test`.

---

# ORDINE DI IMPLEMENTAZIONE PROPOSTO
1. **Backend TASK 1**: migration contatti/social → `BuildWhiteLabelConfig` (logo_url+contacts+social+opening_hours) → dashboard branding → test.
2. **Flutter TASK 1**: estendere `WhiteLabelConfig` → rebuild `business_info_screen` premium (con WhatsApp/social) → widget test.
3. **TASK 2**: pubspec firebase → `PushNotificationService` → wiring session → config nativa (richiede i file Firebase tuoi) → test.

# COSA SERVE DA TE PRIMA DELLA TASK 2
Il **progetto Firebase** (i 3 elementi del prerequisito). Per la TASK 1 non serve nulla: posso partire subito su tua conferma.

---

**Attendo la tua approvazione.** Posso iniziare dalla **TASK 1** (nessun prerequisito) mentre prepari Firebase per la TASK 2, oppure procedere come preferisci.
