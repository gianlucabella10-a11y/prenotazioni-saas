# APP PERSONALIZATION AUDIT — FASE 0

> Modalità: **App Personalization Engine**. Audit del motore white-label **reale ed esistente**.
> Nessuna riga di codice è stata modificata: questo documento è la fotografia dello stato attuale.
> Regola: **prima audit, poi implementazione**. Qui c'è solo l'audit.
>
> - Data: 2026-07-21
> - Branch: `docs/production-readiness-audit`
> - Metodo: lettura diretta del codice (backend Laravel + mobile Flutter), migrazioni, config, route, test.

---

## Legenda

| Stato | Significato |
|-------|-------------|
| 🟢 **GREEN** | Esiste, funziona, è cablato end-to-end (DB → config runtime → Flutter) e testato. Riutilizzabile così com'è. |
| 🟡 **YELLOW** | Il meccanismo esiste ma è parziale: manca la UI di editing, o è cablato solo a metà, o è presente il dato ma non l'effetto visivo. Da **estendere**, non da creare. |
| 🔴 **RED** | Assente. Va progettato e implementato da zero (rispettando l'architettura esistente). |

---

## 1. Mappa del motore esistente (cosa NON va riscritto)

Il prodotto ha **già** un motore white-label runtime funzionante. Il cuore è:

```
[Dashboard cliente]  /personalizzazione        → BrandingController (nome, colori, contatti, legal)
[Control Room]       /clienti/{uuid}/brand      → TenantBrandController (quick setup + logo)
        │
        ▼
brand_profiles (1 per tenant) + brand_assets  ── config_version++ ad ogni modifica
        │
        ▼
GET /app/config → BuildWhiteLabelConfig  ── ETag = "cfg-{uuid}-{config_version}"  → 304 se invariato
        │
        ▼
[Flutter] WhiteLabelConfig.fromJson → BrandTheme → AppThemeBuilder.build() → ThemeData
        │
        ▼
Ogni widget consuma ThemeData/ColorScheme → nessun codice tenant-specifico
```

E, in parallelo, un motore di **build** (App Factory) per ciò che il runtime non può cambiare:

```
AppProject (1 per tenant, identità immutabile) → GenerateAppPackage → manifest 2.0 (JSON)
        │
        ▼
BuildDispatcher (manual | github | local) → AppBuild → AppVersion → BetaDownloadToken
```

**Verdetto architetturale: 🟢 il motore esiste, è pulito, modulare e testato (48 file di test, di cui ~26 su branding/app-factory). Va ESTESO, mai riscritto.**

---

## 2. Verifica dei 9 componenti richiesti

| # | Componente | File chiave | Stato | Nota sintetica |
|---|-----------|-------------|-------|----------------|
| 1 | **White Label Engine** | `BuildWhiteLabelConfig.php`, `AppConfigController.php` | 🟢 | Config runtime completo con ETag/304, gestione stato tenant (suspended/terminated → schermata cortesia). |
| 2 | **Theme Engine** | `app_theme_builder.dart`, `white_label_config.dart` | 🟡 | Colori/radius/typography scale mappati in Material 3. **Solo light**, `success/warning` non applicati allo scheme, nessun accent dedicato. |
| 3 | **Tenant Settings** | `BrandProfile.php`, `Tenant.php`, `TenantFeature.php` | 🟢 | Brand 1:1 col tenant, `features` (feature flags), `locale`, isolamento via `BelongsToTenant`. |
| 4 | **Manifest** | `GenerateAppPackage.php` | 🟢 | "Build Manifest 2.0": app_identity, runtime/dart_define, branding, template, assets, metadata. Sorgente unica per la build. |
| 5 | **Flutter Theme** | `app_theme_builder.dart` | 🟡 | Vedi Theme Engine. Font family cablata (switch) ma **.ttf non impacchettati** → default piattaforma. |
| 6 | **Asset Generator** | `GenerateBrandAssets.php` | 🟢 | Da logo master genera icone Android/iOS + splash + store assets con GD. Versionato, rollback, storico. **Solo raster (no SVG)**. |
| 7 | **Config Builder** | `GenerateAppPackage.php`, `BuildWhiteLabelConfig.php` | 🟢 | Due builder: uno runtime (config app), uno statico (manifest build). |
| 8 | **Build Pipeline** | `BuildDispatcher.php`, `Dispatchers/*`, `BuildService.php` | 🟢 | 3 driver (manual/github/local), versioni, staleness `built_core_version`, ricostruzione flotta, distribuzione beta con token revocabili. |
| 9 | **App Config** | `AppConfigController.php` + `white_label_repository.dart` | 🟢 | Endpoint `GET /app/config`, cache client via ETag, fallback tema. |

---

## 3. Gap analysis per FASE (il cuore dell'audit)

Ogni voce richiesta dal brief è classificata. **GREEN = già presente**, non va rifatto.

### FASE 1 — Brand Identity

| Voce richiesta | Stato | Dove / perché |
|----------------|-------|---------------|
| Nome App | 🟢 | `brand_profiles.app_name` (max 30), editabile in dashboard + control room. |
| Sottotitolo (tagline) | 🟢 | `brand_profiles.tagline` (max 80), in config runtime. |
| Nome Azienda | 🟡 | Esiste `store_name` su `app_projects` (nome store) e `Location.name`. Non c'è un campo "ragione sociale" brand-level dedicato distinto da app_name. |
| Logo | 🟢 | `StoreBrandLogo` + `brand_assets` (kind=logo), upload PNG/JPG ≥256px. |
| Splash Logo | 🟢 | Generato da `GenerateBrandAssets` (splash_1x/2x/3x). |
| Icona | 🟢 | Generata (android_*/ios_* fino a 1024). |
| Primary Color | 🟢 | `primary_color` + `theme.colors.primary`, con validazione contrasto WCAG. |
| Secondary Color | 🟢 | `secondary_color` + `theme.colors.secondary`. |
| Accent Color | 🟡 | Non esiste token `accent` dedicato: `secondary` fa da accento. Estendibile in `theme.colors`. |
| Background Color | 🟡 | `theme.colors.background` esiste ed è usato (`scaffoldBackgroundColor`) ma **non editabile da UI** (solo primary/secondary lo sono). |
| Success / Warning / Error Color | 🟡 | Presenti in `theme.colors` e nei default, ma **solo `error` è mappato** nel `ColorScheme`; success/warning non hanno effetto UI. Non editabili. |
| Dark Theme | 🔴 | **Assente.** `AppThemeBuilder` hardcoda `Brightness.light`. Nessun `darkTheme`/`ThemeMode`. |
| Light Theme | 🟢 | È l'unico tema attuale (di fatto sempre attivo). |
| Rounded / Flat Style | 🟡 | Controllabile indirettamente via `radius` (small/medium/large) nel tema, ma non come "stile" esplicito e non editabile da UI. |
| Shadow Level | 🔴 | Elevation hardcodata (card `elevation: 1`). Nessun token. |
| Border Radius | 🟡 | `theme.radius.{small,medium,large}` esiste ed è applicato, ma non editabile da UI. |
| Font Family | 🟡 | `font_style` su `app_projects` + switch in `AppThemeBuilder` (oswald/poppins/inter), ma **.ttf non impacchettati** → nessun effetto reale ancora. |

**Sintesi Fase 1:** nucleo colori/nome/logo/asset 🟢. Mancano soprattutto **Dark Theme (🔴)**, applicazione di success/warning/accent/shadow (🟡), ed **esposizione UI** di background/radius/font.

### FASE 2 — App Identity

| Voce | Stato | Nota |
|------|-------|------|
| Icon Style | 🔴 | Nessun concetto di stile icone runtime. |
| Bottom Navigation Style | 🟡 | Router/nav esiste (`router.dart`), ma nessuna variante configurabile. |
| Top Bar Style | 🟡 | `appBarTheme` è temizzato (colore/centerTitle), ma non ci sono varianti di stile scelte dal tenant. |
| Card Style | 🟡 | `cardTheme` temizzato (radius/elevation), non variante scelta. |
| Button Style | 🟡 | `filledButtonTheme`/`outlinedButtonTheme` temizzati, non variante scelta. |
| FAB Style | 🔴 | Nessun tema FAB dedicato. |
| Animation Level | 🔴 | Assente. |
| Loading Style | 🟡 | Esiste uno splash + stati loading, ma non configurabile. |
| Empty State Style | 🟡 | Esistono empty state (`error_messages.dart`), non configurabili. |

**Il meccanismo "layout variant" esiste già** (`layout_variant`: standard/hero_dark/gallery su `app_templates.php`, consumato da `home_screen.dart` con `_isHeroLayout`). È la leva giusta da estendere per la App Identity, invece di aggiungere 9 flag separati. → prevalentemente 🟡, alcuni 🔴.

### FASE 3 — Business Identity

| Voce | Stato | Dove |
|------|-------|------|
| Telefono | 🟢 | `Location.phone` (in config `contacts.phone`). |
| Email | 🟢 | `brand_profiles.contact_email`. |
| Whatsapp | 🟢 | `whatsapp_number` + `whatsapp_message` → deep link `wa.me`. |
| Instagram | 🟢 | `instagram_url`. |
| Facebook | 🟢 | `facebook_url`. |
| TikTok | 🔴 | Non presente (facile da aggiungere: stesso pattern social). |
| Sito Web | 🟢 | `website_url`. |
| Google Maps | 🟢 | `maps_url` o derivato dall'indirizzo. |
| Indirizzo | 🟢 | `Location.address`. |
| Coordinate | 🔴 | Non presenti (solo indirizzo testuale). |
| P.IVA | 🔴 | Non presente. |
| Privacy Policy URL | 🟢 | `privacy_policy_url`. |
| Cookie URL | 🟡 | Non c'è campo dedicato; `terms_url`/`privacy_policy_url` presenti. |
| Termini di Servizio | 🟢 | `terms_url`. |

**Sintesi Fase 3:** quasi tutto 🟢. Mancano solo **TikTok, Coordinate, P.IVA, Cookie URL** (🔴/🟡) — estensioni banali del `BrandProfile` esistente.

### FASE 4 — Booking Identity

| Voce | Stato | Dove |
|------|-------|------|
| Durata prenotazioni | 🟢 | `services.duration` (catalogo). |
| Slot / Intervalli | 🟢 | `locations.slot_granularity_minutes`. |
| Buffer | 🟢 | `services.buffer_after_minutes` (editabile in `ServicesController`). |
| Pausa pranzo | 🟢 | Modellata come fasce orarie (morning/afternoon) in `AvailabilityController`. |
| Giorni chiusi | 🟢 | Orari sede/staff + `ScheduleException` (chiusure). |
| Festività | 🟢 | `ScheduleException` (ferie/chiusure con range date). |
| Orari | 🟢 | `LocationSchedule` + orari staff (motore reale). Editabili in dashboard. |
| Numero massimo prenotazioni | 🟡 | Quota `max_services`/`max_staff` esiste; non c'è "max prenotazioni per cliente/giorno". |
| Cancellazione cliente | 🟢 | `cancellation_cutoff_minutes`. |
| Tempo minimo cancellazione | 🟢 | Stesso campo sopra. |
| Conferma automatica | 🟢 | `booking.confirmation_mode` (`auto_confirm`/`request_approve`) via tenant settings. |
| Reminder | 🟢 | `reminder_offsets_hours` + `ScheduleAppointmentNotifications`. |
| Prenotazione multipla | 🔴 | Non presente come opzione configurabile. |
| **Editing UI di window/slot/min_notice/cancellation** | 🟡 | I campi **esistono in `locations`** e viaggiano nel config, ma **nessun controller dashboard li modifica** (impostati al provisioning). Manca la UI. |

**Sintesi Fase 4:** motore prenotazioni molto solido 🟢. Il gap è di **esposizione UI** di alcuni parametri già presenti nel modello dati (🟡), più poche opzioni mancanti (🔴).

### FASE 5 — Customer Experience

| Voce | Stato | Nota |
|------|-------|------|
| Schermata iniziale / Titolo Home / Descrizione | 🟡 | Home renderizza `app_name`/`tagline` + variante layout; non ci sono testi Home dedicati e configurabili. |
| Messaggio benvenuto | 🔴 | Assente nel config. |
| Banner / Immagine Hero | 🟡 | Layout "hero" esiste (`_isHeroLayout`) ma usa il tema, **non un'immagine hero caricabile**. |
| Call To Action + Colori CTA + Testi pulsanti | 🔴 | Nessun testo/colore CTA configurabile (i pulsanti usano il tema). |
| Placeholder / Messaggi vuoti / Messaggi errore | 🟡 | Esistono lato Flutter (`error_messages.dart`) ma **hardcoded**, non da config. |

**Sintesi Fase 5:** è l'area **più scoperta** insieme al Dark Theme. Prevalentemente 🔴/🟡. Richiede nuove chiavi nel payload `/app/config` (es. `content.home_title`, `content.welcome`, `content.cta`, `content.hero_image_url`).

### FASE 6 — Brand Assets

| Voce | Stato | Dove |
|------|-------|------|
| Logo PNG | 🟢 | Upload + storage. |
| Logo SVG | 🔴 | Esplicitamente non supportato (GD non rasterizza SVG → skip graceful). |
| Splash | 🟢 | Generato. |
| Favicon | 🟡 | C'è `web/icons` nel progetto Flutter ma non generata dal brand. |
| Icone | 🟢 | Generate (tutte le densità). |
| Immagini Home / Background / Placeholder immagini | 🔴 | Nessun upload di immagini di contenuto (solo logo master + derivati). |

**Sintesi Fase 6:** pipeline asset 🟢 per logo→icone→splash→store. Mancano **SVG, favicon brandizzata, immagini di contenuto** (🔴/🟡).

### FASE 7 — Push Notifications

| Voce | Stato | Dove |
|------|-------|------|
| Invio push | 🟢 | `FcmPushChannel` (FCM HTTP v1), `SendNotificationJob`, `DeviceController`. |
| Messaggi automatici (reminder) | 🟢 | `ScheduleAppointmentNotifications` + `TemplateRenderer`. |
| Titolo | 🟡 | Per-messaggio (`title`/`body`), non un titolo brand configurabile. |
| Icona / Colore | 🔴 | Non inviati nel payload FCM (nessun `android.notification.icon`/`color`). |
| Canali / Suono / Priorità | 🔴 | Non configurabili per-tenant. |

**Sintesi Fase 7:** infrastruttura push 🟢, ma **personalizzazione visiva della notifica** (icona/colore/canale/suono/priorità) è 🔴. Estendibile nel payload di `FcmPushChannel`.

### FASE 8 — Control Room / Personalizzazione

| Aspetto | Stato | Nota |
|---------|-------|------|
| Sezione "Personalizzazione" cliente | 🟡 | Esiste `/personalizzazione` (dashboard) con **Brand + Contatti + Legal**. Ma **non** è organizzata nelle 7 categorie richieste (Brand, Tema, Booking, Cliente, Notifiche, Contatti, Assets). |
| Quick setup lato Control Room | 🟢 | `TenantBrandController` (nome, colori, logo per tenant). |
| Salvataggio senza ricompilare | 🟢 | `config_version++` + `registry->forget()` → l'app si aggiorna alla prossima apertura, **senza build**. È già la prova vivente del white-label. |
| Categorie ordinate Tema/Booking/Notifiche/Assets in un'unica UI | 🟡 | Frammentate su più schermate (personalizzazione, disponibilità, app-factory). Da consolidare, non da creare. |

### FASE 9 — Smart Build (matrice runtime vs build)

| Aspetto | Stato | Nota |
|---------|-------|------|
| Modifiche runtime (no rebuild) | 🟢 | Nome, tagline, colori editabili, legal, contatti, social → `config_version++`, zero build. |
| Modifiche statiche (richiedono build) | 🟢 | bundle_id/package_name (immutabili), icone/splash native, font .ttf, template code → `GenerateAppPackage` + `BuildDispatcher`. |
| Staleness core | 🟢 | `built_core_version` < `core_version` → app "stale" → rebuild flotta. |
| **Matrice esplicita documentata** | 🔴 | La distinzione **esiste nel codice** ma non c'è una matrice/documento unico "campo → runtime|build". Da formalizzare (è output richiesto). |

---

## 4. Tabelle consolidate GREEN / YELLOW / RED

### 🟢 GREEN — già fatto, riutilizzare così com'è
- White Label Engine runtime (`/app/config` + ETag/304 + config_version).
- Isolamento tenant (`BelongsToTenant`, `CurrentTenant::bypass`).
- Brand core: nome app, tagline, primary/secondary color con **validazione contrasto WCAG**.
- Asset Factory: logo → icone (tutte le densità) + splash + store assets, **versionati con rollback**.
- Build Manifest 2.0 + Build Pipeline (manual/github/local) + versioni + beta con token revocabili.
- Business Identity quasi completa (telefono, email, whatsapp, IG, FB, web, maps, indirizzo, privacy, termini).
- Booking engine: durata, slot, buffer, orari, chiusure/festività, cancellazione, conferma auto, reminder.
- Push infrastructure (FCM v1) + reminder automatici.
- Dashboard `/personalizzazione` (Brand + Contatti + Legal) con salvataggio **senza rebuild**.

### 🟡 YELLOW — c'è il meccanismo, va esteso/esposto
- Theme: `background`, `radius`, `accent`, `success`/`warning` presenti nel dato ma **non applicati/non editabili**.
- Font family: switch cablato ma **.ttf non impacchettati**.
- App Identity (top bar/card/button/nav): temizzati ma **senza varianti scelte dal tenant** (leva = `layout_variant`).
- Booking: window/slot/min_notice/cancellation nel modello ma **senza UI di editing**.
- Customer Experience: layout hero e messaggi esistono ma **hardcoded / non da config**.
- Assets: favicon non brandizzata.
- Push: titolo per-messaggio ma non brand-level.
- Control Room: personalizzazione **frammentata**, non nelle 7 categorie ordinate.

### 🔴 RED — assente, da implementare (sopra l'architettura esistente)
- **Dark Theme / ThemeMode** (il gap più visibile).
- Shadow Level come token; Icon/FAB/Animation/Loading style configurabili.
- Customer Experience: welcome message, home title/description, hero **image**, CTA (testi+colori), empty/placeholder da config.
- Assets di contenuto: immagini Home, background, placeholder; supporto SVG; favicon generata.
- Push: icona/colore/canale/suono/priorità per-tenant.
- Business: TikTok, coordinate GPS, P.IVA, Cookie URL dedicato.
- Booking: prenotazione multipla, max prenotazioni per cliente.
- **Matrice Smart Build** formalizzata (documento).

---

## 5. Quality Gate (FASE 10) — prontezza toolchain

| Comando richiesto | Eseguibile localmente? | Nota |
|-------------------|------------------------|------|
| `php artisan test` | 🟢 Sì | PHP 8.4.22 in `~/.local/php-toolchain/bin/php`, PHPUnit in `vendor/bin`, `artisan` presente. 48 file di test. |
| `flutter analyze` | 🔴 No | **Flutter assente** in questo ambiente. Da eseguire su macchina con SDK / CI. |
| `flutter test` | 🔴 No | Idem. Esistono test Flutter in `apps/client_app/test/{unit,widget,e2e}`. |

> Conseguenza operativa: la parte **backend** (config runtime, manifest, isolamento, asset) è verificabile qui. La parte **Flutter** (tema, dark mode, rendering) va verificata in CI o su macchina con Flutter.

---

## 6. Raccomandazione di priorità (per la fase di implementazione)

Rispettando il vincolo *"non aggiungere decine di impostazioni inutili — l'obiettivo è che il cliente dica «questa è la MIA app»"*, l'impatto percepito più alto con il minor rischio sul motore:

1. **Dark Theme** (🔴 → il salto qualitativo più visibile). Estende `BrandTheme` + `AppThemeBuilder` con `ThemeMode`, senza toccare l'API.
2. **Applicare i token già presenti** (🟡): success/warning/accent/background/radius nello scheme + esporli nella UI dashboard. Costo minimo, alto ritorno.
3. **Customer Experience content** (🔴): welcome/home title/CTA/hero image nel payload `/app/config` — è ciò che fa dire "è la mia app".
4. **Consolidare la Control Room "Personalizzazione"** nelle 7 categorie (🟡): riorganizzazione UI, non nuovo motore.
5. **Matrice Smart Build** (🔴 doc): formalizzare `campo → runtime|build`.
6. Estensioni minori Business/Push/Assets (🟡/🔴) a seguire.

---

## 7. Vincoli confermati (rispettati in fase 2)

- ✅ Il motore **non va riscritto**: tutto quanto sopra si innesta su `BrandProfile`/`theme` JSON, `BuildWhiteLabelConfig`, `AppThemeBuilder`, `app_templates.php`.
- ✅ **Nessuna duplicazione**: le nuove chiavi vivono nel `theme`/payload esistente, non in tabelle parallele.
- ✅ **Estendibilità**: `theme.colors.*` e `sections`/`layout_variant` sono già aperti all'estensione.
- ✅ **No codice morto**: ogni nuova config deve avere effetto visibile in Flutter (altrimenti è YELLOW inutile).

---

**FINE FASE 0.** Prossimo passo: implementazione — **solo dopo conferma della direzione e delle priorità** (§6).
