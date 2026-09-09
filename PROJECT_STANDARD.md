# PROJECT_STANDARD — Standard ufficiali del repository

> Questi standard formalizzano ciò che il codice reale già fa bene in modo consistente (per renderlo vincolante e non implicito) e colmano, solo a livello di regola scritta, i punti dove oggi non esiste alcuno standard. Nessuna modifica al codice esistente è richiesta da questo documento — è una norma per il lavoro *futuro*. Da questo momento, questi standard sono obbligatori per ogni nuovo contributo.

## 1. Naming

### Backend (PHP / Laravel)
- **Classi**: `PascalCase`, un file per classe, nome file = nome classe (già rispettato al 100% nel codice letto).
- **Namespace**: `App\Modules\<NomeModulo>\<Layer>\...` per codice di dominio; `App\Foundation\<Area>\...` per codice trasversale non di dominio. Vietato codice di dominio fuori da `Modules/`; vietato codice trasversale dentro un modulo.
- **Migration**: `YYYY_MM_DD_HHMMSS_verbo_soggetto.php` (es. `create_x_tables`, `add_y_to_z`) — convenzione Laravel standard, già rispettata in tutte le 23 migration esistenti.
- **Comandi Artisan**: `namespace:azione` con `:` come separatore (es. `app:build`, `control-room:create-admin`) — già rispettato nei 7 comandi esistenti.
- **Route API**: segmenti URL in **inglese**, kebab-case dove multi-parola (`/manage/services`, `/beta/download/{token}`) — l'API è un contratto universale, non localizzato.
- **Route Dashboard/Control Room (web)**: segmenti URL in **italiano** dove rivolti all'operatore umano (`/dashboard/prenotazioni`, `/control-room/clienti`) — scelta consapevole già in uso, da mantenere: sono interfacce, non contratti tecnici.

### Flutter
- **File**: `snake_case.dart`, suffisso per ruolo (`_screen.dart` per pagine, `_repository.dart` per repository, `_controller.dart` per controller Riverpod, `_service.dart` per servizi core) — già rispettato in tutti i 35 file esistenti.
- **Classi**: `PascalCase`, nome coerente col file (es. `HomeScreen` in `home_screen.dart`).
- **Feature folder**: `lib/features/<nome_al_singolare_o_dominio>/` con sotto-cartelle fisse `data/`, `domain/`, `presentation/` (non tutte obbligatorie — `catalog/` non ha `presentation/` perché non ha schermate proprie, ed è corretto così).
- **Provider Riverpod**: nome variabile `camelCaseProvider` (es. `sessionControllerProvider`), definiti in `lib/app/providers.dart` salvo quando il provider è strettamente locale a una feature (es. `bookingFlowProvider` in `booking_flow_controller.dart`) — entrambi i pattern sono già in uso, la regola è: **provider condivisi tra più feature → `app/providers.dart`; provider usati da una sola feature → dentro la feature stessa**.

### Documentazione
- **Documenti di stato canonici alla radice** (fonte di verità corrente, uno per argomento, aggiornati in-place): `UPPER_SNAKE_CASE.md` (es. `REAL_PROJECT_STATE.md`).
- **Serie numerata tecnica/business** (`docs/`): `NN-kebab-case-italiano.md`, numerazione sequenziale mai riutilizzata anche se un documento viene ritirato.
- **Runbook operativi**: `UPPER_SNAKE_CASE_RUNBOOK.md` o `_GUIDE.md`, un solo file per procedura — mai una nuova copia con timestamp nel nome.

---

## 2. Cartelle

- **Un modulo backend = un bounded context**, struttura interna fissa: `Domain/` (opzionale se il modulo non ha logica di dominio propria, come `ControlRoom`), `Application/` (use case, orchestrazione), `Infrastructure/Models/` (Eloquent), `Http/Controllers/` o `Presentation/Controllers/` (entrambi i nomi sono oggi in uso — vedi §6 per la regola di consolidamento).
- **Nessun modulo importa `Infrastructure/` di un altro modulo** — solo `Application/`. Regola già dichiarata in `docs/22` e osservata nel codice, ma oggi **non imposta da alcun test**: diventa obbligatoria da qui in avanti che ogni nuovo modulo backend sia accompagnato da (o rientri in) un test di architettura che la verifichi meccanicamente (vedi §5).
- **`Foundation/` è per codice senza stato di dominio proprio** (tenancy, auth, audit, http) — se un futuro concetto ha uno stato di dominio (entità con identità, regole di business), è un modulo, non `Foundation/`.
- **`docs/` è per documentazione che sopravvive più di una sessione di lavoro** — un documento che descrive lo stato di un momento specifico (audit, report di readiness) va in `docs/audit/history/`, non alla radice del repository (vedi `PROJECT_STRUCTURE.md` §2).
- **Un solo livello di nesting extra tollerato in `docs/`** (es. `docs/tech/`) — se in futuro serve più struttura, usare le categorie di `PROJECT_STRUCTURE.md` §2 (`architecture/`, `business/`, `developer/`, ecc.), non nesting arbitrario.

---

## 3. Namespace (PHP)

```
App\Foundation\<Area>                         # Tenancy, Auth, Audit, Http, Enums
App\Modules\<Modulo>\Domain\<...>             # entità di dominio, enum di stato, eccezioni di dominio
App\Modules\<Modulo>\Application\<...>        # use case (verbo+sostantivo: ProvisionTenant, BookAppointment)
App\Modules\<Modulo>\Infrastructure\Models\<...>   # Eloquent
App\Modules\<Modulo>\Http\Controllers\<...>   # o Presentation\Controllers — vedi §6, va unificato
App\Console\Commands\<...>                    # comandi artisan, fuori dai moduli (trasversali per definizione)
App\Models\<...>                              # SOLO per modelli platform-level non tenant-scoped (User, Device, MfaCredential, RefreshToken) — mai aggiungere qui un modello con tenant_id
```

**Regola vincolante nuova**: qualunque nuovo modello Eloquent con colonna `tenant_id` **deve** usare il trait `App\Foundation\Tenancy\BelongsToTenant` — nessuna eccezione senza una nota esplicita nel docblock della classe che ne motivi l'esclusione (come già fatto, correttamente, per `User`). Questa regola esisteva già come commento nel codice (che dichiarava un test di enforcement mai scritto — vedi `REAL_PROJECT_STATE.md` §Fase 8) — da qui in avanti va scritta anche come test reale, non solo come commento (§5).

---

## 4. Documentazione

- **Una fonte di verità per argomento.** Prima di creare un nuovo documento, cercare se un documento esistente copre già l'argomento — se sì, **aggiornarlo**, non affiancarlo con un nuovo file. Questa singola regola avrebbe evitato l'intero cluster "beta readiness" di 12 file quasi-duplicati identificato in `PROJECT_CLEANUP_PLAN.md` §3.
- **Ogni documento dichiara la propria categoria** nell'intestazione (una delle 10: Architecture, Business, Developer, Deployment, Customer, Runbook, Audit, Release, Beta, Legacy) e la data dell'ultimo aggiornamento reale (non la data di creazione).
- **Gli "audit" e i "report di readiness" sono per definizione временный (temporanei)**: se un audit produce un'azione correttiva, l'azione va tracciata come task, non come nuovo documento "readiness" che richiude l'audit precedente. L'audit originale si archivia con una singola riga "superato da: <task/commit>", non si genera un nuovo file gemello.
- **Nessun documento di stato duplica un altro documento di stato.** Se due documenti (come `REAL_PROJECT_STATE.md` e `PROJECT_FREEZE_STATE.md` oggi) coprono lo stesso terreno da angolazioni diverse, vanno esplicitamente cross-referenziati fin dalla prima riga, dichiarando quale sezione appartiene a quale documento — non lasciati sovrapposti silenziosamente.
- **Ogni repository ha un `README.md` alla radice** — oggi assente in questo repository (vedi `PROJECT_CLEANUP_PLAN.md`), da creare come primo passo di adozione di questo standard.

---

## 5. Test

- **Backend**: `tests/Unit/` per logica pura senza I/O (validata: `AvailabilityCalculatorTest`, `TotpTest`, ecc.), `tests/Feature/` per flussi con HTTP/DB, raggruppati in sottocartelle per modulo quando il modulo ha più di 2 test file (già fatto per `AppFactory/`, `ControlRoom/`, `Branding/`) — **da estendere**: ogni modulo con logica di business non banale dovrebbe avere la propria sottocartella, non restare nella root di `Feature/`.
- **Nuovo obbligo**: ogni modello Eloquent che introduce `tenant_id` deve avere un test di isolamento cross-tenant che ne verifichi lo scope (pattern già presente in `TenantIsolationTest`/`WhiteLabelIsolationTest`/`DashboardSecurityTest` — da rendere requisito esplicito per ogni nuovo modello, non solo buona pratica).
- **Nuovo obbligo**: un test di architettura (`tests/Architecture/`, oggi assente nonostante il codice lo dichiari) che verifichi meccanicamente: (a) nessun modulo importa `Infrastructure` di un altro modulo, (b) ogni modello con `tenant_id` usa `BelongsToTenant` salvo eccezione esplicitamente documentata.
- **Flutter**: `test/unit/` (logica pura), `test/widget/` (rendering + interazione), `test/e2e/` (flussi completi) — pattern già in uso, da mantenere. **Nuovo obbligo**: ogni nuova schermata (`*_screen.dart`) deve avere almeno un test in `test/widget/` prima del merge — oggi 4 schermate su 12 non ne hanno (`booking_services_screen`, `booking_schedule_screen`, `booking_success_screen`, `profile_screen`, per dettaglio vedi `PROJECT_FREEZE_STATE.md` §7.8). Non retroattivo su queste 4 (fuori scope di questo audit), ma vincolante da ora in avanti.
- **La pipeline di build reale** (`flutter build apk` end-to-end, non sostituita da un dispatcher fittizio) deve avere almeno un test in CI che la eserciti per intero almeno una volta per release — oggi assente (`PROJECT_FREEZE_STATE.md` §8).

---

## 6. Runbook

- **Un runbook = una procedura operativa ripetibile**, non uno snapshot di una singola esecuzione. Se un file descrive "cosa ho fatto oggi", è un audit/report, non un runbook — va classificato di conseguenza (§4).
- **Un runbook vive in un solo posto**: `docs/operations/runbooks/` (procedure trasversali) o `platform-infra/runbooks/` (procedure che toccano infrastruttura/segreti) — non entrambi, non duplicato.
- **Ogni runbook ha una sezione "Verificato l'ultima volta il: <data>"** — un runbook non aggiornato da mesi va segnalato come a rischio di essere obsoleto, non eliminato silenziosamente.

---

## 7. Build

- **Driver di build esplicito per contesto**: `manual` per default sicuro (nessuna azione, solo intento registrato), `local` **solo per sviluppo/debug su una singola macchina** (mai in produzione — l'accoppiamento filesystem descritto in `PROJECT_DEPENDENCIES.md` §3.3 lo rende inadatto a un ambiente multi-macchina), `github` per ogni build destinata a un tester o a uno store.
- **Ogni build deve produrre un checksum verificabile** — oggi vero solo per il driver `local` (`PROJECT_FREEZE_STATE.md` §8); da estendere anche a `github` prima che diventi il driver di produzione standard.
- **Il numero di versione dichiarato nel database (`app_builds.version`, `app_versions`) deve corrispondere al numero di versione realmente compilato nell'APK** (`--build-name`/`--build-number` passati a `flutter build`) — oggi non è così (versione statica da `pubspec.yaml` indipendentemente dal tenant, vedi `PROJECT_FREEZE_STATE.md` §8). Diventa un requisito bloccante per ogni evoluzione del Build Engine.
- **Nessuna build di release deve poter cadere silenziosamente sulla firma debug** — se le variabili d'ambiente del keystore non sono presenti, la build deve fallire esplicitamente per una release, non proseguire con un fallback silenzioso (oggi il comportamento è l'opposto, vedi `PROJECT_FREEZE_STATE.md` §8).

---

## 8. Deployment

- **Percorso di promozione esplicito**: locale → (staging, oggi assente) → produzione. Oggi esiste solo "locale" e "produzione pilota" — l'introduzione di uno stage intermedio è responsabilità di `PROJECT_EVOLUTION_ROADMAP.md`, non di questo standard, ma una volta introdotto va rispettato senza eccezioni (nessun deploy diretto a produzione che salti lo staging).
- **Ogni modifica a `platform-infra/terraform/` è revisionata come codice applicativo** (stesso livello di attenzione — tocca credenziali e topologia di produzione), non trattata come documentazione.
- **`deploy.sh` resta idempotente e ri-eseguibile** — vincolo già rispettato oggi (commento esplicito nello script), da mantenere per ogni evoluzione dello script.

---

## 9. Release

- **Ogni release di produzione (backend) è taggata in git** (`git tag`) al momento del deploy — oggi non risulta un tag scheme in uso; da introdurre.
- **Ogni release mobile ha un numero di versione univoco e monotono**, coerente con lo store (Android `versionCode` sempre crescente) — oggi non garantito (§7).
- **Un solo documento "processo di release" vivo** (`docs/deployment/RELEASE_PROCESS.md` dopo la riorganizzazione) — non un nuovo file per ogni release eseguita.

---

## 10. Versioning

- **API**: prefisso `/api/v{n}`, già in uso (`/api/v1`). Regola vincolante: **v1 non si rompe mai in-place** — un cambiamento non retrocompatibile richiede `/api/v2`, mai una modifica silenziosa dentro v1. Oggi non esiste ancora un meccanismo di deprecazione (`REAL_PROJECT_STATE.md`/audit API — NOT FOUND) — da progettare quando/se nascerà una v2, non prima.
- **App mobile**: Semantic Versioning (`MAJOR.MINOR.PATCH+BUILD`) nel `pubspec.yaml`, `BUILD` sempre crescente e mai riutilizzato — oggi il valore è statico (`1.0.0+1`) per ogni build di ogni tenant, in violazione di questa regola fin da subito; collegato al gap descritto in §7.
- **Documentazione**: i documenti numerati in `docs/` (`NN-nome.md`) non vengono mai rinumerati — un documento ritirato lascia il proprio numero "vuoto" con una nota, non viene riassegnato ad altro (evita ambiguità nella cronologia).

---

Questi standard sono normativi da oggi in avanti. Non retroattivi sul codice/documentazione esistente salvo dove esplicitamente indicato — l'applicazione retroattiva è materia di `PROJECT_EVOLUTION_ROADMAP.md`.
