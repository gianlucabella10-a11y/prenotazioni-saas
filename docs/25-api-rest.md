# 25 — API REST

## 1. Principi

- Base path **`/api/v1`**; contratto OpenAPI come fonte di verità (generazione client Flutter)
- Risorse identificate da **uuid** (mai id numerici)
- JSON; date-time in ISO 8601 UTC (`2026-06-12T14:30:00Z`); orari ricorrenti come `HH:MM` + timezone esplicita
- Paginazione cursor-based (`?cursor=…&limit=…`) sulle liste potenzialmente grandi (appuntamenti, clienti, notifiche)
- Formato errori uniforme: `{ "error": { "code": "slot_unavailable", "message": "...", "details": {...} } }` — `code` stabile e documentato, `message` localizzato
- Autenticazione: `Authorization: Bearer <JWT>` ([26-autenticazione-autorizzazione.md](26-autenticazione-autorizzazione.md))
- Risoluzione tenant: claim `tid` nel JWT; per gli endpoint pubblici pre-login dell'app cliente, header `X-Tenant-Key` (chiave pubblica compilata nella build white label)

## 2. Versioning e compatibilità

- Breaking change ⇒ nuova versione (`/api/v2`); le versioni convivono per ≥ 12 mesi (le app installate non si aggiornano in massa)
- Endpoint `GET /api/v1/app/min-version`: l'app confronta la propria versione e mostra blocking/soft update
- Campi nuovi sempre additivi e opzionali; il client Flutter ignora campi sconosciuti

## 3. Endpoint — App Cliente Finale (`customer` scope)

### Config e contenuti pubblici (auth: solo `X-Tenant-Key`)
| Metodo | Path | Descrizione |
|---|---|---|
| GET | `/app/config` | WhiteLabelConfig: tema, logo URL, feature flag, sedi, lingue, `config_version` (cacheable, ETag) |
| GET | `/catalog/services` | Catalogo servizi/varianti/prezzi per sede |
| GET | `/staff` | Staff bookable (nome, foto, servizi) |

### Autenticazione customer
| Metodo | Path | Descrizione |
|---|---|---|
| POST | `/auth/register` | email o telefono + password; o social token |
| POST | `/auth/login` | → access + refresh token |
| POST | `/auth/social` | Google/Apple sign-in |
| POST | `/auth/otp/request` + `/auth/otp/verify` | login via OTP telefono |
| POST | `/auth/refresh` | rotazione refresh token |
| POST | `/auth/logout` | revoca refresh token |

### Prenotazione
| Metodo | Path | Descrizione |
|---|---|---|
| GET | `/availability` | `?service_variant_ids[]=&staff_id=&location_id=&from=&to=` → slot disponibili (read model cacheata) |
| POST | `/appointments` | crea (header `Idempotency-Key` obbligatorio); 409 `slot_unavailable` se conflitto |
| GET | `/appointments` | i propri, paginati, `?scope=upcoming|past` |
| GET | `/appointments/{uuid}` | dettaglio |
| POST | `/appointments/{uuid}/reschedule` | nuovo slot (regole cutoff) |
| POST | `/appointments/{uuid}/cancel` | entro cutoff; 422 `cutoff_passed` oltre |
| POST | `/waitlist` / DELETE `/waitlist/{uuid}` | iscrizione lista d'attesa |

### Profilo e privacy
| Metodo | Path | Descrizione |
|---|---|---|
| GET/PATCH | `/me` | profilo |
| PUT | `/me/consents` | opt-in/out granulari |
| PUT | `/me/devices` | registrazione token FCM |
| POST | `/me/gdpr/export` | richiesta export (asincrona, notifica al completamento) |
| POST | `/me/gdpr/erasure` | richiesta cancellazione |

## 4. Endpoint — App Gestionale / Dashboard Tenant (`staff` / `tenant_admin` scope)

### Agenda e appuntamenti
| Metodo | Path | Ruolo minimo |
|---|---|---|
| GET | `/manage/agenda?date=&staff_id=&location_id=` | staff (solo propria agenda se non admin) |
| POST | `/manage/appointments` | staff — prenotazione per conto del cliente (walk-in/telefono) |
| PATCH | `/manage/appointments/{uuid}` | staff |
| POST | `/manage/appointments/{uuid}/confirm` | staff — per tenant in modalità request-approve |
| POST | `/manage/appointments/{uuid}/no-show` | staff (RF-52) |
| POST | `/manage/appointments/{uuid}/complete` | staff |
| POST | `/manage/unavailability` | staff — dichiara indisponibilità → Flusso 5 |

### Configurazione (tenant_admin)
| Area | Endpoint principali |
|---|---|
| Catalogo | CRUD `/manage/services`, `/manage/services/{uuid}/variants`, ordinamento, attivazione per sede |
| Staff | CRUD `/manage/staff`, `/manage/staff/{uuid}/schedules`, `/manage/staff/{uuid}/services` |
| Sedi e orari | CRUD `/manage/locations`, `/manage/locations/{uuid}/schedules`, `/manage/schedule-exceptions` |
| Clienti | CRUD `/manage/customers`, `POST /manage/customers/import` (CSV, job asincrono, RF-53), `GET /manage/customers/{uuid}/history`, note (`visibility` secondo permessi) |
| Branding | `GET/PUT /manage/brand`, `POST /manage/brand/assets` (upload via URL S3 pre-firmato), `POST /manage/brand/preview` (anteprima tema), validazione contrasto WCAG sincrona |
| Notifiche | `GET/PUT /manage/notification-settings` (soglie promemoria), CRUD `/manage/campaigns`, `POST /manage/campaigns/{uuid}/send` |
| Report | `GET /manage/reports/kpi?from=&to=`, `GET /manage/reports/staff-utilization`, `POST /manage/exports` (CSV asincrono) |
| Account | `GET /manage/subscription`, `GET /manage/invoices`, gestione utenti staff (inviti, ruoli, reset) |

## 5. Endpoint — Super Admin (`platform` scope, rete/dominio separato)

| Area | Endpoint principali |
|---|---|
| Tenant | CRUD `/admin/tenants`, `POST /admin/tenants/{uuid}/suspend|reactivate|terminate`, `GET /admin/tenants/{uuid}/onboarding-state` |
| Piani | CRUD `/admin/plans`, `PUT /admin/tenants/{uuid}/features` (override flag) |
| Build | `POST /admin/tenants/{uuid}/builds` (trigger), `GET /admin/builds?status=`, `POST /admin/builds/{uuid}/retry` |
| Billing | `GET /admin/subscriptions?status=past_due`, webhook gateway: `POST /webhooks/billing` (firma verificata) |
| Monitoraggio | `GET /admin/metrics/adoption`, `GET /admin/tenants/at-risk` |
| Supporto | `POST /admin/impersonate/{tenant_uuid}` — sessione impersonificazione a tempo, integralmente audit-loggata |

## 6. Idempotenza, concorrenza, rate limiting

- **Idempotency-Key** obbligatoria su `POST /appointments`, `POST /manage/appointments`, `POST /manage/campaigns/{uuid}/send`: chiave persistita (UNIQUE tenant+key); replay → risposta originale (200/201 con stesso body), non duplicazione
- **Optimistic concurrency** sulle risorse di configurazione: header `If-Match: <ETag>` su PUT/PATCH di brand, schedules; 412 su conflitto
- **Rate limiting** (Redis, per chiave composita): per IP sugli endpoint anonimi; per utente sugli autenticati; per tenant complessivo (noisy neighbor). Limiti differenziati: auth 5/min/IP; availability 60/min/utente; default 120/min/utente. Risposta 429 con `Retry-After`
- **Webhook in ingresso** (billing): verifica firma, idempotenza su event id, risposta rapida + elaborazione in coda

## 7. Convenzioni di sicurezza API

- Nessun dato di altri tenant raggiungibile da alcun endpoint: il tenant deriva **solo** dal JWT/`X-Tenant-Key`, mai da parametri richiesta
- Autorizzazione a livello risorsa via policy (es. uno staff non admin legge solo la propria agenda)
- Output filtering nei Resource: i campi sensibili (note cliniche) emessi solo ai ruoli autorizzati
- CORS: dashboard web su domini noti; le app mobile non richiedono CORS
- Richieste/risposte loggate senza payload sensibili (vedi [32-qualita-aws.md](32-qualita-aws.md) §7)
