# 13 — Strategia di Scalabilità

## 1. Dimensioni della scalabilità

La scalabilità della piattaforma deve essere considerata su tre assi:

1. **Scalabilità tecnica**: capacità dell'infrastruttura di sostenere la crescita del numero di tenant, utenti finali, prenotazioni e notifiche
2. **Scalabilità operativa**: capacità del team (onboarding, supporto, gestione build/store) di gestire un numero crescente di tenant senza crescita lineare dei costi del personale
3. **Scalabilità commerciale**: capacità del modello di vendita di acquisire nuovi clienti a un costo di acquisizione (CAC) sostenibile rispetto al valore (LTV) — approfondito in [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md) e [16-piano-crescita.md](16-piano-crescita.md)

## 2. Scalabilità tecnica

### 2.1 Componenti a maggiore impatto di scala

| Componente | Driver di crescita | Considerazioni |
|---|---|---|
| Database (dati tenant, servizi, prenotazioni) | Numero tenant × numero clienti finali × prenotazioni | Necessità di indicizzazione efficiente su `tenant_id`; possibilità di partizionamento orizzontale (sharding) per fasce di tenant a lungo termine |
| Engine calcolo disponibilità | Frequenza di accesso app cliente finale | Componente critico per latenza (RNF-01); candidato a caching e ottimizzazione dedicata |
| Notification Engine | Numero di notifiche (promemoria + broadcast) | Necessità di code/asincronicità (message queue) per gestire picchi (es. invio massivo broadcast) |
| Pipeline Build White Label | Numero di tenant con build attive/aggiornamenti | Parallelizzazione delle build, gestione code di pubblicazione verso gli store |
| Storage asset (loghi, icone, immagini servizi) | Numero tenant × numero asset | Utilizzo di storage a oggetti con CDN per distribuzione asset statici |

### 2.2 Principi architetturali per la scalabilità

- **Stateless application layer**: i servizi applicativi non devono mantenere stato in memoria locale, per permettere scaling orizzontale (aggiunta di istanze)
- **Asincronicità per operazioni non immediate**: invio notifiche, generazione build, invio campagne broadcast devono essere gestiti tramite code di lavoro (job queue), non in modo sincrono e bloccante
- **Caching**: dati a basso tasso di variazione (configurazione tenant, catalogo servizi, orari) sono candidati a caching per ridurre il carico sul database
- **Separazione tra letture e scritture** (eventuale read replica del database) per la reportistica e le dashboard analitiche, che non devono impattare le performance delle operazioni transazionali (prenotazioni)
- **Monitoraggio continuo (observability)**: metriche, log centralizzati, tracing distribuito per identificare colli di bottiglia man mano che il numero di tenant cresce

### 2.3 Crescita per fasi

| Fase | Numero tenant indicativo | Considerazioni infrastrutturali |
|---|---|---|
| Pilota | 1-50 | Infrastruttura singola, monitoraggio manuale accettabile |
| Early growth | 50-500 | Introduzione di code asincrone, caching, CDN per asset; automazione completa onboarding |
| Scale-up | 500-3.000 | Valutazione partizionamento database, separazione letture/scritture, scaling orizzontale dei servizi applicativi |
| Scala | 3.000-10.000+ | Sharding per fasce di tenant, regioni multiple (se espansione geografica), automazione completa pipeline build con parallelismo elevato |

## 3. Scalabilità operativa

### 3.1 Automazione dell'onboarding
- L'intero Flusso 1 ([08-flussi-applicativi.md](08-flussi-applicativi.md)) deve essere self-service per il tenant, con intervento umano limitato a casi eccezionali
- Materiali di supporto (guide, video tutorial) per ridurre i ticket di assistenza relativi a configurazioni standard

### 3.2 Supporto a più livelli (tiered support)
- **Livello 1 (self-service)**: knowledge base, chatbot/FAQ
- **Livello 2 (supporto standard)**: ticketing con SLA differenziati per piano
- **Livello 3 (supporto enterprise)**: account dedicato per tenant di fascia alta o multi-sede

### 3.3 Automazione della gestione build/store
- La pipeline di build (Flusso 2) deve scalare in parallelo: la generazione di N build non deve essere un processo seriale
- Monitoraggio centralizzato dello stato di pubblicazione di tutte le app sugli store, con alert su rifiuti/problemi

### 3.4 Crescita del team
La crescita del team (vendite, customer success, supporto tecnico, sviluppo) deve essere pianificata in funzione del numero di tenant attivi, con rapporti indicativi (da validare operativamente):

| Funzione | Rapporto indicativo |
|---|---|
| Customer Success / Onboarding | 1 persona per 150-300 tenant attivi |
| Supporto tecnico (L2) | 1 persona per 300-500 tenant attivi |
| Sviluppo/manutenzione piattaforma | dimensionato per evoluzione prodotto, non linearmente legato al numero di tenant (grazie al modello multi-tenant) |

## 4. Scalabilità geografica (espansione futura)

- L'architettura multi-tenant deve permettere l'estensione a nuovi mercati senza redesign: localizzazione (lingua, valuta, fuso orario) è già parte della configurazione tenant ([12-strategia-multi-tenant.md](12-strategia-multi-tenant.md))
- Considerazioni aggiuntive per espansione UE: requisiti di data residency specifici per paese (es. settore sanitario in alcuni stati membri), normative fiscali locali per la fatturazione

## 5. Indicatori di scalabilità da monitorare

- Tempo di risposta del calcolo disponibilità sotto carico crescente
- Tempo medio di completamento pipeline build
- Tempo medio di onboarding per nuovo tenant (target < 5 giorni, vedi [01-prd.md](01-prd.md))
- Rapporto costo infrastrutturale / numero tenant attivi (deve decrescere con la scala)
- Numero di ticket di supporto per tenant/mese (deve decrescere con la maturità della piattaforma)
