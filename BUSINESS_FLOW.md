# BUSINESS_FLOW — Il flusso end-to-end, verificato sul codice

Ogni fase cita la classe/tabella/endpoint reale che la implementa. Nessun passaggio qui descritto è ipotetico.

```
1. CLIENTE (professionista) acquista il servizio
        ↓
2. CONTROL ROOM — l'operatore piattaforma crea il tenant
        ↓  TenantsController::store() → ProvisionTenant::execute()
3. TENANT — creato in un'unica transazione DB (sotto CurrentTenant::bypass()):
        · riga `tenants` (stato iniziale: onboarding)
        · `subscriptions` (piano scelto, stato active)
        · `brand_profiles` di default
        · `locations` + 7 righe `location_schedules` (orari settimanali)
        · catalogo di partenza (`service_categories`/`services`/`service_variants`) da preset di settore
        · primo `users` di tipo tenant_admin (MFA forzata)
        · invito (token, solo hash salvato)
        ↓  subito dopo: AllocateAppIdentifiers::execute()
        · `app_projects` — bundle_id/package_name univoco e immutabile (com.platform.t{shortcode})
        ↓
4. ASSETS — il titolare (o l'operatore per suo conto) carica logo/colori
        StoreBrandLogo (valida MIME/dimensione, checksum) → GenerateBrandAssets (GD)
        → 14 varianti generate (9 icone + 3 splash + 2 store asset), versionate con rollback
        ↓
5. GENERATE — Control Room → "Genera pacchetto"
        PrepareApp::execute() → GenerateAppPackage
        → manifest-latest.json scritto su disco (schema 2.0: identità, runtime/dart-define, branding, template)
        → AppProject.build_status → ready_to_build
        ↓
6. BUILD — Control Room → "Build" (Android)
        BuildService::request() → app_builds (status: queued) → coda
        → RunAppBuildJob → BuildDispatcher (driver: manual/local/github)
        → [driver local] flutter clean/pub get/analyze/test/build apk --release
        ↓
7. APK — prodotto, checksum sha256 calcolato
        salvato in storage/app/private/builds/{tenant_id}/{version}/app-release.apk
        app_builds.status → built
        ↓
8. INSTALLAZIONE — link beta (token 48 char, scadenza 7gg, limite download)
        BetaDownloadController::download() → streaming del file, download_count incrementato
        oppure pubblicazione store (CI, Firebase App Distribution/TestFlight/Play/App Store)
        ↓
9. UTENTE FINALE — installa l'app, si registra
        POST /api/v1/auth/register → email di verifica (codice 6 cifre) → email_verified_at
        ↓
10. PRENOTAZIONE
        GET /api/v1/availability (AvailabilityCalculator: orari, eccezioni, buffer, fuso orario, DST)
        → POST /api/v1/appointments → BookAppointment
          (lock DB sull'intervallo staff richiesto + unique index come rete di sicurezza,
           idempotency_key per replay sicuro, stato iniziale Requested o Confirmed
           a seconda di tenant->requiresBookingApproval())
        ↓
11. BACKEND — gestisce conferma/completamento/cancellazione/no-show
        ManageAgendaController (titolare/staff) via Dashboard o API manage/*
        Ogni transizione di stato → AppointmentEvent (audit trail interno all'appuntamento)
        → evento di dominio AppointmentBooked/AppointmentCancelled → Notifications
          (canale email o push FCM, fallback email se il push fallisce)
        ↓
12. DATABASE — un solo database condiviso, isolato a livello di riga
        (tenant_id + scope Eloquent globale — vedi REAL_PROJECT_STATE.md §Fase 8)
        ↓
13. ANALYTICS — 🟡 GAP NOTO: infrastruttura presente lato Flutter
        (AnalyticsService con eventi tipizzati: appOpen, login, bookingCreated, ecc.)
        ma MAI istanziata in app/providers.dart — nessun evento viene realmente
        raccolto oggi (PROJECT_FREEZE_STATE.md §3). Il flusso si ferma qui nella pratica.
        ↓
14. FEEDBACK — raccolto realmente
        POST /api/v1/me/feedback (dall'app, utente autenticato) → BetaFeedback
        → visibile in sola lettura in Control Room (ultimi 10, per app-project)
        → NESSUNA azione di gestione/moderazione del feedback esiste da Control Room oggi
```

## Punti del flusso con un gap verificato (non ipotesi)

| Fase | Gap | Riferimento |
|---|---|---|
| 6-7 Build | Firma release ricade silenziosamente su debug se le env keystore mancano; `versionCode`/`versionName` statici indipendenti dal tenant | `PROJECT_FREEZE_STATE.md` §8 |
| 13 Analytics | Nessun evento realmente raccolto (sink no-op, mai wired) | `PROJECT_FREEZE_STATE.md` §3 |
| 14 Feedback | Solo lettura da Control Room, nessuna gestione | `REAL_PROJECT_STATE.md` §Fase 6 |

Il resto del flusso (1-12) è verificato come pienamente funzionante e testato.
