# DEAD_CODE_REPORT — Codice non usato, verificato

> Nessuna eliminazione eseguita. Le categorie base (classi morte, view inutilizzate, migration, config duplicate) erano già state verificate in `PROJECT_STANDARDIZATION_AUDIT.md` — qui non ripetute, solo referenziate, con **due verifiche aggiuntive** fatte in questa sessione (config applicative una per una, raggiungibilità di ogni schermata Flutter) a chiudere le categorie richieste che non erano ancora state controllate esplicitamente.

## Duplicazioni / helper duplicati / funzioni duplicate

🟢 **Nessuna trovata**, confermato in `PROJECT_STANDARDIZATION_AUDIT.md` — nessuna cartella duplicata, nessuna logica di scoping/validazione reimplementata in due punti diversi (es. la validazione MIME/dimensione logo esiste in un solo punto, `StoreBrandLogo`; il calcolo disponibilità in un solo punto, `AvailabilityCalculator`).

## Service inutilizzati

| Servizio | Stato |
|---|---|
| `AnalyticsService` (Flutter) | 🔴 **Morto** — mai istanziato in `app/providers.dart`, irraggiungibile a runtime (confermato in `PROJECT_STANDARDIZATION_AUDIT.md`, ripreso in `TECHNICAL_DEBT.md` #3) |
| Tutti i servizi backend (`TenantRegistry`, `JwtService`, `AuditLogger`, `QuotaService`, `ContrastValidator`, ecc.) | 🟢 Tutti raggiunti da almeno un punto di chiamata verificato nelle sessioni di audit precedenti |

## Classi morte

🟢 Nessuna classe PHP orfana trovata nel repository, oltre al caso Flutter sopra. Il controllo iniziale "nessun riferimento al nome classe altrove" aveva segnalato 4 falsi positivi (comandi Artisan invocati per signature stringa, non per nome classe) — verificati singolarmente come attivi in `PROJECT_STANDARDIZATION_AUDIT.md`.

## Controller inutilizzati

🟢 **Nessuno** — ogni controller backend è raggiunto da almeno una route (`routes/api.php`/`web.php`), verificato incrociando l'elenco completo dei controller con la tabella di route in `REAL_PROJECT_STATE.md`.

## Repository inutilizzati

🟢 **Nessuno**, lato Flutter (`features/*/data/*_repository.dart`) — ognuno è iniettato in `app/providers.dart` e consumato da almeno una schermata. **Nota di contesto**: il pattern "Repository" lato backend **non esiste come layer distinto** (le classi `Application/` usano gli Eloquent Model direttamente) — non è codice morto, è un pattern architetturale mai adottato lì, già segnalato in `PROJECT_STRUCTURE_AUDIT.md` sotto "Audit a grana fine".

## Migration inutili

🟢 **Nessuna trovata** — 23 file, ognuno crea o altera una tabella in uso, nessuna coppia ridondante (già verificato in `PROJECT_STANDARDIZATION_AUDIT.md` e in `REAL_PROJECT_STATE.md` §Fase 4).

## Config inutilizzate — verifica aggiuntiva fatta in questa sessione

Controllo per ognuno dei 17 file in `config/`: è referenziato da codice applicativo?

| File config | Verifica | Esito |
|---|---|---|
| `app_factory.php` | `config('app_factory...')` in `AllocateAppIdentifiers`, `LocalBuildDispatcher`, ecc. | 🟢 Usato |
| `app_templates.php` | `$this->config->get('app_templates')` in `TemplateRegistry` | 🟢 Usato |
| `auth_verification.php` | `$this->config->get('auth_verification.*')` in `EmailVerificationService` | 🟢 Usato |
| `booking.php` | `$this->config->get('booking.default_settings'/'defaults'/'default_weekly_schedule')` in `ProvisionTenant` | 🟢 Usato |
| `branding.php` | Referenziato in `StoreBrandLogo`/`GenerateBrandAssets` (disco, limiti) | 🟢 Usato |
| `sector_presets.php` | `$this->config->get("sector_presets.{sector}")` in `ProvisionTenant` | 🟢 Usato |
| `api.php` | `RateLimiter::for(...)` in `AppServiceProvider` | 🟢 Usato |
| `jwt.php` | Path chiavi in `GenerateJwtKeys`, `JwtService` | 🟢 Usato |
| `services.php` | Credenziali FCM (`config('services.fcm...')`) | 🟢 Usato |
| `queue.php` | Framework Laravel (consumato internamente, non via `config()` esplicito in `app/`) | 🟢 Usato (dal framework) |
| `app.php`, `auth.php`, `cache.php`, `database.php`, `filesystems.php`, `logging.php`, `mail.php`, `session.php` | File di configurazione standard Laravel, consumati internamente dal framework | 🟢 Usati (dal framework, non serve un `config()` esplicito in `app/` perché sono letti dai Service Provider nativi di Laravel) |

**Metodologia**: un primo grep automatico (`config('nome.`) aveva segnalato 10 file su 17 come "zero riferimenti diretti" — **falso positivo per 4 di essi** (usano `$this->config->get()` via dependency injection del contratto `Config`, non l'helper globale) e **falso positivo per gli altri 6** (sono config standard Laravel, letti internamente dal framework, mai tramite `config()` esplicito in codice applicativo). **Nessuna configurazione risulta realmente inutilizzata.**

## Assets inutilizzati

| Asset | Stato |
|---|---|
| `assets/fonts/` (Flutter) | 🟡 Cartella presente ma vuota (solo `README.md` che dichiara i `.ttf` assenti) — non "inutilizzata", **mai popolata** (gap distinto, già in `TECHNICAL_DEBT.md`) |
| 17 `IMG_97xx.HEIC` | 🟢 Già spostate in `docs/archive/reference-assets/` (sessione precedente), usate una sola volta come materiale sorgente per `SCREEN_ANALYSIS.md` — non orfane, solo storiche |
| Asset generati per brand (`storage/app/public/brand/...`) | 🟢 Dato runtime, non asset statico del repository — fuori dallo scope di questo controllo |

## Flutter screen mai raggiunte — verifica aggiuntiva fatta in questa sessione

Incrociate le 12 route dichiarate in `lib/app/router.dart` con le 12 schermate esistenti in `lib/features/*/presentation/`:

| Schermata | Route che la raggiunge |
|---|---|
| `SplashScreen` | `/` (iniziale) |
| `TenantUnavailableScreen` | `/unavailable` |
| `LoginScreen` | `/login` |
| `RegisterScreen` | `/register` |
| `VerifyEmailScreen` | `/verify-email` |
| `HomeScreen` | `/home` |
| `BusinessInfoScreen` | `/business` |
| `BookingServicesScreen` | `/booking/services` |
| `BookingScheduleScreen` | `/booking/schedule` |
| `BookingSuccessScreen` | `/booking/success` |
| `MyAppointmentsScreen` | `/appointments` |
| `ProfileScreen` | `/profile` |

🟢 **12/12 raggiungibili — nessuna schermata orfana.**

## File "quasi morti" (residuo, già noto)

| File | Stato |
|---|---|
| `resources/views/welcome.blade.php` | 🔴 Scaffold Laravel di default, nessuna route lo richiama — confermato in `PROJECT_STANDARDIZATION_AUDIT.md` |

## Sintesi

Su tutte le categorie richieste, **solo 2 elementi risultano realmente morti/non usati** in tutto il repository: `AnalyticsService` (Flutter, mai istanziato) e `welcome.blade.php` (scaffold Laravel mai personalizzato). Ogni altra categoria controllata (service, controller, repository, migration, config, asset, schermate) risulta pienamente in uso — il repository non ha un problema di accumulo di codice morto, nonostante le dimensioni.
