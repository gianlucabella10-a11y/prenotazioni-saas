# APP PERSONALIZATION REPORT

> Modalità: **App Personalization Engine**. Implementazione **sopra** il motore white-label esistente
> (nessuna riscrittura, nessuna duplicazione). Ordine rispettato: **audit → implementazione → test → report**.
>
> - Data: 2026-07-21
> - Audit di partenza: [APP_PERSONALIZATION_AUDIT.md](APP_PERSONALIZATION_AUDIT.md)
> - Quality gate backend: **`php artisan test` → 244 test, 897 asserzioni, tutti verdi**
> - Flutter (`flutter analyze` / `flutter test`): da eseguire in **CI** (SDK assente in locale — codice + test scritti)

---

## 1. Cosa è stato implementato (per fase)

Ogni fase si innesta su strutture esistenti: il JSON `theme`/`content`/`notification` del `BrandProfile`,
il payload di `GET /app/config` (`BuildWhiteLabelConfig`), il `AppThemeBuilder` Flutter, la pipeline
`GenerateBrandAssets`. **Nessuna tabella parallela, nessun motore riscritto.**

### FASE 1 — Brand Identity (tema premium) 🟢
- **Dark Theme reale**: `theme.mode` (light/dark/system) → `AppThemeBuilder.buildDark` + `themeMode`; `MaterialApp` ora ha `darkTheme`.
- **Palette dark automatica e leggibile**: nuovo `DeriveDarkPalette` (riusa `ContrastValidator`) schiarisce i colori del brand finché non superano il contrasto minimo sullo sfondo scuro. Curated default in `config/branding.php`.
- **Token prima inerti, ora applicati**: `accent`→`ColorScheme.tertiary`; `success`/`warning` esposti via `BrandColors` ThemeExtension; `elevation.level`→ombra di card/app bar; `background`/`radius` editabili.
- **Validazione contrasto** estesa alla palette dark (light+dark).
- File: `config/branding.php`, `DeriveDarkPalette.php`, `ManageBrandController.php`, `BrandingController.php`, `app_theme_builder.dart`, `white_label_config.dart`, `app.dart`.

### FASE 2 — App Identity 🟢/documentata
- **Densità visiva** dell'intera app: `theme.density` (comfortable/standard/compact) → `ThemeData.visualDensity`. Dial reale, whole-app.
- Top bar / card / button style: già coperti dal tema (Fase 1); header identity dal `layout_variant` (hero/gallery) già cablato.
- **Onestà tecnica**: bottom-nav/FAB/icon-style **non** aggiunti come config → l'app è single-Scaffold senza bottom-nav/FAB: sarebbero stati *codice morto*. Documentato, non simulato.

### FASE 3 — Business Identity 🟢
- Aggiunti **TikTok**, **Cookie policy URL**, **P.IVA**, **coordinate GPS** (sede). Migrazione additiva.
- Le coordinate rendono preciso il link Maps in `BuildWhiteLabelConfig`.
- Renderizzati davvero: TikTok nella scheda social, cookie nel footer legale (`profile_screen`), P.IVA nel footer della scheda attività (`business_info_screen`).

### FASE 4 — Booking Identity 🟢
- Nuova UI **Regole di prenotazione** (`AvailabilityController::updateBookingPolicy`): finestra, slot, preavviso, cancellazione — campi già presenti in `locations` ma **prima non modificabili**.
- **Max prenotazioni attive per cliente** (`settings.max_active_bookings_per_customer`) **applicato** in `BookAppointment` (nuovo codice `booking_limit_reached`) — nessuna config morta.
- Ogni cambio invalida la cache slot e bumpa `config_version`.

### FASE 5 — Customer Experience 🟢
- Nuovo JSON `content` (una colonna, estendibile): **messaggio benvenuto, titolo home, descrizione, testo CTA, empty state, immagine hero**.
- I default riproducono le copy attuali → **zero regressione**; gli override cambiano l'app. Renderizzati in `home_screen` (CTA/titolo/saluto/empty) e nell'header hero (immagine).

### FASE 6 — Brand Assets 🟢
- **Favicon** generata dal logo (riusa la pipeline GD `render()` di `GenerateBrandAssets`).
- Logo PNG/JPG/WebP + icone + splash + store assets: già presenti, versionati con rollback.
- **Immagini Home**: via `content.hero_image_url` (Fase 5). **SVG**: fuori scope by-design (GD non rasterizza) — vincolo documentato, non config morta.

### FASE 7 — Push Notifications 🟢/sicura
- Nuovo `FcmMessageBuilder` (puro, testabile) aggiunge il blocco `android`: **colore accent** (default = primary del brand) + **priorità** (heads-up). `FcmPushChannel` risolve lo stile dal brand del tenant.
- Config `notification` brand-level (colore/priorità) editabile in dashboard.
- **Scelta di sicurezza**: niente channel_id/icona/suono custom via runtime (un channel_id sconosciuto può **sopprimere** la notifica su Android O+, e icone/suoni sono asset di build). Documentato.

### FASE 8 — Control Room / Personalizzazione 🟢
- Pagina Personalizzazione **riorganizzata nelle categorie ordinate** del brief con nav ad ancore: **Brand · Tema · Booking↗ · Cliente · Notifiche · Contatti · Assets**.
- **Assets self-service**: upload logo dalla dashboard (`BrandingController::uploadLogo`, riusa `StoreBrandLogo` + `GenerateBrandAssets`) → rigenera icone/splash/favicon.
- Banner "si applica senza aggiornare l'app" + tabella impatto (Fase 9).

### FASE 9 — Smart Build (matrice) 🟢
- Matrice completa in `config/personalization_matrix.php` + servizio `BuildImpactMatrix` (`classify()`, `requiresBuild()`), **mostrata al tenant** nella pagina Personalizzazione (cosa è subito / cosa richiede build).

### FASE 10 — Quality Gate 🟢
- `php artisan test`: **244/244 verdi** (isolamento tenant, build pipeline, manifest, assets, booking inclusi).
- Nuovi test: `DeriveDarkPaletteTest`, `FcmMessageBuilderTest`, `BuildImpactMatrixTest` (unit) + estensioni feature su `WhiteLabelConfigTest`, `BookingFlowTest`, `DashboardOperationsTest`, `GenerateBrandAssetsTest`.
- Flutter: test aggiunti (`app_theme_builder_test`, `white_label_config_test`) — verifica `flutter analyze/test` in CI.

---

## 2. Cosa è RUNTIME (nessuna build)

Tutto ciò che viaggia in `GET /app/config` (bump `config_version` → ETag → prossima apertura app):

| Area | Dettaglio |
|------|-----------|
| Brand | nome app, slogan, colori (primary/secondary/**accent**/background/success/warning/error) |
| Tema | **chiaro/scuro/auto**, stile forme, livello ombra, densità |
| Cliente | benvenuto, titolo home, descrizione, testo CTA, empty state, immagine hero (URL) |
| Contatti/Social | telefono, email, WhatsApp, Instagram, Facebook, **TikTok**, sito, Maps, indirizzo, **coordinate** |
| Legale | privacy, termini, **cookie**, assistenza, **P.IVA** |
| Booking | finestra, slot, preavviso, cancellazione, **max prenotazioni/cliente**, conferma auto, reminder |
| Notifiche | **colore** e **priorità** push |
| Logo | logo mostrato **in-app** |

## 3. Cosa richiede una BUILD

| Area | Perché |
|------|--------|
| Icona nativa (launcher) | asset nativo iOS/Android |
| Splash nativa · Favicon | asset di build |
| Font `.ttf` | va impacchettato nel binario |
| Bundle ID / package name | identità store, **immutabile** |
| Nome nello store · API base URL | metadati/dart-define di build |
| Template con asset nativi · core version | release train (`built_core_version`) |

> Sorgente di verità: `config/personalization_matrix.php` (servizio `BuildImpactMatrix`), mostrata in dashboard.

---

## 4. Quanto ogni app è personalizzabile

**Molto alta a runtime.** Un tenant, senza toccare codice né ricompilare, definisce:
identità (nome/logo/colori), **tema chiaro e scuro** su misura, forme/densità/ombre, **testi e immagini**
della home, contatti/social completi, dati legali, **regole di prenotazione**, e **stile delle notifiche**.
Con una build entrano solo icona/splash nativi, font, e identità store.

In pratica: l'app non è "logo + colori", ma **identità, contenuti, comportamento e notifiche** — il cliente
apre l'app e vede *la sua*.

## 5. Quanto il sistema è realmente White Label

**Pienamente white-label a livello di prodotto.** Lo stesso binario Flutter renderizza qualsiasi tenant a
partire da `GET /app/config`: **nessun `if cliente == X`**, nessun codice tenant-specifico. La
personalizzazione è **dati** (JSON `theme`/`content`/`notification` + `locations`), non codice. L'unica parte
non-runtime è l'identità **nativa** di store/icona/splash — inevitabile per qualsiasi app nativa — gestita
dalla App Factory esistente. Isolamento tenant garantito (`BelongsToTenant`) e verificato dai test.

---

## 6. Vincoli rispettati

- ✅ **Motore non riscritto**: estese solo le strutture esistenti (`theme` JSON, `BuildWhiteLabelConfig`, `AppThemeBuilder`, `GenerateBrandAssets`, `app_templates`).
- ✅ **Nessuna duplicazione**: nuove chiavi dentro il payload/colonne esistenti; una colonna JSON per area (`content`, `notification`).
- ✅ **Nessun codice morto**: ogni config ha effetto visibile; ciò che non renderizza (bottom-nav/FAB/SVG/channel custom) **non** è stato aggiunto ma **documentato**.
- ✅ **Estendibile**: `theme.colors.*`, `content.*`, `notification.*`, `personalization_matrix.php` aperti a nuove voci.
- ✅ **Niente decine di impostazioni inutili**: solo dial ad alto impatto ("questa è la MIA app").

## 7. File principali (creati / modificati)

**Creati (backend):** `DeriveDarkPalette.php`, `FcmMessageBuilder.php`, `BuildImpactMatrix.php`,
`config/personalization_matrix.php`, 3 migrazioni (`business_identity`, `content`, `notification`),
3 test unit (`DeriveDarkPalette`, `FcmMessageBuilder`, `BuildImpactMatrix`).

**Modificati (backend):** `BuildWhiteLabelConfig`, `GenerateBrandAssets`, `BrandProfile`, `ManageBrandController`,
`BrandingController`, `AvailabilityController`, `FcmPushChannel`, `BookAppointment`, `config/branding.php`,
viste `dashboard/branding` e `dashboard/availability`, `routes/web.php`, + relativi test feature.

**Modificati (Flutter):** `white_label_config.dart`, `app_theme_builder.dart`, `app.dart`, `home_screen.dart`,
`business_info_screen.dart`, `profile_screen.dart`, + test unit.

---

## 8. Follow-up consigliati (fuori da questo scope, non bloccanti)

1. **Font**: impacchettare i `.ttf` (Inter/Poppins/Oswald) per attivare la scelta font (oggi meccanismo pronto, effetto solo con i font bundle → build).
2. **Flutter CI**: eseguire `flutter analyze` + `flutter test` (SDK assente in locale).
3. **Immagini di contenuto avanzate** (background/placeholder upload): oggi hero via URL; estendibile con lo stesso pattern asset se richiesto.
4. **Template su DB con editor** (già previsto come Fase 3 in `app_templates.php`).
