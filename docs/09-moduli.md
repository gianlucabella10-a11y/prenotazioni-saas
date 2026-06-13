# 09 — Definizione dei Moduli

La piattaforma è organizzata in moduli funzionali, ciascuno con responsabilità chiare, per supportare l'architettura multi-tenant e la configurabilità white label. I moduli sono raggruppati per area applicativa.

## A. Area Piattaforma (Super Admin / Software House)

### A1. Modulo Tenant Management
- Creazione, modifica, sospensione, cancellazione tenant
- Assegnazione piano e moduli add-on
- Audit log operazioni di provisioning

### A2. Modulo Billing & Subscription
- Gestione fee di attivazione e canone ricorrente
- Integrazione con gateway di pagamento per addebiti automatici
- Gestione stati tenant (attivo, a rischio, sospeso, cessato)
- Fatturazione automatica

### A3. Modulo Monitoring & Analytics Piattaforma
- Dashboard aggregata su utilizzo, performance, errori
- Metriche di adozione per tenant (download, prenotazioni, utenti attivi)
- Alerting su anomalie (es. tenant inattivi, errori ricorrenti)

### A4. Modulo Build & Release Management
- Gestione configurazioni white label per tenant
- Pipeline CI/CD per generazione build (Android/iOS/PWA)
- Gestione versioni del template core e propagazione aggiornamenti
- Tracciamento stato pubblicazione store

### A5. Modulo Supporto
- Sistema di ticketing/assistenza per Tenant Admin
- Base di conoscenza/guide self-service
- Strumenti di accesso "impersonificazione" (supporto agente su tenant, con audit)

## B. Area Configurazione Tenant (Tenant Admin)

### B1. Modulo Branding & White Label
- Gestione asset: logo, icona, splash screen
- Configurazione palette colori e temi
- Anteprima live dell'app personalizzata
- Gestione nome app, tagline, informazioni legali (P.IVA, indirizzo, privacy policy)

### B2. Modulo Anagrafica Attività
- Dati attività, sedi (indirizzo, contatti, geolocalizzazione)
- Impostazioni generali (lingua, fuso orario, valuta)

### B3. Modulo Catalogo Servizi
- CRUD servizi (nome, descrizione, durata, prezzo, categoria, immagini)
- Gestione varianti e pacchetti/combo
- Associazione servizi-operatori
- Configurazione buffer/tempi di pulizia

### B4. Modulo Gestione Operatori
- CRUD profili operatore
- Associazione servizi erogabili
- Gestione disponibilità individuale (orari, eccezioni, ferie)
- Ruoli e permessi (Operatore vs Tenant Admin)

### B5. Modulo Orari e Calendario
- Configurazione orari per sede
- Configurazione eccezioni/chiusure straordinarie
- Engine di calcolo disponibilità (slot)

### B6. Modulo Notifiche & Comunicazioni
- Configurazione promemoria automatici (tempistiche)
- Creazione campagne broadcast/segmentate
- Gestione consensi/opt-in marketing (registro consensi GDPR)
- Template messaggi (personalizzabili per tenant)

### B7. Modulo CRM Clienti
- Anagrafica clienti, storico appuntamenti
- Note interne (con livelli di accesso/permessi)
- Segmentazione clienti (attivi, inattivi, per servizio)
- Identificazione clienti a rischio abbandono

### B8. Modulo Reportistica Tenant
- Dashboard KPI: prenotazioni, occupazione, no-show, fatturato stimato
- Esportazione dati (CSV)
- Report per operatore/sede

## C. Area App Cliente Finale (End User)

### C1. Modulo Onboarding & Autenticazione
- Registrazione/login (email, telefono, social)
- Gestione profilo utente, preferenze notifiche

### C2. Modulo Catalogo & Prenotazione
- Visualizzazione servizi/prezzi
- Selezione servizio, operatore, sede, slot
- Conferma prenotazione

### C3. Modulo "I miei appuntamenti"
- Storico e prossimi appuntamenti
- Modifica/cancellazione entro soglie configurate
- Iscrizione a waitlist

### C4. Modulo Notifiche (lato utente)
- Ricezione promemoria, conferme, comunicazioni broadcast
- Gestione preferenze notifiche (granulari)

### C5. Modulo Recensioni (roadmap)
- Invio feedback post-servizio
- Visualizzazione valutazione media del tenant/operatore

## D. Area Trasversale (Core Platform Services)

### D1. Modulo Identity & Access Management (IAM)
- Gestione utenti multi-ruolo (Super Admin, Tenant Admin, Operatore, Cliente Finale)
- RBAC (controllo accessi basato su ruoli)
- Single Sign-On per dashboard, autenticazione social per app cliente

### D2. Modulo Multi-Tenancy Core
- Isolamento dati per tenant (data partitioning)
- Gestione configurazioni e feature flag per tenant/piano
- Vedi [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md)

### D3. Modulo Notification Engine
- Astrazione canali (push, email, SMS)
- Scheduler job (promemoria, campagne programmate)
- Gestione provider esterni (push notification service, email/SMS gateway)

### D4. Modulo Pagamenti (add-on, roadmap)
- Integrazione gateway di pagamento (acconti, pagamento servizio)
- Gestione rimborsi/storni

### D5. Modulo Audit & Compliance
- Log delle operazioni sensibili
- Registro dei trattamenti / consensi (GDPR)
- Strumenti per diritti dell'interessato (export, cancellazione)

### D6. Modulo Integrazioni (roadmap)
- API per integrazioni con gestionali esterni (fatturazione, cartelle cliniche)
- Webhook per eventi (nuova prenotazione, cancellazione)

## Mappa Modulo → Requisiti (riferimento)

| Modulo | Requisiti SRS correlati |
|---|---|
| A1 Tenant Management | RF-01..RF-05 |
| A4 Build & Release | RF-49..RF-51 |
| B1 Branding & White Label | RF-06..RF-12 |
| B3 Catalogo Servizi | RF-13..RF-17 |
| B4 Gestione Operatori | RF-18..RF-21 |
| B5 Orari e Calendario | RF-22..RF-25, RF-35 |
| B2/B5 Sedi | RF-26..RF-28 |
| C2 Catalogo & Prenotazione | RF-29..RF-36 |
| D3 Notification Engine | RF-37..RF-41 |
| B7 CRM Clienti | RF-42..RF-44 |
| C5 Recensioni | RF-45..RF-46 |
| B8 Reportistica | RF-47..RF-48 |
