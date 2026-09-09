# 01 — Product Requirements Document (PRD)

## 1. Visione di prodotto

Costruire una piattaforma SaaS White Label e Multi-Tenant che consenta a un'organizzazione (la "Software House" / operatore della piattaforma) di generare, distribuire e mantenere applicazioni mobili di prenotazione appuntamenti personalizzate ("brandizzate") per professionisti e attività commerciali, senza che ciascun cliente richieda sviluppo software dedicato.

Ogni cliente (tenant) ottiene:
- Una propria app mobile (iOS/Android) con nome, logo, icona, splash screen e palette colori personalizzati
- Un pannello di prenotazione per i propri clienti finali
- Una dashboard di gestione per configurare servizi, operatori, orari, sede(i), notifiche e contenuti
- Un set di funzionalità operative (prenotazioni, promemoria, pagamenti, recensioni, fidelizzazione) attivabili in base al piano

## 2. Problema da risolvere

I professionisti che lavorano su appuntamento (barbieri, parrucchieri, estetisti, dentisti, fisioterapisti, consulenti, ecc.) affrontano oggi:

- **Gestione manuale o frammentata degli appuntamenti**: agende cartacee, fogli Excel, chat WhatsApp, telefonate
- **Mancanza di immagine digitale professionale**: assenza di un'app propria, percepita come elemento di valore e fidelizzazione dalla clientela moderna
- **Costi e tempi di sviluppo proibitivi**: un'app nativa personalizzata costerebbe decine di migliaia di euro e mesi di sviluppo
- **No-show e cancellazioni**: assenza di promemoria automatici, con perdita di fatturato
- **Difficoltà nella fidelizzazione**: assenza di strumenti di marketing diretto (notifiche push, promozioni, programmi fedeltà)
- **Dispersione su piattaforme generaliste**: marketplace di prenotazione (booking aggregator) che intermediano la relazione con il cliente finale, applicano commissioni e diluiscono il brand del professionista

## 3. Soluzione proposta

Una piattaforma che:

1. Fornisce a ogni professionista un'app mobile **brandizzata a proprio nome**, generata da un **template configurabile** tramite dashboard
2. Permette al cliente finale di prenotare, gestire e ricevere promemoria per i propri appuntamenti
3. Permette al professionista di gestire in autonomia: servizi offerti, listino prezzi, operatori/risorse, disponibilità/orari, sedi, comunicazioni
4. È gestita centralmente dalla software house tramite un'**architettura multi-tenant**, che consente di onboardare nuovi clienti in tempi rapidi (giorni, non mesi) e di mantenere un unico codebase

## 4. Obiettivi di business

| Obiettivo | Descrizione | Orizzonte |
|---|---|---|
| Validazione modello | Onboarding primi 20-30 clienti pilota, validazione pricing e processo di attivazione | 0-6 mesi |
| Standardizzazione onboarding | Riduzione tempo di attivazione di un nuovo tenant a < 5 giorni lavorativi | 6-12 mesi |
| Espansione verticale | Estensione del catalogo funzionalità per verticali specifici (sanitario, beauty, fitness) | 12-24 mesi |
| Scala | Raggiungimento di 10.000 tenant attivi | 36-60 mesi |
| Diversificazione ricavi | Introduzione di moduli add-on a pagamento (pagamenti online, marketing automation, multi-sede) | 18-36 mesi |

## 5. Modello di business (riepilogo)

- **Fee di attivazione**: 990 € una tantum per tenant — copre setup iniziale, configurazione branding, pubblicazione app sugli store (o attivazione su infrastruttura PWA/wrapper)
- **Canone ricorrente**: 250 €/mese + IVA — copre hosting, manutenzione, aggiornamenti, supporto, infrastruttura notifiche/SMS/email, backup
- **Add-on opzionali** (vedi [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md)): pagamenti online, multi-sede, marketing automation, integrazioni gestionali di settore (es. cartelle cliniche)

## 6. Scope del progetto (MVP)

### In scope (MVP)
- Dashboard di amministrazione tenant (self-service) per configurazione branding, servizi, operatori, orari, sedi
- App mobile end-user (cliente finale) per ricerca servizi, prenotazione, gestione appuntamento, notifiche
- App/portale gestionale per il professionista (gestione agenda, clienti, servizi)
- Pannello super-admin (software house) per provisioning tenant, monitoraggio, billing
- Sistema di notifiche (push, email, SMS opzionale) per promemoria e conferme
- Sistema di branding white label: logo, icona, splash screen, palette colori, nome app
- Generazione automatizzata build app (pipeline CI/CD per build white label)

### Fuori scope (MVP) — pianificato per fasi successive
- Pagamenti online integrati (gateway pagamento, depositi cauzionali)
- Marketplace/discovery multi-tenant (ricerca professionisti da un'unica app aggregatore)
- Programmi fedeltà avanzati, gift card, referral
- Integrazioni con sistemi gestionali di terze parti (cartelle cliniche, fatturazione elettronica)
- Funzionalità di telemedicina/videoconsulto
- Multi-lingua oltre IT/EN

## 7. Stakeholder

| Ruolo | Descrizione | Interesse primario |
|---|---|---|
| Software House (operatore piattaforma) | Proprietaria e gestore della piattaforma | Ricavi ricorrenti, scalabilità, basso costo di gestione per tenant |
| Tenant Owner (titolare attività) | Cliente che acquista il servizio white label | App professionale, semplicità di gestione, fidelizzazione clienti |
| Staff/Operatori del tenant | Collaboratori del professionista (dipendenti, altri operatori) | Visibilità agenda personale, gestione propri appuntamenti |
| Cliente finale (end-user) | Utente che prenota gli appuntamenti | Facilità di prenotazione, promemoria, esperienza fluida |
| Team di supporto/onboarding | Personale della software house | Strumenti di provisioning, assistenza, monitoraggio |

## 8. KPI di prodotto

| KPI | Target indicativo |
|---|---|
| Tempo medio di onboarding nuovo tenant (configurazione completa e servizio attivo via PWA — la pubblicazione su store nativi segue tempistiche indipendenti, vedi [11-strategia-white-label.md](11-strategia-white-label.md)) | < 5 giorni lavorativi |
| Tasso di attivazione (tenant che completano setup) | > 85% |
| Tasso di churn mensile | < 3% |
| NPS tenant | > 40 |
| Tasso di adozione app da parte dei clienti finali (per tenant) | > 60% della clientela attiva entro 6 mesi |
| Tasso di approvazione del branding al primo tentativo (anteprima accettata senza richieste di modifica aggiuntive) | > 70% |
| Uptime piattaforma | > 99.9% |
| Tempo medio di prenotazione (end-user) | < 90 secondi |

## 9. Vincoli e assunzioni

- L'app per il cliente finale deve essere disponibile sia su iOS che su Android (o, in alternativa MVP, tramite PWA installabile, con build native a roadmap)
- Ogni tenant ha un proprio dominio/sottodominio per la dashboard gestionale (multi-tenant logico, white label brand)
- La pubblicazione su App Store/Play Store di ogni singola app white label richiede account sviluppatore dedicato per tenant (o gestione tramite organizzazione enterprise Apple/Google) — vedi approfondimento in [11-strategia-white-label.md](11-strategia-white-label.md)
- La piattaforma deve essere conforme al GDPR (clienti UE) e, per i tenant sanitari, alle normative di settore su dati sanitari (categoria particolare di dati personali)
