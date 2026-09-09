# 12 — Strategia Multi-Tenant

## 1. Obiettivo

Definire i principi architetturali che permettono a un singolo sistema (codebase, infrastruttura) di servire migliaia di tenant indipendenti, garantendo isolamento dei dati, configurabilità per-tenant e costi marginali contenuti per nuovo cliente.

## 2. Modelli di isolamento dati — opzioni e trade-off

| Modello | Descrizione | Vantaggi | Svantaggi |
|---|---|---|---|
| **Database per tenant** | Ogni tenant ha un proprio database/schema fisico | Massimo isolamento, semplice da esportare/cancellare per singolo tenant, conforme a richieste enterprise di data residency | Overhead operativo elevato a migliaia di tenant (gestione, migrazioni, backup per ciascuno) |
| **Schema condiviso con tenant_id** | Database condiviso, ogni tabella ha colonna `tenant_id`, isolamento applicato a livello applicativo/query | Costi infrastrutturali contenuti, semplice da scalare orizzontalmente | Richiede disciplina rigorosa nel codice per evitare leak cross-tenant; rischio in caso di bug applicativi |
| **Modello ibrido (a fasce)** | Schema condiviso per tenant standard (piano Base/Pro), database dedicato per tenant Enterprise o settori regolamentati che lo richiedono contrattualmente | Bilancia costi e requisiti di compliance differenziati | Maggiore complessità architetturale (due pattern da mantenere) |

**Raccomandazione**: adottare inizialmente il modello **schema condiviso con tenant_id**, con:
- Enforcement dell'isolamento a più livelli (query layer + eventuali politiche di sicurezza a livello di database, es. Row-Level Security se il database lo supporta)
- Predisposizione architetturale per migrare singoli tenant verso database dedicati in caso di requisiti enterprise/compliance specifici (modello ibrido), senza richiedere un redesign complessivo

## 3. Configurazione per-tenant

Ogni tenant ha associato un **profilo di configurazione** che include:

- Identità di branding (vedi [11-strategia-white-label.md](11-strategia-white-label.md))
- Piano attivo e feature flag derivati (vedi [10-funzionalita.md](10-funzionalita.md))
- Impostazioni regionali (lingua, fuso orario, valuta)
- Parametri operativi (soglie di cancellazione, tempistiche promemoria, regole di buffer)
- Stato del ciclo di vita (in onboarding, attivo, a rischio, sospeso, cessato — vedi Flusso 9, [08-flussi-applicativi.md](08-flussi-applicativi.md))

La configurazione è centralizzata in un **registro tenant** (tenant registry), consultato da tutti i servizi per applicare le regole corrette senza duplicazione di logica.

> **Nota di affidabilità**: il registro tenant è un componente critico condiviso da tutti i servizi e rappresenta un potenziale singolo punto di guasto per l'intera piattaforma. Deve essere progettato con i medesimi requisiti di ridondanza dei servizi core (vedi [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §8), e i servizi che lo consultano devono prevedere una cache locale di fallback per continuare a operare in caso di indisponibilità temporanea del registro.

## 4. Feature flag e gestione dei piani

- Le funzionalità Pro/Enterprise (vedi [10-funzionalita.md](10-funzionalita.md)) sono gestite tramite **feature flag associati al piano del tenant**
- L'attivazione/disattivazione di un add-on aggiorna il profilo di configurazione del tenant senza richiedere deploy di codice
- I feature flag devono essere verificati sia lato backend (per sicurezza, evitando che un client modificato accada a funzionalità non pagate) sia lato frontend (per mostrare/nascondere UI)

## 5. Gestione degli aggiornamenti del template core

- Il core applicativo (logica di business, componenti UI condivisi) è versionato centralmente
- Gli aggiornamenti vengono propagati a tutti i tenant tramite il meccanismo descritto in [11-strategia-white-label.md](11-strategia-white-label.md) (sezione 6)
- Le personalizzazioni per-tenant (branding, configurazioni) sono separate dal codice core e non vengono sovrascritte dagli aggiornamenti

## 6. Provisioning automatizzato di nuovi tenant

Il provisioning di un nuovo tenant (Flusso 1, [08-flussi-applicativi.md](08-flussi-applicativi.md)) deve essere **completamente automatizzabile** tramite un processo che:

1. Crea il record nel registro tenant con stato iniziale "in onboarding"
2. Inizializza lo spazio dati (schema/namespace logico) con dati di default (template di servizi, orari standard) per facilitare il completamento del wizard
3. Genera credenziali di accesso per il Tenant Admin
4. Predispone la configurazione di branding con valori placeholder/template predefiniti, sostituibili durante l'onboarding

## 7. Convivenza di tenant di settori diversi sulla stessa infrastruttura

- Il modello dati deve essere sufficientemente generico da rappresentare sia un "servizio" di un barbiere (es. "Taglio capelli", 30 min) sia una "prestazione" di un fisioterapista (es. "Seduta riabilitativa", 45 min, parte di un pacchetto)
- I campi specifici di settore (es. note cliniche per dentisti) sono gestiti come **estensioni opzionali del modello dati** (campi/strutture attivabili per tenant), non come tabelle separate per settore, per mantenere un unico schema gestibile
- I requisiti di compliance differenziati per settore (es. dati sanitari) sono gestiti come **livello di policy aggiuntivo** applicato al tenant, non come sistema separato (vedi [14-strategia-sicurezza.md](14-strategia-sicurezza.md))

## 8. Limiti e quote per tenant

Per garantire stabilità della piattaforma e prevenire abusi (anche involontari, es. un tenant che invia un numero eccessivo di notifiche broadcast):

- Definizione di **quote per piano**: numero massimo di operatori, sedi, servizi, invii broadcast mensili, chiamate API
- Monitoraggio dell'utilizzo per tenant con alert in caso di superamento soglie
- Le quote sono parametri di configurazione, non vincoli "hardcoded", per permettere eccezioni commerciali

## 9. Rischi specifici del modello multi-tenant e mitigazioni

| Rischio | Mitigazione |
|---|---|
| Leak di dati cross-tenant per bug applicativo | Test automatizzati dedicati all'isolamento; revisione del codice focalizzata su query con `tenant_id`; eventuale enforcement a livello database |
| "Noisy neighbor" (un tenant con traffico anomalo impatta gli altri) | Quote, rate limiting, monitoraggio per tenant |
| Singolo punto di guasto per tutti i tenant | Architettura ridotta a componenti ridondanti, piani di disaster recovery (vedi [14-strategia-sicurezza.md](14-strategia-sicurezza.md)) |
| Migrazioni di schema complesse a causa del numero di tenant | Migrazioni progettate per essere retrocompatibili e applicate in modo incrementale/automatizzato |
