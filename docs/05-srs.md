# 05 — Software Requirements Specification (SRS)

## 1. Introduzione

Questo documento descrive i requisiti funzionali e non funzionali della piattaforma, secondo una struttura ispirata allo standard IEEE 830 / ISO 29148, adattata al contesto SaaS multi-tenant white label.

### 1.1 Attori del sistema

| Attore | Descrizione |
|---|---|
| **Super Admin** | Personale della software house; gestisce tenant, billing, monitoraggio piattaforma |
| **Tenant Admin** | Titolare dell'attività; configura branding, servizi, operatori, orari, sedi |
| **Operatore/Staff** | Collaboratore del tenant (es. barbiere, igienista dentale); gestisce la propria agenda |
| **Cliente Finale** | Utente dell'app mobile che prenota appuntamenti |
| **Sistema di Notifica** | Componente automatizzato (push/email/SMS) |
| **Sistema di Build/CI-CD** | Componente automatizzato per generazione build white label |

## 2. Requisiti Funzionali (RF)

### 2.1 Gestione Tenant e Provisioning (Super Admin)

- **RF-01**: Il sistema deve permettere al Super Admin di creare un nuovo tenant specificando ragione sociale, settore, piano contrattuale, dati di fatturazione
- **RF-02**: Il sistema deve generare automaticamente un'istanza di configurazione white label (tema, asset placeholder) per ogni nuovo tenant
- **RF-03**: Il sistema deve permettere al Super Admin di sospendere, riattivare o disattivare un tenant (es. per mancato pagamento)
- **RF-04**: Il sistema deve fornire una dashboard di monitoraggio aggregato (numero tenant attivi, utilizzo, stato fatturazione, ticket di supporto)
- **RF-05**: Il sistema deve mantenere un audit log delle operazioni di provisioning e configurazione critica

### 2.2 Configurazione White Label (Tenant Admin)

- **RF-06**: Il Tenant Admin deve poter impostare nome dell'app, sottotitolo/tagline
- **RF-07**: Il Tenant Admin deve poter caricare logo (in più formati/risoluzioni richiesti dagli store)
- **RF-08**: Il Tenant Admin deve poter caricare icona app (rispettando specifiche dimensionali iOS/Android)
- **RF-09**: Il Tenant Admin deve poter configurare la splash screen (immagine e/o colore di sfondo)
- **RF-10**: Il Tenant Admin deve poter definire la palette colori dell'app (colore primario, secondario, colori di stato) tramite selettore guidato con anteprima live
- **RF-11**: Il sistema deve fornire un'anteprima in tempo reale ("preview") dell'app con le personalizzazioni applicate, prima della pubblicazione
- **RF-12**: Il sistema deve validare che le combinazioni di colore scelte rispettino soglie minime di contrasto/accessibilità (WCAG AA)

### 2.3 Gestione Servizi

- **RF-13**: Il Tenant Admin deve poter creare, modificare, disattivare servizi (nome, descrizione, durata, prezzo, categoria)
- **RF-14**: Il sistema deve supportare servizi con varianti (es. "Taglio capelli — corto/lungo" con prezzi/durate diverse)
- **RF-15**: Il sistema deve supportare pacchetti/combo di servizi (es. "Taglio + Barba")
- **RF-16**: Il Tenant Admin deve poter associare ciascun servizio a uno o più operatori abilitati ad eseguirlo
- **RF-17**: Il Tenant Admin deve poter impostare un tempo di buffer/pulizia tra appuntamenti consecutivi per servizio

### 2.4 Gestione Operatori

- **RF-18**: Il Tenant Admin deve poter creare profili operatore (nome, foto, ruolo, servizi erogabili)
- **RF-19**: Il sistema deve permettere a ciascun operatore di avere un proprio calendario di disponibilità indipendente
- **RF-20**: Il sistema deve permettere agli operatori di impostare assenze/ferie/permessi che bloccano automaticamente la disponibilità nell'app
- **RF-21**: Il Cliente Finale deve poter scegliere un operatore specifico oppure "nessuna preferenza" (assegnazione automatica)

### 2.5 Gestione Orari e Disponibilità

- **RF-22**: Il Tenant Admin deve poter configurare orari di apertura/chiusura per sede, per giorno della settimana
- **RF-23**: Il sistema deve supportare orari differenziati per operatore rispetto agli orari generali della sede
- **RF-24**: Il sistema deve gestire chiusure straordinarie (festività, eventi) a livello di sede o singolo operatore
- **RF-25**: Il sistema deve calcolare gli slot di disponibilità in tempo reale in base a: orari, durata servizio, buffer, prenotazioni esistenti, assenze

### 2.6 Gestione Sedi (Multi-location)

- **RF-26**: Il sistema deve supportare uno o più punti vendita/sedi per tenant (in base al piano)
- **RF-27**: Il Cliente Finale deve poter selezionare la sede preferita all'interno dell'app del tenant
- **RF-28**: Ogni sede deve poter avere orari, operatori e servizi (sottoinsieme) propri

### 2.7 Prenotazione (Cliente Finale)

- **RF-29**: Il Cliente Finale deve poter registrarsi/accedere tramite email, numero di telefono o provider social (Google/Apple)
- **RF-30**: Il Cliente Finale deve poter visualizzare il catalogo servizi con prezzi e durate
- **RF-31**: Il Cliente Finale deve poter selezionare servizio, operatore (opzionale), data e ora tra gli slot disponibili
- **RF-32**: Il sistema deve confermare la prenotazione e generare un riepilogo (data, ora, servizio, operatore, sede, prezzo)
- **RF-33**: Il Cliente Finale deve poter visualizzare lo storico e i prossimi appuntamenti
- **RF-34**: Il Cliente Finale deve poter modificare o cancellare un appuntamento entro una soglia temporale configurabile dal Tenant Admin
- **RF-35**: Il sistema deve impedire la prenotazione di slot già occupati (gestione concorrenza/race condition)
- **RF-36**: Il sistema deve supportare la gestione di una lista d'attesa (waitlist) per slot al momento non disponibili

### 2.8 Notifiche e Comunicazioni

- **RF-37**: Il sistema deve inviare una notifica di conferma immediatamente dopo la prenotazione
- **RF-38**: Il sistema deve inviare promemoria automatici configurabili (es. 24h e 2h prima dell'appuntamento)
- **RF-39**: Il sistema deve inviare notifiche in caso di modifica/cancellazione dell'appuntamento da parte del tenant
- **RF-40**: Il Tenant Admin deve poter inviare comunicazioni broadcast (promozioni, chiusure straordinarie) ai clienti che hanno accettato di riceverle
- **RF-41**: Il sistema deve gestire il consenso (opt-in/opt-out) alle comunicazioni di marketing in conformità GDPR

### 2.9 Gestione Clienti (CRM di base)

- **RF-42**: Il Tenant Admin/Operatore deve poter visualizzare l'anagrafica e lo storico appuntamenti di ciascun cliente
- **RF-43**: Il sistema deve permettere l'inserimento di note interne sul cliente (es. preferenze, allergie per estetisti, note cliniche per dentisti — con livelli di accesso differenziati)
- **RF-44**: Il sistema deve identificare automaticamente i clienti "inattivi" (nessuna prenotazione da N giorni) per campagne di recall

### 2.10 Recensioni e Feedback (roadmap post-MVP, predisposizione dati)

- **RF-45**: Il sistema deve poter richiedere automaticamente una recensione/feedback al cliente dopo l'appuntamento
- **RF-46**: Il Tenant Admin deve poter visualizzare le valutazioni medie e i feedback ricevuti

### 2.11 Reportistica (Tenant Admin)

- **RF-47**: Il sistema deve fornire un dashboard con KPI base: numero prenotazioni, tasso di occupazione, tasso di no-show, fatturato stimato
- **RF-48**: Il sistema deve permettere l'esportazione dei dati (CSV) per appuntamenti e clienti

### 2.12 Generazione e Distribuzione App (Build Pipeline)

- **RF-49**: Il sistema deve generare automaticamente i pacchetti applicativi (build) a partire dalla configurazione white label del tenant
- **RF-50**: Il sistema deve gestire il versionamento delle build e la pubblicazione sugli store (o aggiornamento PWA)
- **RF-51**: Il sistema deve notificare il Tenant Admin sullo stato della pubblicazione (in revisione, approvata, rifiutata)

### 2.13 Gestione No-Show e Importazione Dati (introdotti in audit, vedi [17-audit-revisione.md](17-audit-revisione.md))

- **RF-52**: Il sistema deve permettere all'operatore/Tenant Admin di marcare un appuntamento come "no-show" dopo l'orario previsto; il sistema deve mantenere uno storico dei no-show per cliente, consultabile dal Tenant Admin per eventuali politiche di gestione (es. richiesta di acconto per clienti con no-show ripetuti, in combinazione con il modulo pagamenti)
- **RF-53**: Il sistema deve fornire una funzionalità di importazione massiva dell'anagrafica clienti tramite file CSV durante l'onboarding

## 3. Requisiti Non Funzionali (RNF)

### 3.1 Prestazioni
- **RNF-01**: Il calcolo degli slot disponibili deve completarsi in < 1 secondo per il 95° percentile delle richieste
- **RNF-02**: L'app cliente finale deve avere un tempo di avvio (cold start) < 3 secondi su dispositivi di fascia media

### 3.2 Disponibilità e Affidabilità
- **RNF-03**: La piattaforma deve garantire un uptime ≥ 99.9% mensile per i servizi core (autenticazione, prenotazione)
- **RNF-04**: Il sistema deve prevedere backup automatici giornalieri dei dati con retention minima di 30 giorni
- **RNF-05**: Il sistema deve prevedere un piano di disaster recovery con RPO ≤ 24h e RTO ≤ 4h per i servizi core

### 3.3 Scalabilità
- **RNF-06**: L'architettura deve supportare la crescita da decine a oltre 10.000 tenant senza modifiche strutturali al modello dati (vedi [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) e [13-strategia-scalabilita.md](13-strategia-scalabilita.md))

### 3.4 Sicurezza
- **RNF-07**: Tutti i dati in transito devono essere cifrati (TLS 1.2+)
- **RNF-08**: I dati sensibili a riposo devono essere cifrati
- **RNF-09**: Il sistema deve implementare isolamento dei dati tra tenant (nessun accesso cross-tenant)
- **RNF-10**: Il sistema deve implementare controllo accessi basato su ruoli (RBAC) per Super Admin, Tenant Admin, Operatore, Cliente Finale
- **RNF-20**: La creazione di una prenotazione deve essere atomica (transazione con verifica e blocco dello slot) per prevenire doppie prenotazioni in caso di richieste concorrenti
- **RNF-21**: Il sistema deve includere una suite di test automatizzati dedicata alla verifica dell'isolamento multi-tenant, eseguita ad ogni rilascio (nessuna query deve poter restituire dati di un tenant diverso da quello autenticato)
- Vedi dettaglio completo in [14-strategia-sicurezza.md](14-strategia-sicurezza.md)

### 3.5 Usabilità
- **RNF-11**: La dashboard di configurazione deve essere utilizzabile da un utente non tecnico senza formazione (principio "self-service")
- **RNF-12**: L'app cliente finale deve supportare almeno italiano e inglese, con possibilità di estensione

### 3.6 Compatibilità
- **RNF-13**: L'app cliente finale deve essere compatibile con le ultime 3 major version di iOS e Android
- **RNF-14**: Il sistema deve essere compatibile con i principali browser desktop/mobile per le dashboard web

### 3.7 Manutenibilità
- **RNF-15**: Gli aggiornamenti del template white label devono poter essere propagati a tutti i tenant senza richiedere intervento manuale per ciascuno (salvo personalizzazioni specifiche)
- **RNF-16**: Il sistema deve essere coperto da test automatizzati per i flussi critici (prenotazione, pagamento, notifiche)

### 3.8 Compliance
- **RNF-17**: Il sistema deve essere conforme al GDPR (Regolamento UE 2016/679)
- **RNF-18**: Per i tenant del settore sanitario, il sistema deve supportare la gestione di categorie particolari di dati personali (dati sanitari) con misure di sicurezza rafforzate
- **RNF-19**: Il sistema deve fornire strumenti per l'esercizio dei diritti dell'interessato (accesso, cancellazione, portabilità dei dati)

## 4. Tracciabilità requisiti → moduli

La mappatura dei requisiti sopra elencati verso i moduli applicativi è dettagliata in [09-moduli.md](09-moduli.md) e [10-funzionalita.md](10-funzionalita.md).
