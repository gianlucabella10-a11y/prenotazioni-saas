# APP_FACTORY_PHASE2_READINESS

> Stato della **App Factory** dopo l'audit FASE 0 (`APP_FACTORY_PHASE2_AUDIT.md`) e la chiusura dei gap. Obiettivo raggiunto a livello **"un operatore crea una nuova app cliente senza intervento tecnico"**: dalla Control Room si crea il cliente, si carica il logo (validato), si sceglie un template, si genera il pacchetto e lo si scarica — senza toccare il repository.
>
> Verifiche: backend `php artisan test` **159/159** (621 asserzioni) · Pint pulito (solo file App Factory) · `flutter analyze` pulito · `flutter test` **36**. **Nessuna modifica** a booking/auth/API/sicurezza (diff confinato ad App Factory + Control Room + Branding).

## 1. Cosa è stato completato
- **Asset Factory reale** (`GenerateBrandAssets`, GD): dal logo master → 9 icone (Android mdpi→xxxhdpi, iOS 60/120/180/1024) + 3 splash, per-tenant, **versionati**, idempotenti, isolati. Adaptive icon Android + AppIcon set iOS completo restano generati a **build-time** (`flutter_launcher_icons`/`flutter_native_splash`) dal master — corretto non stoccare 20 PNG server-side.
- **Validazione immagine** in `StoreBrandLogo`: raster valido + MIME (png/jpg/webp) + lato minimo 256px (consigliato 1024) → `ValidationException`. Niente logica nei controller.
- **Template Engine** — `TemplateRegistry` (Domain): unico accesso a `config/app_templates.php` (barber/beauty/medical/restaurant/default), normalizzazione, **fallback al default** su codice/config invalida, espone layout/font/**sezioni**. Usato da `BuildWhiteLabelConfig`, `AppProjectController`, `AllocateAppIdentifiers`, `GenerateAppPackage` (manifest ora include `sections`). Flutter sceglie il layout dal `template` (hero/standard) **senza `if cliente==X`**.
- **Build preparation** — `GenerateAppPackage` (manifest 2.0) + `ExportAppPackage`: produce il pacchetto **self-contained** `generated_apps/{uuid}/` con `manifest.json` + `assets/` (logo+derivati) + `config/build.env` (--dart-define) + `config/template.json`. Idempotente, isolato. Comando `php artisan app:generate {tenant}` e Control Room lo producono (orchestratore unico `PrepareApp`).
- **Control Room operativa**: login MFA → crea cliente → carica logo → sceglie template → **genera pacchetto** → **scarica ZIP** (`/control-room/apps/{uuid}/package`). Scheda con **anteprima brand** (logo, swatch colori, **mockup icona**, asset status n.icone/splash/versione) ed **etichetta ciclo di vita** (Bozza → Configurata → Generata/Pronta build → Pubblicata).
- **Scala (già in essere)**: release train del core + `app:build-matrix` + dashboard **Flotta**; firma Android default-safe nel gradle; CI scaffold per-tenant + batch (`.github/workflows/app-factory-*.yml`).

## 2. Cosa manca (non bloccante per il design)
- **Rendering dinamico delle `sections` in Flutter**: oggi il client consuma `layout`/`font`/`theme`; l'ordine sezioni è esposto in `/app/config` ma non ancora renderizzato → naturale nella fase DESIGN (additivo).
- **`app_templates` su DB con editor** in Control Room: oggi sono in config (sufficienti e versionabili). Ottimizzazione per quando i template diventeranno tanti.
- **Asset Factory come microservizio**: oggi GD in-process (ok fino a volumi alti).
- **Font `.ttf` reali** (Oswald/Poppins/Inter): meccanismo cablato, file esterni (licenza) da fornire; finché assenti l'app usa il font di sistema (nessuna rottura).

## 3. Cosa serve per la build reale (esterno a questo repo)
- **Mac/CI** per compilare il nativo: `flutter build appbundle` (Android) e `flutter build ipa` (iOS) non sono eseguibili in questo ambiente.
- **Account store + segreti** (in `APP_FACTORY_RELEASE_SECRETS.md`): keystore Android + service account Google Play; **account Apple Developer del cliente** (linee guida 4.2.6/4.3) + API key App Store Connect.
- **Prima esecuzione CI** su un tenant pilota (track `internal` / canary `--limit=1`) per validare la pipeline end-to-end.

## 4. Rischi
- **Native non build-verificato qui**: mitigato mantenendo i file nativi ai default e applicando la parametrizzazione a build-time (script + gradle env). Resta una build di conferma su runner.
- **Policy store "app ripetitive"** (Play): mitigare con store listing reali (descrizione/screenshot dal catalogo del cliente).
- **iOS sotto account del cliente**: enrollment + primo review sono manuali (non automatizzabili sotto un unico account).
- **Qualità del logo in ingresso**: ora mitigata dalla validazione (min 256px); consigliato 1024×1024 per icone nitide.

## 5. Stato: pronto per la fase DESIGN? → **SÌ (GO)**
La parte **tecnica** della App Factory è completa e verificata: un operatore crea un'app cliente end-to-end dalla Control Room senza intervento sul codice; ogni cliente è **configurazione + asset + identità**, mai codice; l'architettura regge 10 → 100 → 1000 (template in registry, asset versionati per-tenant, manifest+pacchetto self-contained, release train + CI matrix). Booking/auth/API/sicurezza **intatti**. Si può passare al redesign UI/UX: il punto di innesto naturale è il **rendering dinamico delle `sections`** (già esposte in `/app/config`) sopra il layout/tema/font già guidati dal template.

— Riferimenti: `APP_FACTORY_PHASE2_AUDIT.md`, `APP_FACTORY_MASTER_PLAN.md`, `APP_FACTORY_PHASE2C_READINESS.md`, `APP_FACTORY_PHASE3_READINESS.md`, `APP_FACTORY_RELEASE_SECRETS.md`.
