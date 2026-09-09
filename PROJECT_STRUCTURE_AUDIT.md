# PROJECT_STRUCTURE_AUDIT — Audit completo (GREEN / YELLOW / RED)

> Verificato leggendo il codice e la documentazione reali. Nessuna modifica al codice applicativo. La riorganizzazione della documentazione (`docs/`, radice) descritta più sotto **è stata eseguita** in questa stessa sessione — è l'unica azione non puramente di analisi in questo documento, ed è dichiarata esplicitamente dove rilevante.

**Legenda**: 🟢 GREEN = solido, nessuna azione richiesta. 🟡 YELLOW = funzionante, con un gap noto e circoscritto. 🔴 RED = compromesso, contraddice se stesso o manca di una garanzia strutturale minima.

| Area | Stato | Motivazione sintetica |
|---|---|---|
| Struttura cartelle (codice) | 🟢 | `Modules/<Nome>/{Domain,Application,Infrastructure,Http}` rispettato con consistenza reale su 10 moduli backend; `lib/features/<nome>/{data,domain,presentation}` rispettato su 8 feature Flutter |
| Struttura cartelle (documentazione) | 🟢 *(era 🔴)* | Prima di questa sessione: 82 file markdown alla radice, nessuna categoria, nessun `README.md`, 19 mai committati. **Riorganizzati in questa sessione** in `docs/{Architecture,Business,Backend,Flutter,AppFactory,ControlRoom,WhiteLabel,Deployment,Testing,Operations,Runbooks,API,Release,archive}/` — vedi `PROJECT_MAP.md` per l'albero risultante |
| Naming (PHP/Flutter) | 🟢 | Convenzioni rispettate al 100% sul codice letto — `PascalCase` classi, `snake_case` file Dart, namespace `App\Modules\<Nome>\<Layer>` coerente, nessuna eccezione trovata |
| Moduli backend | 🟡 | Confini rispettati *de facto* (nessun modulo importa `Infrastructure` di un altro), ma **nessun test li impone** — il codice dichiara un test di architettura che non esiste (`REAL_PROJECT_STATE.md` §Fase 8) |
| Backend (core applicativo) | 🟢 | Tenancy, booking, auth JWT+MFA — tutti reali e testati. Gap minori: 3 pattern di risposta API coesistenti, CORS non ristretto (vedi riga "Config") |
| Flutter | 🟡 | 12 schermate, tutte complete, zero placeholder — ma `AnalyticsService` mai istanziato (codice morto), config Firebase native assenti dal repo, 4 schermate + il guard di routing senza test |
| App Factory (orchestrazione) | 🟡 | Identità app, manifest, asset, distribuzione beta: tutti reali e funzionanti |
| Build Engine (compilazione) | 🔴 | Firma release che ricade silenziosamente su debug se le env keystore mancano (nessun fail-fast); `versionCode`/`versionName` statici indipendenti da tenant/build pur avendo due tabelle DB dedicate a tracciarli; zero test end-to-end sulla build reale; accoppiamento filesystem che assume backend e mobile sulla stessa macchina |
| Control Room | 🟡 | Copertura funzionale ampia e reale (creazione/gestione tenant, build, beta, tester) — ma nessuna azione "termina/archivia" esposta pur esistendo nell'enum, nessuna vista di audit log, MFA opzionale per riga anziché invariante |
| White Label Engine | 🟢 | Pipeline asset→manifest→runtime completa, versionata con rollback, testata (`WhiteLabelIsolationTest`) |
| Tenant Engine (multi-tenancy) | 🟢 | 4 livelli di difesa (risoluzione da fonte fidata, scope globale, fail-closed, mascheramento 404), testato con test di isolamento reali. Unico neo: `User` non ha lo scope automatico (per motivazione architetturale documentata, ma senza garanzia strutturale) |
| Assets (branding) | 🟢 | Pipeline GD pura, checksum, versionamento con rollback, validatore di contrasto |
| CI (`.github/workflows/`) | 🟡 | 3 workflow funzionanti e ben strutturati — ma la pipeline App Factory dipende da un singolo backend di produzione raggiungibile via SSH (nessuno staging isolato), `max-parallel: 3` hardcoded |
| Documentazione | 🟢 *(era 🔴, vedi riga "Struttura cartelle")* | |
| Test | 🟡 | 45 file backend + 13 Flutter, buona ampiezza su tenancy/booking — ma pipeline di build reale mai esercitata, 4 schermate Flutter + router senza test, nessun test di architettura |
| Migrations | 🟢 | 23 file, naming coerente (`YYYY_MM_DD_HHMMSS_verbo_soggetto`), indici e foreign key appropriati, nessuna migration orfana o in conflitto trovata |
| Config | 🟡 | 17 file di configurazione per dominio, ben organizzati — ma nessun `config/cors.php` applicativo (eredita il default Laravel wide-open `allowed_origins: ['*']`) |

## Audit a grana fine — pattern di codice (aggiunto in questa sessione)

Non un'area intera ma la singola categoria di file/classe, per verificare coerenza di pattern oltre che di cartella.

### Backend (PHP)

| Categoria | Stato | Motivazione |
|---|---|---|
| Controller | 🟡 | Naming coerente (`XController`), ma la cartella che li ospita **non è uniforme**: `AppFactory`, `ControlRoom`, `Dashboard` usano `Http/Controllers/`; `Branding`, `Catalog`, `Customers`, `Scheduling`, `Staff`, `TenantManagement` usano `Presentation/Controllers/`. Nessun problema funzionale, ma è un'incoerenza di convenzione non ancora rilevata nei passaggi precedenti — 6 moduli contro 3 usano nomi diversi per lo stesso concetto. |
| Actions/Use case (`Application/`) | 🟢 | Naming verbo+sostantivo consistente (`ProvisionTenant`, `BookAppointment`, `GenerateBrandAssets`, `AllocateAppIdentifiers`) — nessuna eccezione trovata, nessun suffisso generico tipo "Action"/"Handler" necessario perché il nome è già auto-esplicativo |
| Jobs | 🟢 | Suffisso `Job` coerente (`RunAppBuildJob`, `SendNotificationJob`), entrambi `ShouldQueue` |
| Commands | 🟢 | 7 comandi, tutti con signature `dominio:azione` coerente, namespace unico `App\Console\Commands` (corretto: sono trasversali, non di un singolo modulo) |
| DTO / value object | 🟢 | Nessun suffisso generico `Dto` — nomi descrittivi (`BuildDispatchResult`, `JwtClaims`, `TenantContext`), scelta di naming migliore di un suffisso generico, non un'incoerenza |
| Services | 🟢 | Stesso principio dei DTO: nomi per ruolo (`TenantRegistry`, `JwtService`, `AuditLogger`, `QuotaService`, `ContrastValidator`) — solo alcuni portano il suffisso `Service`, per scelta descrittiva coerente, non per distrazione |
| Repository (pattern) | 🟡 | **Il pattern Repository non esiste nel codice** — le classi `Application/` interrogano gli Eloquent Model direttamente. `docs/22-struttura-repository.md` (piano originale) menzionava "repository concreti"; non è mai stato implementato come layer distinto. Non è un bug (Eloquent-diretto è una scelta legittima), ma è un disallineamento tra piano e realtà da tenere presente — coerente con lo scostamento già documentato in `PROJECT_STRUCTURE.md` §0 |
| Models (Eloquent) | 🟢 | `Infrastructure/Models/`, naming singolare coerente, uso di `BelongsToTenant` verificato su 21/25 modelli tenant-scoped (eccezioni documentate) |
| Events | 🟡 | Solo il modulo `Scheduling` usa eventi di dominio (`AppointmentBooked`, `AppointmentCancelled`) — pattern non esteso ad altri moduli. Non è un errore (gli altri moduli potrebbero non averne bisogno), ma se un futuro modulo introduce eventi, non c'è un secondo esempio da cui copiare le convenzioni oltre a `Scheduling` |
| Listeners | 🟡 | Non verificata in questo passaggio una wiring esplicita degli eventi `Scheduling` a listener Laravel dedicati (vs. chiamata diretta da `Notifications`) — segnalato come **da verificare**, non affermato come assente |
| Policies | 🔴 | Confermato **NOT FOUND** (già in `REAL_PROJECT_STATE.md` §Fase 6) — nessun `app/Policies`, autorizzazione interamente a guard/middleware |
| Middleware | 🟢 | Naming coerente e descrittivo (`EnsureX`, `RequiresX`, `ResolveXFromY`, `BindX`), organizzato per proprietario (`Foundation/Http/Middleware` per il trasversale, `Dashboard/Http/Middleware` e `ControlRoom/Http/Middleware` per lo specifico di modulo) |
| Providers | 🟡 | Un solo `AppServiceProvider` per l'intera applicazione (rate limiter, singleton tenancy) — nessun provider per modulo. Coerente con l'assenza di route per-modulo (`routes/api.php`/`web.php` restano flat, come già notato in `PROJECT_STRUCTURE.md` §0) |

### Flutter

| Categoria | Stato | Motivazione |
|---|---|---|
| Widgets | 🟡 | Nessuna cartella `widgets/` condivisa — ogni widget riutilizzabile (`_EmptyState`, `_ErrorState`, ecc.) è privato dentro il file della propria schermata. Funziona, ma significa zero riuso di componenti UI tra feature — coerente con l'assenza del pacchetto `design_system` pianificato in `docs/22` e mai costruito |
| Screens | 🟢 | 12 file, suffisso `_screen.dart` coerente al 100%, tutte raggiungibili da `app/router.dart` (nessuna schermata orfana — verificato incrociando le 12 route con i 12 file) |
| Repositories (Flutter) | 🟢 | Suffisso `_repository.dart` coerente in ogni `features/*/data/` |
| Services (Flutter) | 🟢 | Suffisso `_service.dart` coerente in `core/` |
| Core | 🟢 | 7 sotto-cartelle (`analytics,env,network,push,session,storage,utils`), ognuna a singola responsabilità |
| Shared | 🔴 | **Non esiste** una cartella `shared/` — stesso gap del punto "Widgets": nessun layer di componenti/utility esplicitamente condivisi tra feature, oltre a `core/` |
| Theme | 🟡 | Logica di tema (`AppThemeBuilder`) vive dentro `features/white_label/domain/`, non in una cartella `theme/` dedicata — ragionevole vista l'accoppiamento stretto col config white-label, ma non immediatamente ovvio a chi cerca "dove sta il tema" |
| Assets | 🟡 | `assets/fonts/` esiste ma è vuota salvo un `README.md` che dichiara i file `.ttf` assenti (gap noto, già in `TECHNICAL_DEBT.md`) |
| Manifest (Android/iOS) | 🟢 | `AndroidManifest.xml`/`Info.plist` usano placeholder (`${appName}`) risolti a build-time, nessun valore per-tenant committato per errore |
| Storage (backend) | 🟢 | Struttura Laravel standard (`app/private`, `app/public`, `logs`, `framework`), dischi corretti in `.gitignore` |
| Build (artefatti) | 🟢 | `platform-mobile/.../build/` correttamente escluso da git, nessun artefatto committato per errore |
| CI | 🟡 | 3 workflow ben strutturati, ma **PHP 8.4 in CI contro `"php": "^8.3"` richiesto in `composer.json`** — funziona (8.4 soddisfa `^8.3`), ma è un disallineamento di versione dichiarata mai armonizzato |
| Scripts | 🟢 | `platform-infra/bin/`, script root (`START_CONTROL_CENTER.command`, ecc.) — naming coerente, ognuno documentato |
| Config | 🟡 | Vedi riga "Config" nella tabella principale — 🟡 confermato, gap CORS |

## Sintesi

**2 aree RED prima di questa sessione, 1 dopo, a livello di area**: la documentazione (🔴→🟢, risolta) e il Build Engine (🔴, richiede codice). **A livello di pattern di codice (nuovo in questa sessione), 2 RED aggiuntivi**: assenza di `Policies` (già nota) e assenza di un layer `shared/`/`widgets/` condiviso lato Flutter (nuovo rilievo — non bloccante, ma un vero limite al riuso di componenti UI). **6+9 aree YELLOW** tra macro-aree e pattern di dettaglio: nessuna blocca l'uso attuale, tutte già tracciate o ora aggiunte a `TECHNICAL_DEBT.md`.

Il Build Engine resta l'unica area RED **a livello di macro-area** del repository — è anche l'unica per cui la soluzione richiede necessariamente codice, non organizzazione (vedi `PROJECT_EVOLUTION_ROADMAP.md` punti 14-15). A livello di pattern di dettaglio, l'assenza di `Policies` e di un layer UI condiviso Flutter sono i due nuovi punti da tenere d'occhio se il progetto cresce in complessità.
