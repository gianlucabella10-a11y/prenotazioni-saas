# NAMING_GUIDELINES — Standard ufficiale per categoria di file

> Estende `PROJECT_STANDARD.md` §1 (naming generale) con la convenzione per ciascuna categoria di classe/pattern, verificata contro l'uso reale nel codice (`PROJECT_STRUCTURE_AUDIT.md` "Audit a grana fine"). Dove il codice esistente è già coerente, la regola è quella osservata, resa vincolante. Dove il codice ha un'incoerenza reale, è segnalata esplicitamente — non corretta in questa fase.

## Controller

**Standard**: `<Sostantivo>Controller`, metodi RESTful (`index`, `show`, `create`, `store`, `edit`, `update`, `destroy`) dove applicabile, altrimenti verbo esplicito (`suspend`, `betaLink`, `dispatchBuild`).

🟡 **Incoerenza reale da correggere quando si toccano questi file** (non in questa fase): la cartella che li ospita non è uniforme — `AppFactory`, `ControlRoom`, `Dashboard` usano `Http/Controllers/`; `Branding`, `Catalog`, `Customers`, `Scheduling`, `Staff`, `TenantManagement` usano `Presentation/Controllers/`. **Standard da questo momento in avanti per ogni modulo nuovo**: `Http/Controllers/` (maggioranza semplice non decisiva, ma è il nome più diretto — "Presentation" introduce un livello di astrazione linguistica non necessario quando in Laravel il termine standard è "Http").

## Actions / Use case

**Standard**: verbo + sostantivo, senza suffisso (`ProvisionTenant`, `BookAppointment`, `GenerateBrandAssets`) — non `ProvisionTenantAction`/`ProvisionTenantHandler`. Vive sempre in `Application/`. Un metodo pubblico principale (`execute()` o un verbo esplicito come `forUser()`).

🟢 Già rispettato al 100% — nessuna eccezione trovata.

## Jobs

**Standard**: `<VerboAlPresente/Sostantivo>Job` (`RunAppBuildJob`, `SendNotificationJob`), implementa `ShouldQueue`, vive in `Application/`.

🟢 Già rispettato al 100%.

## Commands (Artisan)

**Standard classe**: `<VerboSostantivo>` senza suffisso `Command` esplicito nel nome breve visibile (`GenerateApp`, `BuildAppCommand` — quest'ultimo con suffisso, incoerente rispetto agli altri 6). **Standard signature**: `dominio:azione` in kebab-case (`app:build`, `app:build-matrix`, `notifications:dispatch-due`, `control-room:create-admin`).

🟡 **Incoerenza minore**: `BuildAppCommand.php` porta il suffisso `Command`, gli altri 6 comandi no (`GenerateApp`, `BuildMatrix`, `CreateControlRoomAdmin`, `DispatchDueNotifications`, `GenerateJwtKeys`, `RecordAppBuild`). **Standard da questo momento**: nessun suffisso `Command` (la cartella `Console/Commands/` già lo rende ovvio).

## DTO / Value Object

**Standard**: nome descrittivo del ruolo, non suffisso generico (`BuildDispatchResult`, `JwtClaims`, `TenantContext` — non `BuildDto`/`ClaimsDto`). `final readonly class` dove il linguaggio lo supporta.

🟢 Già rispettato, e la scelta di evitare un suffisso generico è **preferibile**, non solo "diversa" — resa esplicitamente standard.

## Services

**Standard**: nome per ruolo specifico quando possibile (`TenantRegistry`, `AuditLogger`, `QuotaService`, `ContrastValidator`) — il suffisso `Service` è accettabile ma non obbligatorio quando un nome più preciso esiste.

🟢 Già rispettato.

## Repositories

**Standard Flutter**: `<Dominio>Repository` in `features/<nome>/data/` (`AuthRepository`, `BookingRepository`, `CatalogRepository`, `MeRepository`) — rispettato al 100%.

🔴 **Standard backend: NON esiste come pattern.** Il codice backend non ha un layer Repository — le classi `Application/` usano gli Eloquent Model direttamente. Questo documento **non introduce** il pattern retroattivamente (violerebbe il vincolo "non rifattorizzare/non cambiare architettura") — registra solo che, se in futuro si introduce un Repository backend, il nome standard sarà `<Dominio>Repository` in un namespace `Infrastructure/Repositories/`, per coerenza col lato Flutter.

## Events

**Standard**: `<Sostantivo><VerboAlPassato>` (`AppointmentBooked`, `AppointmentCancelled`), vivono in `Application/Events/` del modulo che li solleva.

🟢 Rispettato nell'unico modulo che li usa (`Scheduling`). **Standard per futuri moduli**: stesso pattern, stessa posizione (`<Modulo>/Application/Events/`).

## Policies

🔴 **Il pattern non esiste nel codice** (nessun `app/Policies`, confermato in più sessioni di audit). Standard dichiarato per un'eventuale introduzione futura: `<Modello>Policy` in `app/Policies/`, registrata via `Gate::policy()` in `AppServiceProvider` — non introdotto ora, per vincolo esplicito di questa sessione.

## Middleware

**Standard**: verbo imperativo o participio che descrive il controllo (`EnsureX`, `RequiresX`, `ResolveXFromY`, `BindX`, `RequireX`). Alias registrato in `bootstrap/app.php` con nome kebab-case breve (`tenant.key`, `auth.full`, `control.admin`).

🟢 Già rispettato al 100%, incluso nei moduli specifici (`ControlRoom/Http/Middleware/`, `Dashboard/Http/Middleware/`).

## Providers

**Standard**: `<Ambito>ServiceProvider`. Oggi un solo provider (`AppServiceProvider`) per l'intera applicazione — coerente con l'assenza di route/config per-modulo.

🟢 Nessuna incoerenza, perché non c'è varietà da rendere coerente (un solo file).

## Flutter — Widgets

**Standard**: `PascalCase`, widget privati (riutilizzati solo dentro la propria schermata) prefissati con underscore (`_EmptyState`, `_ErrorState`, `_DayStrip`).

🟢 Rispettato. 🟡 Nota architetturale (non di naming): nessun widget è condiviso tra schermate tramite una cartella `widgets/` — vedi `PROJECT_STRUCTURE_AUDIT.md`.

## Flutter — Screens

**Standard**: `<Nome>Screen` in `features/<nome>/presentation/<nome_snake_case>_screen.dart`.

🟢 Rispettato al 100%, 12/12 file.

## Assets

**Standard**: `snake_case`, organizzati per tipo (`assets/fonts/`). Nessun asset per-tenant committato nel repository (i brand asset sono generati a runtime, mai statici).

🟢 Rispettato — l'unico asset statico dichiarato (`assets/fonts/`) è vuoto per un gap noto (`TECHNICAL_DEBT.md`), non per un problema di naming.

## Routes

**Standard**: API (`/api/v1/*`) in inglese, kebab-case dove multi-parola; Dashboard/Control Room (interfacce operatore umano) in italiano. Nome route (`->name(...)`) in dot-notation coerente col prefisso (`control.tenants.show`, `dashboard.servizi.index`).

🟢 Rispettato al 100% su tutte le route lette in `REAL_PROJECT_STATE.md`.

## Riepilogo — incoerenze da correggere quando si toccano questi file (non ora)

1. `Http/Controllers/` vs `Presentation/Controllers/` — 3 moduli contro 6, standard dichiarato: `Http/Controllers/`.
2. `BuildAppCommand` con suffisso `Command`, unico tra 7 comandi — standard dichiarato: nessun suffisso.

Nessun'altra incoerenza di naming trovata in tutto il repository.
