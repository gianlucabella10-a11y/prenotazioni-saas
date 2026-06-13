# 07 — User Journey

## Journey 1 — Acquisizione e Onboarding di un nuovo Tenant (es. "Marco", barbershop)

| Fase | Azione | Touchpoint | Stato emotivo |
|---|---|---|---|
| 1. Consapevolezza | Marco viene contattato da un agente commerciale / vede una pubblicità geolocalizzata | Telefono, social, evento di settore | Curiosità |
| 2. Considerazione | Vede una demo dell'app con un esempio brandizzato simile alla sua attività | Demo live o video | Interesse, valutazione costo/beneficio |
| 3. Decisione | Firma il contratto, paga la fee di attivazione (990€) | Modulo contrattuale digitale, pagamento online | Impegno |
| 4. Raccolta asset | Carica logo, sceglie colori, nome app tramite wizard guidato | Dashboard Tenant Admin — wizard onboarding | Coinvolgimento, possibile incertezza su scelte grafiche |
| 5. Configurazione iniziale | Inserisce servizi, prezzi, orari, operatori | Dashboard Tenant Admin | Concentrazione, eventuale richiesta di supporto |
| 6. Generazione app | Il sistema genera la build white label e la invia in revisione agli store (o pubblica come PWA) | Pipeline automatizzata + notifica email | Attesa |
| 7. Pubblicazione | L'app è disponibile su App Store/Play Store o come PWA installabile | Notifica "App pubblicata" | Entusiasmo |
| 8. Promozione | Marco comunica ai clienti (social, locandina in negozio con QR code) il nuovo canale di prenotazione | Materiali di marketing forniti dalla piattaforma | Orgoglio, attesa risultati |
| 9. Adozione | I primi clienti scaricano l'app e prenotano | App end-user | Soddisfazione crescente |
| 10. Gestione continuativa | Marco monitora prenotazioni, gestisce orari/eccezioni, riceve report mensile | Dashboard Tenant Admin | Routine, fiducia |

### Punti critici del journey (da mitigare nel design)
- **Fase 4**: il rischio di abbandono per "scelta grafica" troppo complessa — mitigato con template predefiniti e wizard guidato (vedi [11-strategia-white-label.md](11-strategia-white-label.md))
- **Fase 6**: i tempi di revisione degli store (specialmente Apple) possono richiedere giorni — gestiti con comunicazione proattiva dello stato
- **Fase 8-9**: l'adozione da parte della clientela finale è il fattore di successo percepito più importante — la piattaforma deve fornire materiali di promozione pronti all'uso (QR code, locandine, post social)

## Journey 2 — Prenotazione di un appuntamento (Cliente Finale, "Sara")

| Fase | Azione | Touchpoint | Stato emotivo |
|---|---|---|---|
| 1. Download/accesso | Scarica l'app del salone (da QR code in negozio o link condiviso) | App store / PWA | Curiosità |
| 2. Registrazione | Crea account (email, telefono o social login) | App — schermata onboarding | Neutro, attenzione a velocità |
| 3. Esplorazione | Visualizza i servizi disponibili, prezzi, durate | App — catalogo servizi | Valutazione |
| 4. Selezione | Scegli servizio, eventualmente operatore preferito | App — selezione servizio/operatore | Decisione |
| 5. Scelta slot | Visualizza il calendario con gli slot disponibili e ne seleziona uno | App — calendario disponibilità | Attesa rapida (deve essere istantanea) |
| 6. Conferma | Riceve riepilogo e conferma la prenotazione | App — schermata di riepilogo | Soddisfazione |
| 7. Notifica conferma | Riceve notifica push/email di conferma immediata | Notifica | Rassicurazione |
| 8. Promemoria | Riceve promemoria automatico (es. 24h prima) | Notifica push | Utilità percepita |
| 9. Gestione | Eventualmente modifica/cancella l'appuntamento dall'app | App — sezione "I miei appuntamenti" | Controllo |
| 10. Post-servizio | Riceve richiesta di recensione/feedback (roadmap) | Notifica/app | Coinvolgimento |

### Punti critici del journey
- **Fase 5**: la velocità e correttezza del calcolo di disponibilità è cruciale — uno slot mostrato come disponibile ma non prenotabile (race condition) genera forte insoddisfazione (vedi RF-35 in [05-srs.md](05-srs.md))
- **Fase 2**: la registrazione deve essere il più rapida possibile (social login) per evitare abbandoni
- **Fase 8**: l'eccessiva frequenza di notifiche (specialmente promozionali) può portare l'utente a disattivarle — serve un controllo granulare delle preferenze

## Journey 3 — Gestione quotidiana dell'agenda (Operatore, "Luca" fisioterapista)

| Fase | Azione | Touchpoint | Stato emotivo |
|---|---|---|---|
| 1. Login | Accede con credenziali fornite dal Tenant Admin | App/dashboard operatore | Routine |
| 2. Visualizzazione agenda | Controlla gli appuntamenti del giorno | Dashboard operatore — vista agenda | Pianificazione |
| 3. Gestione imprevisti | Segnala un'indisponibilità improvvisa (es. malattia) | Dashboard — sezione disponibilità | Urgenza |
| 4. Notifica automatica | Il sistema notifica i clienti con appuntamenti impattati e propone riprogrammazione | Sistema automatizzato | Delega della comunicazione |
| 5. Gestione pacchetti | Visualizza le sedute residue di un pacchetto trattamento per un paziente | Dashboard — scheda cliente | Controllo clinico |
| 6. Note post-seduta | Inserisce note (private/condivise secondo permessi) sulla seduta appena conclusa | Dashboard — scheda cliente | Documentazione |

## Journey 4 — Provisioning di un nuovo Tenant (Customer Success, "Anna")

| Fase | Azione | Touchpoint | Stato emotivo |
|---|---|---|---|
| 1. Creazione tenant | Inserisce dati anagrafici/contrattuali del nuovo cliente nel pannello Super Admin | Pannello Super Admin | Operativo |
| 2. Assegnazione piano | Seleziona piano e moduli add-on attivi | Pannello Super Admin | Operativo |
| 3. Invito Tenant Admin | Il sistema invia credenziali di accesso al titolare | Email automatica | Delega |
| 4. Monitoraggio configurazione | Verifica lo stato di completamento del wizard di onboarding del tenant | Dashboard monitoraggio | Proattività |
| 5. Supporto | Se il tenant è bloccato, Anna interviene (chat/telefono) | Strumenti di supporto integrati | Assistenza |
| 6. Avvio pipeline build | Avvia/approva la generazione della build white label | Pannello Super Admin | Controllo qualità |
| 7. Monitoraggio pubblicazione | Segue lo stato di revisione sugli store | Dashboard stato build | Attesa |
| 8. Go-live | Conferma al tenant l'avvenuta pubblicazione, invia materiali di lancio | Email/notifica | Chiusura positiva |
| 9. Follow-up | Dopo 30 giorni, verifica l'adozione (numero download/prenotazioni) e propone interventi se bassa | Dashboard analytics | Retention proattiva |
