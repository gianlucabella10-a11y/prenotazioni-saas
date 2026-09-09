# CURRENT CUSTOMIZATION STATE (FASE 0)

> Fotografia **reale e verificata nel codice** di ciò che oggi è personalizzabile.
> Fonti lette: `config/branding.php`, `BuildWhiteLabelConfig.php` (payload `/app/config`),
> `app_theme_builder.dart` + `white_label_config.dart` (Flutter), `GenerateBrandAssets.php` (asset),
> `GenerateAppPackage.php` (manifest), `config/app_templates.php`, Control Room (`TenantBrandController`,
> `AppProjectController`), Dashboard (`BrandingController`).
> Nessun file modificato. Diviso in **GIÀ PRESENTE / PARZIALMENTE PRESENTE / ASSENTE**.

---

## Sintesi in una riga

Il motore personalizza **colori (light+dark), forma, densità, testi, contatti, asset raster e scelta di 5 template**
a runtime senza rebuild. **Manca tutto ciò che dà "personalità" percepita**: font reali, immagini/illustrazioni,
motion, iconografia, voce/terminologia, struttura schermate dinamica. Oggi due app di due clienti diversi hanno
**lo stesso font, la stessa struttura, le stesse animazioni, le stesse icone** — cambiano colore e testo.

---

## 🟢 GIÀ PRESENTE (verificato, funzionante a runtime)

### Brand & Identità
- **Nome app** (`brand_profiles.app_name`, max 30) — in-app + manifest.
- **Slogan/tagline** (`tagline`, max 80).
- **Logo** (upload PNG/JPG/WebP ≥256px, `brand_assets` kind=logo) → `logo_url` nel config.

### Theme (design tokens)
- **Modalità**: `light` / `dark` / `system` (`theme.mode`) → `AppThemeBuilder` costruisce `theme` + `darkTheme` + `themeMode`.
- **Palette completa light + dark**: `primary, on_primary, secondary, on_secondary, accent, on_accent, surface, on_surface, background, success, warning, error` (+ `dark.colors` speculare).
- **Dark palette auto-derivata** con contrasto garantito (`DeriveDarkPalette` + `ContrastValidator`).
- **Accent** → `ColorScheme.tertiary`; **success/warning** → `BrandColors` ThemeExtension (usabili dai widget).
- **Forma**: `radius.{small,medium,large}`; **ombra**: `elevation.level` (0-4); **densità**: `density` (comfortable/standard/compact → `visualDensity`).
- **Scala tipografica**: `typography.scale` (0.8-1.4).
- **Validazione contrasto WCAG** su light e dark (gate anti-palette illeggibile).

### Contenuti (Customer Experience)
- `content`: `welcome_message, home_title, home_subtitle, primary_cta_label, empty_appointments, hero_image_url` (default = copy attuali).

### Business & Contatti
- `contacts` (telefono/email/sito), `social` (Instagram, Facebook, **TikTok**, Maps, WhatsApp con messaggio precompilato), `legal` (privacy, termini, **cookie**, supporto), `business` (**P.IVA**), coordinate GPS sede.

### Notifiche
- `notification`: **colore accent** (default = primary) + **priorità** (high/normal) nel payload FCM.

### Asset generati (Asset Factory, GD)
- **Icone** Android (mdpi→xxxhdpi) + iOS (60/120/180/1024).
- **Splash** (1x/2x/3x, logo su sfondo brand).
- **Store**: feature graphic 1024×500, screenshot placeholder.
- **Favicon** 16/32 (web/PWA).
- Versionati con **storico e rollback**.

### Template (skin curate)
- **5 template** in `config/app_templates.php`: `default` (Standard), `barber_dark` (Barber Dark Premium), `beauty_visual` (Beauty Visual), `medical_clean` (Medical Clean), `restaurant_visual` (Restaurant Visual).
- Ogni template porta: `theme` (colori/raggi/tipo) + `font_style` + `layout_variant` + `sections`. Selezionabile dalla Control Room; bump `config_version` → l'app riprende il nuovo skin senza rebuild.

### Manifest di build (App Factory 2.0)
- `app_identity` (name/slug/bundle_id/package_name/store_name), `runtime` (dart_define: ENV/API_BASE_URL/TENANT_KEY/APP_NAME/TEMPLATE), `branding` (logo/icone/splash/colori), `template` (code/layout/font/sections), `assets`, `metadata`.

### Control Room / Dashboard
- **Control Room** (founder): quick setup nome+colori+logo (`TenantBrandController`), cambio template + genera manifest + build + versioni + beta (`AppProjectController`).
- **Dashboard tenant**: `/personalizzazione` in 7 categorie (Brand/Tema/Booking/Cliente/Notifiche/Contatti/Assets) + upload logo self-service.
- **Salvataggio senza rebuild** (config_version/ETag) + **Smart Build Matrix** (`BuildImpactMatrix`) che mostra cosa è runtime vs build.

---

## 🟡 PARZIALMENTE PRESENTE (meccanismo cablato, effetto reale incompleto)

- **Font family** 🟡 — `font_style` (oswald/poppins/inter) è nel config, nel template e nello switch di `AppThemeBuilder`, **MA i file `.ttf` non sono impacchettati** → il client cade sul font di sistema (Roboto/SF). *Oggi tutti i tenant hanno lo stesso font.* **È il gap #1 della "personalità".**
- **Layout / struttura schermate** 🟡 — `layout_variant` e `sections` viaggiano nel config, ma il client renderizza solo **un toggle hero-vs-standard** (`home_screen._isHeroLayout` = hero_dark|gallery). La lista `sections` è "data-ready" ma **non compone dinamicamente** le schermate: la struttura è sostanzialmente identica per tutti.
- **Immagine hero** 🟡 — supportata come **singolo URL** (`content.hero_image_url`), nessun upload gestito, nessuna gallery, nessun set di immagini.
- **Empty state** 🟡 — testo configurabile (`empty_appointments`) ma **nessuna illustrazione**; resta un testo su Card.
- **Notifica** 🟡 — colore+priorità sì; **icona/canale/suono custom no** (esclusi by-design: rischio soppressione Android O+ / asset di build).
- **Terminologia/microcopy** 🟡 — alcuni testi chiave sono override (`content.*`) ma **non esiste un pacchetto di voce/terminologia per verticale**; i default sono generici ("Prenota ora").

---

## 🔴 ASSENTE (NON IMPLEMENTATO)

- **Font reali impacchettati / pipeline di font** — nessun `.ttf` bundle, nessun asset font nel manifest. 🔴
- **Varianti di logo** (bianco / nero / orizzontale / quadrato / monocromatico) — esiste **un solo** logo master. 🔴
- **Nome breve** (short name / launcher label distinta dal nome app) — non modellato. 🔴
- **Sistema immagini / illustrazioni**: copertine, gallery, sfondi, illustrazioni empty-state/onboarding — 🔴.
- **Onboarding brandizzato** — nessun flusso di benvenuto personalizzabile. 🔴
- **Motion / animazioni** (animation level, transizioni di pagina, stile loader) — 🔴, tutto Material default.
- **Iconografia** (set/stile icone per template) — 🔴, tutte Material Icons.
- **Divider / card border / shadow come token editoriali distinti** — 🟡/🔴 (radius+elevation esistono, ma non `divider`/`card border`/`shadow color` separati).
- **Placeholder immagini** (avatar/logo fallback brandizzati) — 🔴 (fallback = iniziali).
- **Voice/terminology pack per verticale** (barbiere vs medico vs avvocato) — 🔴.
- **Dominio custom / deep-link domain per tenant** — `tenant_domains` esiste a DB ma **non c'è pipeline** di dominio white-label end-to-end. 🔴 (parziale schema).
- **Anteprima live** della personalizzazione in Control Room — 🔴 (si salva "alla cieca", si vede solo aprendo l'app).
- **Copyright / "powered by" configurabile** — `powered_by_enabled` esiste su `app_projects` ma non è esposto come testo/brand configurabile. 🟡/🔴.

---

## Conclusione FASE 0

**La base tecnica dei token è solida (tra le migliori per un white-label a binario unico).**
Ma la personalizzazione oggi è **cromatica e testuale**, non **esperienziale**. I quattro assi che fanno dire a un
cliente *"questa è l'app del MIO barbiere"* — **tipografia, immagini, movimento, linguaggio** — sono **assenti o inerti**.

Il resto di questo dossier progetta come colmare esattamente quel divario, **senza aggiungere funzioni fuori dal
dominio prenotazioni** e **mantenendo un solo motore**.
