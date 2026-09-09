# BUSINESS_OS_AUDIT — Il sistema operativo di business, verificato

> READY = costruito e verificato funzionante · PARTIAL = esiste una parte reale, ma incompleta o non collegata · MISSING = zero codice, confermato per assenza (non per omissione di questo audit).

| Area | Stato | Evidenza |
|---|---|---|
| **Gestione clienti** | ✅ READY | `ProvisionTenant`, ciclo di vita completo (`TenantStatus`: onboarding→active→at_risk→suspended→terminated), tutto da Control Room, testato |
| **Onboarding** | ✅ READY | Invito titolare, accettazione con scadenza 72h, provisioning transazionale — `docs/Runbooks/APP_PROVISIONING_RUNBOOK.md` |
| **Rinnovi** | 🟡 PARTIAL *(migliorato in questa sessione)* | `subscriptions.current_period_end` esiste ed è popolato da `ProvisionTenant`, ma **nessun processo di rinnovo** (automatico o manuale) agiva su questo dato prima di questa sessione. Ora **visibile** nel cruscotto (abbonamenti in scadenza/scaduti) — resta MISSING il rinnovo stesso (nessun pagamento, nessun'azione di proroga) |
| **Licenze** | 🟡 PARTIAL | `Plan`+`Subscription` sono un concetto di licenza commerciale reale (piano, quote), ma non esiste una "chiave di licenza" distinta dall'`api_key` tecnica, né un enforcement di licenza oltre le quote (`QuotaService`) |
| **Versioni** | 🟡 PARTIAL | `app_versions` esiste ma gestito manualmente, scollegato dalla build reale (`TECHNICAL_DEBT.md` #2) |
| **Supporto** | 🔴 MISSING | Nessun sistema di ticketing — comunicazione interamente fuori piattaforma (`FOUNDER_DEPENDENCY_REPORT.md`) |
| **Ticket** | 🔴 MISSING | Confermato — nessuna tabella, nessun modello, nessuna route per ticket di supporto |
| **CRM** | 🔴 MISSING | `Customer` esiste ma è il cliente finale **del tenant** (chi prenota), non un CRM B2B per il Founder (lead, pipeline, note commerciali sui clienti-tenant) |
| **Notifiche** (verso il Founder/B2B) | 🟡 PARTIAL | Notifiche verso l'utente finale (push/email) sono reali e testate; notifiche verso il Founder sono solo "pull" (cruscotto), zero canale proattivo (email/SMS) |
| **Email** (B2B — fatture, promemoria rinnovo, benvenuto) | 🔴 MISSING | Il canale email (`EmailChannel`) esiste tecnicamente ma è usato solo per il flusso utente finale (verifica, promemoria appuntamento) — nessuna email B2B verso il titolare del tenant |
| **Comunicazioni** (col cliente-tenant) | 🔴 MISSING | Link di invito/beta inviati manualmente, copia/incolla, fuori piattaforma — nessun canale integrato |
| **Documentazione** (per il cliente finale, self-service) | 🟡 PARTIAL | Esistono guide operative interne eccellenti (`docs/`, root canonici) — **nessuna documentazione rivolta al cliente-tenant** (help center, FAQ pubblica) |
| **Contratti** | 🔴 MISSING | `privacy_policy_url`/`terms_url` in `brand_profiles` sono solo link — nessuna gestione di accordi commerciali, nessuna firma/accettazione tracciata a livello di tenant (esiste solo il consenso GDPR del cliente finale, `consents` table, concetto diverso) |

## Sintesi

| Stato | Conteggio |
|---|---|
| ✅ READY | 2 (Gestione clienti, Onboarding) |
| 🟡 PARTIAL | 5 (Rinnovi, Licenze, Versioni, Notifiche B2B, Documentazione self-service) |
| 🔴 MISSING | 6 (Supporto, Ticket, CRM, Email B2B, Comunicazioni, Contratti) |

**Il pattern è netto**: tutto ciò che riguarda **l'app e il suo ciclo tecnico** (provisioning, build, distribuzione) è READY o PARTIAL con gap noti e circoscritti. Tutto ciò che riguarda **la relazione commerciale continuativa col cliente** (supporto, rinnovi attivi, comunicazione, contratti) è MISSING — non per dimenticanza, ma perché nessuna sessione precedente aveva mandato di costruire funzionalità commerciali. Questo è esattamente il divario tra "piattaforma tecnica pronta" e "azienda SaaS vendibile" misurato in `GO_TO_MARKET_REPORT.md`.

---

## Progettazione — moduli necessari per un Business Operating System completo

> Solo progettazione — nessuna implementazione oltre a quanto già fatto in Fase 3 (visibilità rinnovi). Ogni modulo elencato qui sarebbe una **nuova funzionalità commerciale**, esplicitamente fuori mandato per l'implementazione in questa e nelle sessioni precedenti.

| Modulo | Colma il gap | Dipendenze | Nota di design |
|---|---|---|---|
| **Billing Engine** | Rinnovi, Licenze | Un provider di pagamento esterno (Stripe o simile) — oggi zero integrazioni | Dovrebbe agganciarsi a `Subscription`/`Plan` già esistenti, non sostituirli — lo schema commerciale c'è già, manca solo chi lo fa muovere |
| **Support/Ticketing** | Supporto, Ticket | Una nuova entità "Ticket" collegata a `Tenant` (platform-level) — non tocca `BetaFeedback` (concetto diverso, feedback beta vs. supporto commerciale) | Potrebbe riusare `AuditLogger`/pattern esistenti per la cronologia, ma serve una tabella e un flusso di stato nuovi |
| **CRM leggero** | CRM | Nuova entità "Lead"/"Opportunità" a monte di `Tenant` (oggi il ciclo di vita parte già da `Tenant` — manca tutto ciò che precede, `PLATFORM_LIFECYCLE.md` fase 1 "Lead") | Potrebbe essere minimale: solo tracciamento di chi ha chiesto una demo, senza integrarsi con provider CRM esterni |
| **Comms Hub** | Notifiche B2B, Comunicazioni | Riusa `EmailChannel` già esistente, ma richiede nuovi template B2B (benvenuto, promemoria rinnovo, invito) distinti da quelli utente-finale già esistenti | Rischio più basso degli altri — è un'estensione di un canale già collaudato, non un sistema nuovo |
| **Contract/Legal Vault** | Contratti | Nuova entità per tracciare accordi firmati per tenant, oltre ai semplici link `terms_url`/`privacy_policy_url` già esistenti | Il più lontano dall'architettura attuale — oggi non esiste alcun concetto di "documento firmato" nel dominio |
| **Customer Success Console** | Documentazione self-service, parte di Supporto | Vista aggregata (probabilmente dentro Control Room) di salute-cliente: utilizzo, rinnovo, ticket aperti | Dipende dagli altri moduli sopra per avere dati da aggregare — è l'ultimo da costruire, non il primo |

Ordine di costruzione consigliato (se mai autorizzato): **Comms Hub → Billing Engine → Support/Ticketing → CRM leggero → Contract/Legal Vault → Customer Success Console** — dal rischio più basso/beneficio più immediato al più alto/più lontano dall'architettura esistente.
