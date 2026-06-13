# 11 — Strategia White Label

## 1. Obiettivo

Permettere a ciascun tenant di avere un'app percepita dai propri clienti come "la propria app", configurabile interamente da dashboard, senza intervento sul codice da parte della software house per ogni singolo cliente.

## 2. Elementi configurabili per tenant

| Elemento | Descrizione | Vincoli tecnici tipici |
|---|---|---|
| Nome app | Nome visualizzato sotto l'icona e nello store | Limiti di lunghezza imposti dagli store |
| Logo | Utilizzato in splash screen, header dashboard, email | Formati vettoriali/raster multipli, sfondo trasparente |
| Icona app | Icona visualizzata su home screen del dispositivo | Dimensioni multiple richieste (iOS: set di size; Android: adaptive icon con layer) |
| Splash screen | Schermata di avvio | Immagine e/o colore di sfondo, dimensioni multiple per densità schermo |
| Palette colori | Colore primario, secondario, colori di stato (successo/errore/avviso) | Verifica contrasto minimo (accessibilità) |
| Tema (chiaro/scuro) | Variante grafica | Opzionale, fascia Pro |
| Contenuti testuali | Tagline, messaggi di benvenuto, footer legale | Localizzazione IT/EN |
| Informazioni legali | P.IVA, ragione sociale, privacy policy, termini di servizio | Generati/compilati da template legale + dati tenant |

## 3. Architetture possibili per la distribuzione white label

Esistono tre approcci principali, con trade-off differenti. Il documento ne descrive le caratteristiche affinché la fase di progettazione tecnica (fuori scope di questo documento) possa scegliere la combinazione più adatta.

### Opzione 1 — Build nativa dedicata per tenant (one app per tenant)
- Ogni tenant ha una propria app pubblicata separatamente su App Store/Play Store, con proprio bundle ID
- **Vantaggi**: massima personalizzazione (icona, nome reali sullo store), percezione di "app propria" massima, possibilità di ASO (App Store Optimization) per singolo tenant
- **Svantaggi**: gestione di migliaia di account sviluppatore/app distinte; ogni aggiornamento del core richiede ri-build e ri-pubblicazione di tutte le app; tempi di revisione store per ogni nuova app; costi di account developer per tenant (Apple Developer Program ha costo annuo per account)
- **Mitigazioni**: pipeline CI/CD completamente automatizzata (Flusso 2, [08-flussi-applicativi.md](08-flussi-applicativi.md)); utilizzo di programmi enterprise/developer "managed" se disponibili per pubblicazione massiva; pianificazione di rilasci batch per aggiornamenti core
- **Mitigazione rischio policy "app duplicate/cloneable"**: per soddisfare il requisito di "valore differenziante" richiesto dalle policy store anche per app generate da template, ogni build deve includere contenuti realmente specifici del tenant (descrizione store, screenshot con servizi/orari reali, dati di contatto reali, recensioni/branding propri), non solo differenze grafiche

### Opzione 2 — App container multi-tenant (single app, dynamic branding)
- Una singola app pubblicata sugli store, che al primo avvio (o tramite codice/link di attivazione) carica da remoto la configurazione del tenant (colori, loghi, nome interno) — l'app "diventa" quella del tenant dopo il primo login/configurazione
- **Vantaggi**: un solo ciclo di pubblicazione/aggiornamento per tutti i tenant; nessun costo di account developer per tenant; aggiornamenti istantanei
- **Svantaggi**: l'app non appare con nome/icona proprio del tenant sullo store (limite percepito per il valore "white label" promesso al cliente); le linee guida di Apple/Google **vietano esplicitamente le "app shell" generiche che si limitano a caricare contenuti diversi senza valore differenziante** — questa opzione da sola non è conforme alle policy se proposta come "la tua app"
- **Utilizzo consigliato**: come componente interno (es. app per gli Operatori/Tenant Admin, non distribuita pubblicamente con marchio del cliente finale) oppure come fallback PWA

### Opzione 3 — PWA (Progressive Web App) installabile come ponte verso le build native
- L'app del cliente finale è inizialmente una PWA installabile (icona su home screen, notifiche push dove supportato), brandizzata per tenant tramite sottodominio/configurazione
- **Vantaggi**: attivazione immediata (nessuna revisione store), costo marginale quasi nullo, ottimo per la fase MVP/validazione
- **Svantaggi**: funzionalità più limitate su iOS (in particolare le notifiche push storicamente limitate, anche se in miglioramento), percezione "meno premium" rispetto a un'app store nativa

### Strategia consigliata (roadmap a fasi)

1. **Fase MVP**: Opzione 3 (PWA) per validare rapidamente il modello con i primi tenant pilota, a basso costo di distribuzione
2. **Fase di scala (Opzione 1 automatizzata)**: introduzione della pipeline di build nativa automatizzata per tenant che richiedono presenza reale sugli store, a partire dai tenant di fascia superiore o su richiesta
3. **Valutazione continua**: monitorare evoluzione delle policy store relative a soluzioni "template-based" e ai programmi per sviluppatori multi-app, adeguando il mix Opzione 1 / Opzione 3 in base a costi e vincoli regolatori

> Nota: questa è una decisione architetturale di alto impatto che richiede approfondimento tecnico/legale dedicato (revisione delle linee guida store aggiornate al momento dell'implementazione) prima dell'avvio dello sviluppo.

> **Chiarimento KPI di onboarding** (vedi [01-prd.md](01-prd.md)): il target "< 5 giorni lavorativi" si riferisce al completamento della configurazione e alla disponibilità del servizio per il cliente finale tramite PWA (sempre attivabile immediatamente). La pubblicazione su store nativi (Opzione 1) è un passaggio successivo e indipendente, con tempistiche di revisione non controllabili dalla piattaforma, comunicate al tenant in modo trasparente.

## 4. Processo di generazione asset

- **Wizard guidato**: il Tenant Admin carica un logo in formato vettoriale (preferibile) o raster ad alta risoluzione; il sistema genera automaticamente i formati derivati (icone multi-size, splash screen multi-densità)
- **Template predefiniti**: per tenant che non dispongono di asset grafici propri, la piattaforma offre un set di template di branding predefiniti (combinazioni di palette e layout) selezionabili e personalizzabili, riducendo la barriera per i clienti meno "tech-savvy" (Persona "Marco", [06-user-personas.md](06-user-personas.md))
- **Validazione automatica**: controllo dimensioni minime, formati, contrasto colori prima di procedere alla build

## 5. Gestione degli account store

- Valutare l'utilizzo di un **account sviluppatore Apple di tipo "Organization"** della software house, con possibilità di gestione centralizzata delle app dei tenant (modello "agency"), oppure la creazione di account dedicati per i tenant di fascia enterprise che lo richiedano
- Per Android, Google Play consente la gestione di più app sotto un singolo account developer più semplicemente rispetto ad Apple, ma con limiti di policy da verificare per app "duplicate/template"
- Definire un processo contrattuale chiaro su **titolarità dell'app e dei dati** in caso di cessazione del rapporto con la piattaforma (vedi anche [17-audit-revisione.md](17-audit-revisione.md))

## 6. Aggiornamenti e versioning

- Il **core applicativo** (logica, funzionalità) è condiviso tra tutti i tenant tramite un template versionato
- Gli **asset di branding** sono dati di configurazione per-tenant, separati dal codice
- Gli aggiornamenti del core vengono propagati tramite ri-build automatizzata; le personalizzazioni grafiche del tenant vengono automaticamente riapplicate alla nuova build
- Politiche di rilascio: rilasci batch programmati (es. mensili) per gli aggiornamenti non critici; rilasci immediati per fix di sicurezza, con comunicazione ai tenant
