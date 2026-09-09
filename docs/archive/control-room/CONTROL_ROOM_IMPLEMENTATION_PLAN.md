# CONTROL_ROOM_IMPLEMENTATION_PLAN (FASE 0)

> Piano tecnico della **Control Room MVP v1** — console proprietaria super-admin per gestire i primi 10→50 clienti senza supporto tecnico. Blade, stessa app Laravel, zero SPA, riuso massimo. Verificato sul codice reale (baseline: 90/90 test verdi).

## 1. Architettura scelta
- **Blade server-rendered nella stessa app**, prefisso **`/control-room`** (nomi rotta `control.*`), separato dal dashboard cliente (`/dashboard`, invariato).
- **Guard dedicata `admin`** (driver session, provider `users` esistente — un provider dedicato NON è necessario: `User` contiene già `type=super_admin`). Sessione separata da `web` (chiavi sessione distinte → un professionista loggato sul dashboard NON è autenticato sulla Control Room e viceversa).
- **Middleware `EnsureSuperAdmin`** (specchio di `RequireOwner`): 403 se `type !== super_admin`. Difesa in profondità: il login Control Room accetta solo `super_admin`, il middleware ri-verifica a ogni richiesta.
- **MFA obbligatoria** per il super-admin (riuso `Totp` + `MfaCredential`, stesso schema del titolare): è l'account più privilegiato.
- **Riuso UI**: `public/css/dashboard.css` e i pattern Blade del dashboard (card, table, badge, btn).
- **Audit** su ogni accesso e azione (riuso `AuditLogger`).

## 2. File modificati (additivi, nessuna rottura)
- `config/auth.php` — aggiunge guard `admin` (session, provider users). Non tocca `web`/`api`.
- `bootstrap/app.php` — alias `control.admin` → `EnsureSuperAdmin`; `redirectGuestsTo` reso path-aware (`/control-room/*` → `control.login`, altrove `/dashboard/login`).
- `routes/web.php` — nuovo gruppo `/control-room` (guest + area autenticata `auth:admin` + `control.admin`).
- `app/Modules/TenantManagement/Presentation/Controllers/AdminTenantController.php` — refactor minimo: la transizione di stato usa la nuova action condivisa `ChangeTenantStatus` (comportamento/API identici, test esistenti restano verdi).
- `app/Modules/TenantManagement/Application/ProvisionTenant.php` — usa la nuova `IssueTenantInvite` (estrazione, stesso comportamento) + accetta `phone`/`address` OPZIONALI per la sede.

## 3. Nuovi file
**Backend**
- `app/Modules/ControlRoom/Http/Middleware/EnsureSuperAdmin.php`
- `app/Modules/ControlRoom/Http/Controllers/ControlRoomAuthController.php` (login + MFA + logout, guard `admin`)
- `app/Modules/ControlRoom/Http/Controllers/TenantsController.php` (index/create/store/show + suspend/reactivate)
- `app/Modules/ControlRoom/Http/Controllers/TenantInviteController.php` (rigenera/revoca invito)
- `app/Modules/ControlRoom/Http/Controllers/TenantBrandController.php` (colore + logo)
- `app/Modules/TenantManagement/Application/ChangeTenantStatus.php` (condivisa API↔web)
- `app/Modules/TenantManagement/Application/IssueTenantInvite.php` (condivisa provisioning↔reset)
- `app/Modules/Branding/Application/StoreBrandLogo.php` (upload su disco `public` + riga `brand_assets`)
- `app/Console/Commands/CreateControlRoomAdmin.php` (`control-room:create-admin {email} {--password=}`)

**Viste** (`resources/views/control_room/`): `layout`, `auth/login`, `auth/mfa-challenge`, `auth/mfa-setup`, `tenants/index`, `tenants/show`, `tenants/create`.

**Test** (`tests/Feature/ControlRoom/`): `ControlRoomAccessTest`, `ControlRoomTenantManagementTest`.

## 4. Tabelle / modelli riutilizzati (NESSUNA migration nuova)
`tenants`, `plans` (seed `base/pro/enterprise` via `PlanSeeder`), `subscriptions`, `tenant_domains`, `brand_profiles`, **`brand_assets`** (già pronta per il logo), `users` (`type=super_admin`, `last_login_at`), `password_reset_tokens` (inviti), `audit_logs`. Modelli: `Tenant`, `TenantStatus`, `Plan`, `Subscription`, `BrandProfile`, `BrandAsset`, `User`, `MfaCredential`.

## 5. Flusso creazione tenant (riuso `ProvisionTenant`, niente duplicazione)
Form Control Room (nome, categoria/settore, email owner, telefono, colore principale, logo opz.) → `TenantsController@store` → **`ProvisionTenant::execute()`** (una transazione: tenant `onboarding` + subscription dal piano + brand default + sede [+telefono/indirizzo] + orari + catalogo settoriale + owner MFA-enforced + invito) → se `primary_color`/logo forniti, applica al `BrandProfile`/`brand_assets` (riuso `StoreBrandLogo`) → mostra **invite-link** (plaintext una sola volta) + **api-key**.

## 6. Rischi sicurezza e mitigazioni
- **Accesso del professionista/cliente alla Control Room** → guard `admin` separata + `EnsureSuperAdmin` + login che filtra `super_admin`. Test: tenant_admin/staff/customer/guest ⇒ 403/redirect.
- **Privilege escalation** → la sessione `web` non concede la `admin` (guard distinte); il `type` è ri-verificato a ogni richiesta.
- **Tenant isolation** → la Control Room legge i `Tenant` (modello platform-level, non scoped); le query cross-tenant restano protette dagli scope esistenti. Test: l'isolamento resta intatto.
- **Invito**: token salvato **hash** (mai in chiaro a DB), scadenza 72h, plaintext mostrato solo alla generazione; "copia link" richiede rigenerazione (corretto).
- **MFA obbligatoria** sul super-admin; **audit** su login, creazione, sospensione, invito.
- **Logo upload**: validazione mime/dimensione/peso, storage su disco dedicato, nome file non controllato dall'utente.

## 7. Test necessari
- `ControlRoomAccessTest`: super_admin entra; tenant_admin/staff/customer/guest negati (403/redirect); rotte protette.
- `ControlRoomTenantManagementTest`: lista + ricerca + filtro stato; **create via ProvisionTenant** (assert tenant+owner+invito+brand in DB); dettaglio; suspend→reactivate (transizioni valide) e transizione illegale bloccata; rigenera invito.
- Regressione: i **90 test esistenti** restano verdi (refactor `ChangeTenantStatus`/`IssueTenantInvite` non cambia il comportamento API).

## Definition of Done (verifica finale)
1. Entro come proprietario piattaforma · 2. Creo un cliente senza toccare il DB · 3. Genero l'accesso (invito) · 4. Sospendo/riattivo · 5. Controllo il brand (colore/logo) · 6. Nessun cliente vede l'area · 7. Tutti i test verdi. Poi: `php artisan test`, verifica route, lint, e `CONTROL_ROOM_READINESS.md`.
