# PLATFORM ARCHITECTURE STATE

> **Documento ufficiale di certificazione tecnica.** Redatto in ruolo di CTO / Software Architect.
> Ogni affermazione è verificabile nel codice del repository. Dove una funzionalità non esiste è
> scritto **NON IMPLEMENTATO**. Nessuna stima, nessun marketing, nessuna assunzione.
>
> - Data: 2026-07-21 · Ambito: `platform-backend` (Laravel), `platform-mobile` (Flutter), `platform-infra` (Terraform)
> - Metodo: lettura diretta di codice, migrazioni, config, route, test. Nessun file modificato.
> - Dimensioni misurate: backend `app/` ≈ **12.758 LOC** / **113 file**; Flutter `lib/` ≈ **5.278 LOC**; **51 file di test** (42 Feature + 9 Unit), **244 test** verdi.

---

## CAPITOLO 1 — VISIONE GENERALE

### Architettura adottata
**Modular monolith** Laravel 13.8 (PHP 8.3+) con client **Flutter white-label** separato che consuma l'API.
Il backend è organizzato in `app/Modules/<Modulo>/{Application, Domain, Infrastructure, Presentation|Http}`
— una stratificazione **DDD-flavored** (non DDD stretto: manca il pattern Repository, gli Eloquent model
in `Infrastructure/Models` sono usati direttamente). Un layer `app/Foundation/` raccoglie i concern
trasversali (Auth, Tenancy, Audit, Http).

### Pattern realmente presenti (verificati)
- **Multi-tenancy con global scope fail-closed**: `BelongsToTenant` + `TenantScope` (`Foundation/Tenancy`). Ogni query su modello tenant-bound è vincolata al tenant corrente; **senza contesto legato lancia `TenantContextMissing` invece di girare unscoped**. `CurrentTenant` è un servizio `scoped` (per-request). Tenant risolto da header `X-Tenant-Key` (`ResolveTenantFromKey`).
- **Application / Use-case services** `final readonly`: `BookAppointment`, `ProvisionTenant`, `GenerateAppPackage`, `BuildWhiteLabelConfig`, `GenerateBrandAssets`, ecc. — un'operazione per classe.
- **Domain layer puro**: `AvailabilityCalculator`, `TimeInterval`, enum di stato (`AppointmentStatus`, `TenantStatus`), eccezioni di dominio (`SlotUnavailable`, `InvalidStatusTransition`).
- **API error envelope uniforme**: `{"error":{code,message,details}}` (`ApiErrorResponse` + render in `bootstrap/app.php`), con **404 generico anti-enumeration cross-tenant**.
- **Idempotenza** (booking `Idempotency-Key`), **locking pessimistico** (`lockForUpdate`) + **indice parziale unico** anti doppia-prenotazione a livello DB.
- **Cache-busting white-label** via `config_version` + ETag/304.
- **RBAC coarse-grained** via middleware + enum `UserType` (customer / staff / tenant_admin / super_admin). *Non* c'è layer Gate/Policy fine-grained.

### Organizzazione dei moduli
10 moduli in `app/Modules/`: **AppFactory** (28 file), **Scheduling** (22), **Notifications** (11),
**TenantManagement** (11), **Branding** (10), **ControlRoom** (10), **Dashboard** (9), **Catalog** (6),
**Customers** (4), **Staff** (2). Foundation: Auth (7 file), Tenancy (8), Http/Middleware (7), Audit (1).

### Flusso principale (verificato)
1. Il client Flitter parte, chiama `GET /api/v1/app/config` con `X-Tenant-Key` → riceve il `WhiteLabelConfig` (tema, contenuti, contatti, sedi, feature) e costruisce il `ThemeData`.
2. Il cliente si registra/login (`/auth/*`, JWT + eventuale MFA), verifica email.
3. Consulta catalogo/staff/disponibilità (`AvailabilityCalculator`), prenota (`BookAppointment`, transazione + lock).
4. `AppointmentBooked` → `ScheduleAppointmentNotifications` → outbox → `SendNotificationJob` (queue) → push FCM / email.
5. Il professionista gestisce dalla **Dashboard** web (sessione), il proprietario di piattaforma dalla **Control Room** (super-admin), che tramite **App Factory** genera manifest e build white-label per-tenant.

### Filosofia del progetto (dal codice e dai commenti)
"Un solo binario Flutter rende qualunque tenant a partire dai dati di `/app/config` — **nessun `if cliente == X`**".
Sicurezza per difesa-in-profondità (tenant scope fail-closed, 404 anti-probing, MFA, throttling per-bucket).
Automazione operativa (scheduler singleton ECS-aware, backup giornaliero).

---

## CAPITOLO 2 — MODULI DELLA PIATTAFORMA

> Per ogni voce richiesta dal brief: stato reale, con entry/exit e criticità. **NON IMPLEMENTATO** dove assente.

| # | Modulo (brief) | Stato | Dove / Nota |
|---|----------------|-------|-------------|
| 1 | **Authentication** | 🟢 | `Foundation/Auth`: JWT (`JwtService/JwtGuard/JwtClaims`), refresh token (`RefreshTokenService`), MFA TOTP (`Totp`, `mfa_credentials`), email verification. Entry: `/api/v1/auth/*`, `AuthController`. |
| 2 | **Authorization** | 🟢 (coarse) | Middleware `EnsureUserType` + enum `UserType` + `RequireOwner`/`EnsureSuperAdmin`/`RequiresFeature`. RBAC a 4 ruoli. **Nessun Gate/Policy fine-grained.** |
| 3 | **Tenancy** | 🟢 (forte) | `Foundation/Tenancy` (scope fail-closed, `CurrentTenant`, `TenantRegistry`, `TenantAwareJob`). Vedi Cap. 3. |
| 4 | **Booking** | 🟢 | `Scheduling/Application/BookAppointment` (transazione, lock, idempotenza, quota cliente). |
| 5 | **Scheduling** | 🟢 | `Scheduling/Domain/AvailabilityCalculator` + `GetAvailability` + `AvailabilityCacheVersion`. Timezone-aware. |
| 6 | **Notification** | 🟢 | `Notifications`: canali FCM push (`FcmPushChannel` + `FcmMessageBuilder`) ed email, `TemplateRenderer`, `ScheduleAppointmentNotifications`, `SendNotificationJob`. Client: `firebase_messaging`. |
| 7 | **Payments** | 🔴 **NON IMPLEMENTATO** | 0 riferimenti a stripe/payment/invoice/checkout. `subscriptions` ha `gateway_subscription_id`/`current_period_end` **come placeholder di schema**, ma nessun gateway, webhook o logica di addebito. |
| 8 | **Assets** | 🟢 | `Branding/GenerateBrandAssets` (GD: icone/splash/favicon/store), `brand_assets` versionati, rollback. |
| 9 | **Theme** | 🟢 | `Branding/BuildWhiteLabelConfig` + Flutter `AppThemeBuilder` (light/dark, token). |
| 10 | **White Label** | 🟢 | Branding + AppFactory. Config runtime + manifest di build. |
| 11 | **Build** | 🟢 (operational-dependent) | `AppFactory/BuildDispatcher` con driver `manual`/`github`/`local`; default `manual`. |
| 12 | **App Factory** | 🟢 | `app_projects/app_builds/app_versions` + beta (`beta_testers/beta_feedback/beta_download_tokens`). |
| 13 | **Control Room** | 🟢 | Super-admin: tenants, apps/flotta, brand, backup, audit viewer, log viewer. Guard `admin` + MFA. |
| 14 | **Analytics** | 🟡/🔴 | Backend: **NON IMPLEMENTATO**. Flutter: astrazione `AnalyticsService`/`AnalyticsSink` con **sink no-op di default** (call-site presenti, nessun backend collegato). |
| 15 | **Monitoring** | 🟡 | Solo **Sentry** (`sentry/sentry-laravel`, `sentry_flutter`), **dormiente senza `SENTRY_LARAVEL_DSN`**. Nessun APM/metriche/Prometheus. |
| 16 | **Health** | 🟢 (minimo) | Endpoint Laravel `/up` (`bootstrap/app.php health: '/up'`). Nessun deep-health custom. |
| 17 | **Backup** | 🟢 | `ControlRoom/CreatePlatformBackup` + comando `platform:backup` + schedule giornaliero + retention. |
| 18 | **Deployment** | 🟢 (pilot) | `platform-infra/terraform/pilot/*.tf`, `bin/deploy.sh`, `server/user-data.sh`, runbook `DEPLOY_PILOT.md`. Ambiente **"pilot"** singolo. |
| 19 | **Customer** | 🟢 | `Customers`: `MeController` (profilo, consensi GDPR, **cancellazione account**), `customer_notes`, `consents`. |
| 20 | **Media** | 🔴 **NON IMPLEMENTATO** | Nessuna media library/CDN dedicata. Solo upload logo via `Storage` facade. |
| 21 | **Storage** | 🟢 (framework) | Laravel `filesystems.php` (public/local/s3 via env). Nessun modulo dedicato. |
| 22 | **Configuration** | 🟢 | `config/` dominio: `branding`, `app_factory`, `app_templates`, `sector_presets`, `booking`, `personalization_matrix`. |
| 23 | **Security** | 🟢 (distribuito) | Non è un modulo: JWT, scope fail-closed, MFA, throttling per-bucket, 404 anti-probing, validazione contrasto. |
| 24 | **Audit** | 🟢 | `Foundation/Audit/AuditLogger` + `audit_logs` + `ControlRoom/AuditLogController`. |
| 25 | **Logging** | 🟢 | Laravel logging + `ControlRoom/LogViewerController`. |
| 26 | **Queue** | 🟢 (DB default) | `TenantAwareJob`, `SendNotificationJob`; connessione default **database** (tabelle `jobs`/`job_batches`/`failed_jobs`). Redis/SQS configurati ma non default. |
| 27 | **Scheduler** | 🟢 | `routes/console.php`: `notifications:dispatch-due` (15 min) + `platform:backup` (daily), `onOneServer` + `withoutOverlapping`. |
| 28 | **Flutter** | 🟢 | `platform-mobile/apps/client_app` (Riverpod 3.2, go_router 17, dio 5.9, secure_storage, firebase_messaging 15.1, sentry_flutter 9.22). |
| 29 | **API** | 🟢 | `routes/api.php` sotto `/api/v1`; superfici public/customer/manage/admin con middleware stratificati. |
| 30 | **Web** | 🟢 | `routes/web.php`: Dashboard professionista (guard `web`) + Control Room (guard `admin`), sessione + CSRF. |
| 31 | **Console** | 🟢 | 8 comandi: `BackupPlatform`, `BuildAppCommand`, `BuildMatrix`, `CreateControlRoomAdmin`, `DispatchDueNotifications`, `GenerateApp`, `GenerateJwtKeys`, `RecordAppBuild`. |

**Funzionalità con schema/flag ma senza engine (NON IMPLEMENTATO):**
- **Waitlist** 🔴 — tabella `waitlist_entries` (schema ricco: customer/variant/staff/date range/offered_at/offer_expires_at), flag di piano, gate `RequiresFeature`. **Nessun model, controller, service o route.**
- **Campaigns** 🔴 — tabella `campaigns` (title/message/segment/channel/status), flag di piano, `NotificationRecord.campaign_id`. **Nessun model, controller, service o route.**

---

## CAPITOLO 3 — MODELLO DATI

**Verdetto: piattaforma multi-tenant reale, NON un gestionale single-tenant.**

44 tabelle. Isolamento e crescita progettati per SaaS:
- **`tenant_id` pervasivo**: ~30 tabelle con `foreignId('tenant_id')->constrained()` (21 `restrictOnDelete`, 5 `cascadeOnDelete`).
- **Indici tenant-first** (perf + isolamento): `[tenant_id, status]`, `[tenant_id, staff_member_id, starts_at]`, `[tenant_id, customer_id, starts_at]`, ecc.
- **Unique tenant-scoped**: `unique(['tenant_id','email'])`, `unique(['tenant_id','phone'])`, `unique(['tenant_id','idempotency_key'])`, `unique(['tenant_id','feature_code'])` → la stessa email/telefono può esistere in tenant diversi (design SaaS corretto).
- **Guardia concorrenza a livello DB**: indice **parziale** `(tenant_id, staff_member_id, starts_at, is_blocking)` con `is_blocking=1` per attivi / `NULL` per cancellati → previene doppie prenotazioni identiche anche in gara.

### Aree del modello
- **Tenancy**: `tenants`, `tenant_domains`, `tenant_features`, `plans`, `subscriptions`.
- **Identity/Auth**: `users`, `refresh_tokens`, `mfa_credentials`, `email_verifications`, `password_reset_tokens`, `devices`, `sessions`.
- **Catalog**: `locations`, `location_schedules`, `location_services`, `services`, `service_variants`, `service_categories`.
- **Scheduling**: `appointments`, `appointment_items`, `appointment_events`, `staff_members`, `staff_schedules`, `staff_services`, `schedule_exceptions`, `waitlist_entries`(dormiente).
- **Customers**: `customers`, `customer_notes`, `consents`.
- **Branding**: `brand_profiles`, `brand_assets`.
- **App Factory**: `app_projects`, `app_builds`, `app_versions`, `beta_testers`, `beta_feedback`, `beta_download_tokens`.
- **Messaging**: `notification_records`, `campaigns`(dormiente).
- **Audit/Infra**: `audit_logs`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

### Tenant isolation
Tripla difesa: (1) risoluzione tenant da chiave/sessione; (2) **global scope fail-closed** su ogni modello; (3) auto-fill + **anti-spoof** di `tenant_id` in `creating`. Coperto da `WhiteLabelIsolationTest` (verde).

### Problemi / duplicazioni / modello di crescita
- **Tabelle "morte"**: `waitlist_entries` e `campaigns` esistono senza codice applicativo → schema non usato.
- **`subscriptions.gateway_subscription_id`**: placeholder di billing mai collegato.
- **DB unico**: nessuno sharding/partitioning; crescita verticale finché non si introducono read-replica/partizioni (vedi Cap. 6).
- Nessuna duplicazione di colonne rilevante; snapshot volutamente denormalizzati su `appointment_items` (nome servizio/durata/buffer/prezzo al momento della prenotazione) — scelta corretta, non debito.

---

## CAPITOLO 4 — ENGINE (voto 0-100)

Solo engine realmente presenti nel codice.

| Engine | Stato | Completezza | Estendibilità | Debito | Voto |
|--------|-------|-------------|---------------|--------|------|
| **Tenant Isolation** (`TenantScope`) | Produzione | Alta (fail-closed, anti-spoof, testato) | Alta | Manca il test d'architettura citato ma inesistente | **92** |
| **White-Label / Theme** (`BuildWhiteLabelConfig`+`AppThemeBuilder`) | Produzione | Alta (tema light/dark, contenuti, ETag) | Alta (JSON estendibile) | Font `.ttf` non impacchettati | **88** |
| **Scheduling / Availability** (`AvailabilityCalculator`+`BookAppointment`) | Produzione | Alta (timezone, buffer, eccezioni, lock, idempotenza) | Media (no group/class booking) | Solo 1 cliente per slot | **85** |
| **Auth** (JWT+MFA+refresh) | Produzione | Alta | Media | Nessun OAuth/SSO | **84** |
| **Notification** (push+email, template, outbox) | Produzione | Alta | Alta (canali pluggable) | Richiede config Firebase a build | **82** |
| **Asset Generation** (`GenerateBrandAssets`, GD) | Produzione | Media-Alta (raster) | Media | No SVG (limite GD) | **78** |
| **App Factory / Build** (`BuildDispatcher`) | Funzionale | Media (3 driver, default `manual`) | Alta | Dipende da CI/segreti; `local` richiede SDK | **70** |
| **Smart Build Matrix** (`BuildImpactMatrix`) | Produzione | Bassa (piccolo, nuovo) | Alta | Copertura recente | **75** |

**NON contano come engine** (assenti): Payments, Analytics backend, Waitlist, Campaigns, Media, Metrics/APM.

---

## CAPITOLO 5 — DIPENDENZE

### Mappa (namespace-level, verificata)
```
AppFactory        → Branding, TenantManagement
Branding          → AppFactory, Catalog, Scheduling
Catalog           → Scheduling, Staff, TenantManagement
ControlRoom       → AppFactory, Branding, TenantManagement
Customers         → (nessuna — leaf pulito)
Dashboard         → AppFactory, Branding, Catalog, Scheduling, Staff, TenantManagement
Notifications     → Branding, Customers, Scheduling
Scheduling        → Catalog, Customers, Staff
Staff             → Catalog, Scheduling, TenantManagement
TenantManagement  → Branding, Catalog
```

### Dipendenze circolari (violazioni DDD/Clean Architecture)
- **AppFactory ↔ Branding** (ciclo diretto).
- **Catalog ↔ Scheduling** (ciclo diretto).
- **Scheduling ↔ Staff** (ciclo diretto).
- Catena: **TenantManagement → Branding → AppFactory → TenantManagement**.

I moduli sono di fatto **namespace feature-grouped**, non bounded-context indipendenti. In PHP non causano crash (nessun import a compile-time), ma violano la regola di dipendenza a senso unico.

### Accoppiamento e "troppo grande"
- **Dashboard** è un aggregatore accoppiato a 6 moduli (accettabile come layer di composizione/presentazione, ma è il punto più fragile a modifiche cross-modulo).
- **Nessun god-class**: file più grande `AppProjectController` (441 righe), poi `BrandingController` (301), `BookAppointment` (273). Nessun repository/service oltre ~270 righe.
- **Manca il pattern Repository**: gli application service usano Eloquent (`Model::query()`) direttamente → leak dell'infrastruttura nel layer applicativo (testabilità garantita però dai Feature test).
- **SOLID**: nessuna violazione grave osservata (classi coese, `final readonly`); la principale frizione è l'**Inversione delle Dipendenze** (cicli sopra).

---

## CAPITOLO 6 — SCALABILITÀ

App tier **stateless** (JWT, sessione DB, commenti ECS) → scala orizzontalmente. Il collo di bottiglia è il **data tier** e i driver di default.

| Scala | Cosa regge | Cosa rompe / da rifattorizzare |
|-------|-----------|-------------------------------|
| **100 clienti** | Tutto. Indici tenant-first, cache slot per staff/giorno, ETag. | Nulla. |
| **1.000 clienti** | Regge con DB gestito. Queue e notifiche async. | Iniziare a spostare cache/queue su **Redis** (default oggi = `database`). |
| **10.000 clienti** | App tier ok. | **Cache/queue DB-backed** diventano contesa: servono **Redis** (cache+queue) e **read-replica**. Il `cache` table e la coda `jobs` su DB non reggono il throughput. |
| **100.000 clienti** | Solo l'app tier stateless. | Serve **sharding/partitioning** per tenant, read-replica multiple, Redis cluster, storage asset su CDN, IaC multi-AZ/region (oggi Terraform **solo "pilot"**). L'architettura dati attuale (DB unico) **non regge** senza rework infrastrutturale. |

Punti a favore già pronti: statelessness, idempotenza, indici corretti, cache di disponibilità versionata, driver Redis/SQS **già configurati** (`config/queue.php`, `config/cache.php`) → passaggio via **env**, non riscrittura di codice.

---

## CAPITOLO 7 — MULTI-DOMINIO

Il motore è **appointment-centric e vertical-agnostic**. `config/sector_presets.php` definisce già 8 verticali
(`barber`, `hair`, `beauty`, `dental`, `medical`, `physio`, `consultant`, `other`) con servizi pre-configurati
(categoria/durata/buffer/prezzo). `config/app_templates.php` fornisce 5 skin. Il modello dati non contiene
nulla di specifico per settore: un servizio è (categoria + durata + buffer + prezzo) erogato da uno staff in una sede.

| Dominio | Già riutilizzabile | Cosa manca | Cosa NON modificare |
|---------|--------------------|-----------|---------------------|
| **Barber / Parrucchiere** | Tutto (preset `barber`/`hair`). | Nulla di strutturale. | Core scheduling + white-label. |
| **Centro Estetico** | Tutto (preset `beauty`). | Nulla. | Core. |
| **Dentista / Studio Medico** | Booking, staff, orari (preset `dental`/`medical`). | **Cartella clinica / documenti paziente** (non esiste `Media`/records). | Core scheduling/tenancy. |
| **Veterinario** | Booking generico. | Anagrafica "animale" (paziente ≠ cliente). | Core. |
| **Fisioterapista / Personal Trainer** | Booking 1:1 (preset `physio`). | **Prenotazione di gruppo / classi** (oggi 1 cliente per slot bloccante) — NON IMPLEMENTATO. | Core. |
| **Coach / Consulente / Commercialista / Avvocato** | Booking consulenza (preset `consultant`). | **Fatturazione/pagamenti** (Payments NON IMPLEMENTATO); gestione documentale. | Core. |

**Sintesi**: per qualsiasi verticale a **appuntamento 1:1** la piattaforma è già riutilizzabile *come dato/config*
(nessuna modifica al motore). I limiti reali e trasversali sono tre e sono di **funzionalità mancanti**, non di
architettura: (1) **niente pagamenti**, (2) **niente prenotazioni di gruppo/risorse**, (3) **niente gestione documentale/media**.

---

## CAPITOLO 8 — DEBITO TECNICO (ordinato per priorità)

> Solo debito reale/architetturale. La codebase ha **0 marcatori TODO/FIXME/HACK** in `app/` — il debito è strutturale, non "sparso".

1. **[Bloccante commerciale] Payments/Billing NON IMPLEMENTATO** — esistono `plans`/`subscriptions` e `gateway_subscription_id`, ma nessun gateway/webhook/addebito. Una SaaS a piani senza incasso.
2. **[Alto] Feature "fantasma": Waitlist e Campaigns** — tabelle + flag di piano + (waitlist) gate middleware, **senza engine**. I flag di piano promettono funzioni inesistenti.
3. **[Alto] Dipendenze circolari tra moduli** (AppFactory↔Branding, Catalog↔Scheduling, Scheduling↔Staff) — impediscono estrazione futura in servizi/bounded-context.
4. **[Alto] Scalabilità dati**: cache/queue/session di default su **`database`**, DB unico. Ceiling a ~10k tenant senza Redis + read-replica.
5. **[Medio] Assenza del pattern Repository** — Eloquent nei service applicativi; accoppiamento all'ORM.
6. **[Medio] Test d'architettura citato ma inesistente** — `BelongsToTenant` documenta "enforced by the architecture test in tests/Architecture" che **non esiste**: la regola platform-vs-tenant si regge sulla disciplina, non su un test.
7. **[Medio] Osservabilità parziale** — solo Sentry, **dormiente senza DSN**; nessuna metrica/APM/tracing; health `/up` minimale.
8. **[Medio] Analytics no-op** — l'astrazione client esiste ma il sink di default non invia nulla; backend analytics assente.
9. **[Medio] Booking solo 1:1** — nessuna prenotazione di gruppo/classi/risorse (limita fitness/education).
10. **[Basso] Font white-label non impacchettati** — `font_style` cablato, `.ttf` mancanti (effetto solo a build).
11. **[Basso] Asset solo raster** — no SVG (limite GD, documentato).
12. **[Basso] IaC solo "pilot"** — Terraform singolo ambiente; no multi-AZ/region, no pipeline CD completa.
13. **[Basso] Vincolo MVP documentato** — `WebAuthController`: gestione email staff senza sottodomini (limite noto).

---

## CAPITOLO 9 — ROADMAP DEL CTO (20 milestone)

| # | Milestone | Motivazione | Impatto | Prio | Dipendenze | Tempo | Rischio | Beneficio |
|---|-----------|-------------|---------|------|------------|-------|---------|-----------|
| 1 | Billing engine (gateway + webhook + dunning) | Nessun incasso oggi | Sblocca ricavi | P0 | subscriptions/plans | 3-5 sett | Medio | Alto |
| 2 | Rimuovere/implementare Waitlist & Campaigns | Feature-flag mentono | Coerenza prodotto | P0 | Notifications | 2-3 sett | Basso | Medio |
| 3 | Redis per cache + queue (env + IaC) | Ceiling scalabilità | Regge 10k+ | P0 | infra | 1 sett | Basso | Alto |
| 4 | Read-replica + connessione read/write | DB unico | Regge letture massicce | P1 | #3 | 1-2 sett | Medio | Alto |
| 5 | Test d'architettura (tenant/platform, cicli) | Regola non enforce | Previene regressioni | P1 | — | 3-5 gg | Basso | Alto |
| 6 | Spezzare cicli moduli (introdurre contratti/interfacce) | Clean Architecture | Estraibilità | P1 | #5 | 2-4 sett | Medio | Medio |
| 7 | Repository/porte sui domini critici | Disaccoppiare ORM | Testabilità/evoluzione | P2 | #6 | 2-3 sett | Medio | Medio |
| 8 | Osservabilità (metriche + tracing + dashboard) | Solo Sentry dormiente | Operabilità | P1 | infra | 1-2 sett | Basso | Alto |
| 9 | Attivare Sentry (DSN) + alerting | Crash reporting off | Affidabilità | P1 | #8 | 2 gg | Basso | Alto |
| 10 | CD pipeline + IaC multi-AZ | Solo pilot | Prod-grade | P1 | #3 | 2-3 sett | Medio | Alto |
| 11 | Prenotazione di gruppo/risorse | Verticali fitness/edu | Nuovi mercati | P2 | Scheduling | 3-4 sett | Medio | Alto |
| 12 | Media/documenti (paziente/cliente) | Medico/vet/legale | Nuovi verticali | P2 | Storage/S3 | 2-3 sett | Medio | Medio |
| 13 | SSO/OAuth per dashboard professionisti | Enterprise onboarding | Vendite B2B | P2 | Auth | 2 sett | Basso | Medio |
| 14 | Export audit + retention GDPR | Compliance enterprise | Vendite regolate | P2 | Audit | 1-2 sett | Basso | Medio |
| 15 | Bundling font white-label | Identità completa | UX premium | P3 | build | 1 sett | Basso | Medio |
| 16 | Rate-limit/quote per-piano centralizzate | Abuso/tiering | Monetizzazione | P2 | #1 | 1 sett | Basso | Medio |
| 17 | Backup: verifica restore automatica | Backup non testato-restore | DR reale | P1 | Backup | 1 sett | Medio | Alto |
| 18 | i18n/l10n oltre it/en | Espansione geo | Mercati | P3 | Flutter/API | 2 sett | Basso | Medio |
| 19 | Contract test API ↔ Flutter | Evitare drift | Stabilità | P2 | — | 1 sett | Basso | Medio |
| 20 | SLO/SLA + load test 10k tenant | Certezza scala | Fiducia enterprise | P2 | #3,#8 | 2 sett | Medio | Alto |

---

## CAPITOLO 10 — VALUTAZIONE FINALE

| Dimensione | Voto | Motivazione (dal codice) |
|-----------|------|--------------------------|
| **Architettura** | 78 | Modular monolith pulito, DDD-flavored; penalizzata da cicli tra moduli e assenza di Repository. |
| **Scalabilità** | 60 | App stateless ok; default DB per cache/queue e DB unico limitano oltre ~10k tenant (driver Redis/SQS però già configurati). |
| **Modularità** | 70 | Moduli reali ma accoppiati e con dipendenze circolari; Dashboard aggregatore. |
| **Pulizia** | 86 | 0 TODO/FIXME, nessun god-class, `final readonly`, Pint, naming coerente. |
| **Estendibilità** | 80 | White-label/tema/contenuti/verticali estendibili via JSON/config; canali notifiche pluggable. |
| **Manutenibilità** | 80 | Leggibile, testato (244 test), documentato; frenata dai cicli. |
| **Multi-Tenant** | 90 | Isolamento fail-closed + anti-spoof + indici/unique tenant-first, testato. Eccellente. |
| **White Label** | 88 | Un binario, zero codice tenant-specifico, config runtime + manifest build. |
| **Build Engine** | 70 | Pipeline reale a 3 driver ma default `manual` e dipendente da CI/segreti/SDK. |
| **Control Room** | 74 | Super-admin reale (tenant/app/flotta/backup/audit/log), UI Blade essenziale. |
| **Qualità del codice** | 84 | Alta coesione, eccezioni tipizzate, envelope errori uniforme. |
| **Qualità del database** | 80 | Isolamento/indici ottimi; penalizzata da tabelle morte (waitlist/campaigns) e placeholder billing. |
| **Prontezza Enterprise** | 58 | Mancano billing, osservabilità piena, SSO, multi-region, export compliance. |
| **Prontezza Commerciale** | 54 | Prodotto core funziona ma **senza pagamenti non è vendibile** as-is. |

---

## CONCLUSIONE

**Categoria reale: PIATTAFORMA (white-label multi-tenant) allo stadio di PRODOTTO INIZIALE.**

Non è un prototipo (troppo maturo: isolamento tenant enterprise-grade, motore di prenotazione con lock e
idempotenza, engine white-label data-driven, App Factory, 244 test verdi). È **oltre l'MVP** sul piano
architetturale. Ma **non è ancora una piattaforma enterprise**: mancano il **billing** (nessun incasso),
l'**osservabilità piena**, lo **scale-out dei dati** (cache/queue/DB di default non reggono oltre ~10k tenant),
e due funzioni promesse dai flag di piano (**Waitlist, Campaigns**) sono **NON IMPLEMENTATE**.

**Se dovessi investire 1 milione di euro:** la considererei una **piattaforma reale con fondamenta solide, a
stadio di early product** — non un prototipo, non un semplice gestionale. Il valore è nel **motore** (tenancy +
white-label + scheduling): raro e ben fatto. Il rischio è nel **prodotto** (monetizzazione e scala) non ancora
chiuso. L'investimento è giustificato **a condizione** di finanziare in priorità la Roadmap P0/P1 (billing,
Redis/read-replica, osservabilità, pulizia feature fantasma): con quelle milestone diventa una piattaforma
enterprise vendibile; senza, resta un ottimo motore in cerca di un prodotto commerciale.

*Certificazione basata esclusivamente sul codice presente nel repository alla data indicata. Nessun file è stato modificato.*
