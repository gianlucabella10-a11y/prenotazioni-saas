# 19 — Architettura Tecnica Completa

## 1. Stack tecnologico (vincoli di progetto)

| Layer | Tecnologia | Ruolo |
|---|---|---|
| Mobile (cliente finale) | Flutter | App white label (PWA + build native premium) |
| Mobile (operatore/titolare) | Flutter | App "Business" unica multi-tenant |
| Backend API | Laravel (PHP 8.3+) | API REST, logica di dominio, job asincroni |
| Dashboard web (Tenant Admin + Super Admin) | Laravel + SPA (o Blade/Livewire — decisione in §8) | Pannelli di gestione |
| Database | MySQL 8.0 (Amazon RDS/Aurora MySQL) | Persistenza transazionale |
| Cache / Lock / Queue broker | Redis (Amazon ElastiCache) | Cache disponibilità, lock distribuiti, code Laravel Horizon |
| Storage oggetti | Amazon S3 + CloudFront | Asset branding, immagini, export |
| Push | Firebase Cloud Messaging | Notifiche push iOS/Android/Web |
| Hosting | AWS (eu-south-1 Milano o eu-west-1) | Infrastruttura completa |

## 2. Diagramma architetturale (vista logica)

```
                          ┌──────────────────────────────────────────────┐
                          │                 CLIENT LAYER                  │
                          │                                               │
  App Cliente (Flutter)   │  App Business (Flutter)   Dashboard Web       │
  PWA + native premium    │  operatori/titolari       Tenant + SuperAdmin │
                          └───────────────┬──────────────────────────────┘
                                          │ HTTPS (TLS 1.2+)
                          ┌───────────────▼──────────────────────────────┐
                          │  CloudFront (CDN) ── S3 (asset statici, PWA) │
                          │  Route53 (DNS, sottodomini tenant)           │
                          │  AWS WAF + ALB (Application Load Balancer)   │
                          └───────────────┬──────────────────────────────┘
                                          │
                          ┌───────────────▼──────────────────────────────┐
                          │            APPLICATION LAYER (ECS Fargate)    │
                          │                                               │
                          │  ┌─────────────┐  ┌──────────────┐            │
                          │  │ API Service │  │ Web Dashboard│            │
                          │  │ (Laravel)   │  │ (Laravel)    │            │
                          │  └─────────────┘  └──────────────┘            │
                          │  ┌─────────────────────────────────┐          │
                          │  │ Queue Workers (Horizon)          │          │
                          │  │ code: critical / default / bulk  │          │
                          │  └─────────────────────────────────┘          │
                          │  ┌─────────────────────────────────┐          │
                          │  │ Scheduler (Laravel scheduler)    │          │
                          │  └─────────────────────────────────┘          │
                          └────────┬───────────────┬─────────────────────┘
                                   │               │
                  ┌────────────────▼───┐   ┌───────▼────────────┐
                  │ RDS MySQL 8        │   │ ElastiCache Redis  │
                  │ Multi-AZ           │   │ cache + lock + queue│
                  │ + Read Replica     │   └────────────────────┘
                  └────────────────────┘
                                   │
                  ┌────────────────▼──────────────────────────┐
                  │ Servizi esterni: FCM (push), SES (email),  │
                  │ provider SMS, gateway pagamenti (fase 3)   │
                  └────────────────────────────────────────────┘

                  ┌────────────────────────────────────────────┐
                  │ BUILD PIPELINE (separata dal runtime)       │
                  │ CodeBuild/GitHub Actions + macOS runner     │
                  │ per build native premium; deploy PWA su S3  │
                  └────────────────────────────────────────────┘
```

## 3. Scelte architetturali chiave e motivazioni

### 3.1 Monolite modulare Laravel (non microservizi)
- Un'unica applicazione Laravel organizzata in **moduli di dominio** (vedi [20-ddd-clean-architecture.md](20-ddd-clean-architecture.md)), deployata in tre ruoli: API, web, worker (stessa immagine, entrypoint diversi)
- **Motivazione**: team piccolo in Fase 0-1; i microservizi aggiungerebbero costi operativi senza benefici al volume previsto. La modularità interna preserva la possibilità di estrarre servizi (es. Availability Engine, Notification) quando i dati di carico lo giustificheranno (Fase 3+)

### 3.2 ECS Fargate (non EC2 gestite a mano, non Lambda)
- Container stateless, autoscaling su CPU/memoria/richieste, nessuna gestione OS
- Lambda scartata: Laravel su Lambda (Bref/Vapor) è praticabile ma il modello di costi a 10k tenant con traffico costante favorisce container; inoltre Horizon/worker long-running sono più naturali su container

### 3.3 Multi-AZ obbligatoria dal giorno 1 per RDS; Read Replica da Fase 2
- RDS Multi-AZ copre il requisito RTO ≤ 4h con ampio margine (failover in minuti)
- Read replica introdotta quando la reportistica inizia a pesare (Fase 2, [13-strategia-scalabilita.md](../13-strategia-scalabilita.md))

### 3.4 Redis con tre usi separati ma un cluster logico iniziale
- **Cache** (config tenant, disponibilità precalcolata), **lock distribuiti** (checkout prenotazione), **code** (Horizon)
- Da Fase 2: separazione cache vs queue su nodi distinti per evitare che l'eviction della cache impatti le code (le code NON devono mai stare su un Redis con eviction attiva — criticità ricorrente)

### 3.5 Region: eu-south-1 (Milano) preferita
- Data residency UE/Italia (vantaggio commerciale con tenant sanitari), latenza minima per il mercato primario. Verificare disponibilità/costo dei servizi richiesti in Milano; fallback eu-west-1 (Irlanda) con stessa compliance GDPR

## 4. Ambienti

| Ambiente | Scopo | Infrastruttura |
|---|---|---|
| `dev` | sviluppo, dati sintetici | locale (Docker Compose: MySQL, Redis, MinIO, mailpit) |
| `staging` | QA, demo commerciali, test pipeline build | copia ridotta di prod (single-AZ, istanze piccole), dati anonimizzati |
| `production` | tenant reali | architettura §2 completa |

- **IaC obbligatoria**: tutta l'infrastruttura definita con Terraform (o CDK); nessuna risorsa creata a mano in console
- **Deploy**: blue/green su ECS; rollback = switch di target group. Canary: i tenant "pilota interni" ricevono il deploy con 24h di anticipo

## 5. Risoluzione del tenant (vista runtime)

Ogni richiesta è attribuita a un tenant tramite, in ordine di priorità:
1. **App mobile**: claim `tenant_id` nel token + header `X-Tenant-ID` (devono coincidere; fa fede il token)
2. **PWA/dashboard**: sottodominio (`{slug}.piattaforma.tld` per PWA cliente; `admin.piattaforma.tld` con tenant nel token per dashboard)
3. **Super Admin**: nessun tenant implicito; accesso cross-tenant esplicito e auditato

Il middleware `ResolveTenant` carica il **TenantContext** (config, piano, feature flag, timezone, stato ciclo di vita) da cache Redis (TTL breve + invalidazione su modifica) e lo inietta nel container; tutti i Global Scope Eloquent leggono da lì. Tenant `suspended` → risposta 423 con messaggio brandizzato (Flusso 9 Fase 1).

## 6. Le tre applicazioni client

| App | Distribuzione | Branding | Note |
|---|---|---|---|
| **App Cliente** (Flutter) | PWA per tutti i tenant (default) + build native per add-on premium | White label completo runtime (design token remoti) | Vedi [24-white-label-multitenant-tech.md](24-white-label-multitenant-tech.md) |
| **App Business** (Flutter) | Una sola app sugli store, brand della piattaforma | Tema neutro + accento colore tenant | Login con selezione tenant; usata da operatori e titolari per agenda quotidiana |
| **Dashboard Web** | Web | Brand piattaforma + logo tenant | Configurazione completa, wizard onboarding, report |

Questa separazione risolve la criticità C3/D1 di [18-revisione-critica-fase1.md](18-revisione-critica-fase1.md): l'app Business non necessita white label (l'utente è il professionista, non il suo cliente) e quindi non moltiplica le build.

## 7. Comunicazione asincrona

- **Eventi di dominio** (catalogo in [20-ddd-clean-architecture.md](20-ddd-clean-architecture.md)) pubblicati dalle transazioni di dominio e gestiti da listener in coda
- **Code Horizon**: `critical` (conferme prenotazione, OTP, reset password — SLA secondi), `default` (promemoria, email transazionali), `bulk` (broadcast marketing, export, report — rate-limited per tenant)
- **Outbox pattern semplificato**: gli eventi che generano notifiche sono persistiti (tabella `notification_log`) prima dell'invio, garantendo riconciliazione in caso di crash del worker

## 8. Dashboard web: scelta tecnica

**Decisione**: Laravel + **Inertia.js + Vue 3** per entrambe le dashboard (Tenant Admin e Super Admin).
- Mantiene il routing/auth/middleware Laravel (un solo modello di sicurezza), offre UX da SPA per il wizard di branding con anteprima live (RF-11)
- Alternativa scartata: SPA separata + API pubblica — duplicherebbe la superficie di sicurezza; Blade puro — insufficiente per l'anteprima live interattiva

## 9. Release plan tecnico (ri-prioritizzazione MVP — risolve C1)

| Release | Contenuto | Corrispondenza moduli Fase 1 |
|---|---|---|
| **R1 — Core Booking (MVP)** | Tenancy, onboarding wizard, branding PWA, servizi/operatori/orari, availability engine, prenotazione, promemoria push/email, app Business agenda, Super Admin provisioning/billing manuale | A1, B1-B6 (parziale), C1-C4, D1-D3 |
| **R2 — Engagement** | Broadcast/segmentazione, waitlist, no-show, import CSV, report base, recensioni, billing automatizzato | B6-B8, C5, A2 |
| **R3 — Monetizzazione/Scala** | Pagamenti/acconti, build native premium pipeline, multi-sede avanzato, modulo sanitario (note cliniche cifrate + DPIA), API pubbliche/webhook | D4, D6, add-on |

## 10. Requisiti trasversali coperti altrove

- DDD, Clean Architecture, struttura repo → [20-ddd-clean-architecture.md](20-ddd-clean-architecture.md)
- Database, ER → [21-database-er.md](21-database-er.md)
- API REST → [22-api-rest.md](22-api-rest.md)
- Sicurezza (JWT, RBAC, MFA, audit, rate limit, GDPR, encryption) → [23-auth-sicurezza.md](23-auth-sicurezza.md)
- White label + multi-tenant tecnico → [24-white-label-multitenant-tech.md](24-white-label-multitenant-tech.md)
- Notifiche, appuntamenti, dashboard → [25-sistemi-core.md](25-sistemi-core.md)
- Performance, costi AWS, DR, backup, logging, monitoring → [26-qualita-operazioni.md](26-qualita-operazioni.md)
- Audit tecnico 50+ criticità → [27-audit-tecnico.md](27-audit-tecnico.md)
