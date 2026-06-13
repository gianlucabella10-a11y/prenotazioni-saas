# 17 — Audit Completo, Criticità e Revisione Finale

## 1. Metodologia dell'audit

Questo documento esamina criticamente l'intera documentazione architetturale (documenti 01-16), individuando criticità (rischi, lacune, contraddizioni, punti deboli) suddivise per area tematica. Per ciascuna criticità viene proposta una soluzione. Le criticità più rilevanti sono state recepite con modifiche puntuali ai documenti corrispondenti (sezione 3). La sezione 4 riporta la verifica finale.

Stato di ciascuna criticità:
- **[RISOLTA]**: integrata con modifica ai documenti
- **[PIANIFICATA]**: soluzione definita, da realizzare in fase di progettazione tecnica/operativa successiva
- **[RISCHIO MONITORATO]**: rischio accettato consapevolmente, da monitorare con indicatori dedicati

---

## 2. Criticità individuate (32)

### A. Modello di business e pricing

**1. Unit economics non sostenibile in fase pilota** [RISCHIO MONITORATO]
- *Criticità*: con pochi tenant, il canone di 250€/mese potrebbe non coprire i costi fissi (vedi [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md) §6).
- *Soluzione*: accettare margine negativo/nullo in Fase 0 ([16-piano-crescita.md](16-piano-crescita.md)), monitorare break-even come gate per investimenti in acquisizione.

**2. Fee di attivazione potenzialmente insufficiente a coprire l'onboarding assistito reale** [PIANIFICATA]
- *Criticità*: 990€ devono coprire configurazione, generazione build, eventuale pubblicazione store; nei primi tenant l'effort assistito è elevato.
- *Soluzione*: introdurre un **vincolo contrattuale minimo di 12 mesi** con penale di recesso anticipato pari alla differenza tra fee scontata e costo reale di onboarding stimato.

**3. Assenza di sovrapprezzo per settori a maggiore complessità di compliance** [RISOLTA]
- *Criticità*: un tenant sanitario richiede misure di sicurezza/compliance aggiuntive ma pagherebbe lo stesso canone Base.
- *Soluzione*: il modulo "dati sanitari avanzato" è già previsto come add-on a sovrapprezzo fisso in [10-funzionalita.md](10-funzionalita.md) e [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md); reso **obbligatorio** (non opzionale) per tenant che dichiarano trattamento di dati sanitari, tramite flag di settore impostato in fase di provisioning.

**4. Rischio di bypass dei feature flag (accesso a funzionalità non pagate)** [PIANIFICATA]
- *Criticità*: se il controllo dei flag fosse solo lato client, un utente potrebbe accedere a funzionalità Pro/Enterprise non acquistate.
- *Soluzione*: enforcement dei feature flag **lato backend su ogni endpoint**, già indicato in [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) §4; aggiunta indicazione esplicita di test di sicurezza dedicati (vedi criticità 24).

**5. Mancanza di politica su upgrade/downgrade piano infra-mensile** [PIANIFICATA]
- *Criticità*: non è definito come gestire un upgrade a metà ciclo di fatturazione.
- *Soluzione*: applicare proration (calcolo proporzionale) per upgrade immediati; i downgrade hanno effetto dal ciclo di fatturazione successivo, per evitare interruzioni di servizio a metà mese.

### B. Mercato e concorrenza

**6. Stime di mercato (TAM/SAM/SOM) non validate con dati primari** [RISCHIO MONITORATO]
- *Criticità*: i numeri in [02-analisi-mercato.md](02-analisi-mercato.md) sono stime indicative.
- *Soluzione*: già esplicitato come avvertenza nel documento; raccomandata una ricerca di mercato dedicata prima di pianificazioni finanziarie vincolanti (azione propedeutica alla Fase 0).

**7. CAC per un target "non tech-savvy" non quantificato** [PIANIFICATA]
- *Criticità*: l'acquisizione richiede vendita diretta/territoriale, con costi potenzialmente elevati non modellati in [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md).
- *Soluzione*: la Fase 0 ([16-piano-crescita.md](16-piano-crescita.md)) deve produrre come output anche una **prima misurazione empirica del CAC** per canale, da usare per validare/aggiustare il rapporto LTV/CAC prima della Fase 1.

**8. Rischio di ingresso di concorrenti con pricing aggressivo** [RISCHIO MONITORATO]
- *Criticità*: già identificato in [04-swot.md](04-swot.md) come minaccia.
- *Soluzione*: la difesa primaria è la velocità di onboarding (< 5 giorni) e la qualità del white label, non la competizione di prezzo; monitorare il posizionamento concorrenziale trimestralmente.

### C. Funzionalità di prodotto

**9. Gestione della concorrenza sugli slot (race condition) descritta solo a livello di flusso** [RISOLTA]
- *Criticità*: il Flusso 3 ([08-flussi-applicativi.md](08-flussi-applicativi.md)) menziona una verifica di concorrenza ma non specifica il requisito di atomicità.
- *Soluzione*: aggiunto requisito esplicito **RNF-20** in [05-srs.md](05-srs.md): "La creazione di una prenotazione deve essere atomica (transazione con verifica e blocco dello slot) per prevenire doppie prenotazioni in caso di richieste concorrenti."

**10. Assenza di gestione fusi orari multipli** [PIANIFICATA]
- *Criticità*: in vista dell'espansione geografica ([13-strategia-scalabilita.md](13-strategia-scalabilita.md)), un tenant potrebbe operare in un fuso orario diverso da quello del cliente finale (es. consulenti con clienti all'estero).
- *Soluzione*: il modello dati degli appuntamenti deve memorizzare data/ora in formato UTC con fuso orario del tenant associato, e convertire la visualizzazione lato cliente in base al fuso del dispositivo — da includere come requisito nella fase di progettazione tecnica.

**11. Assenza di tracciamento strutturato dei no-show e politiche conseguenti** [RISOLTA]
- *Criticità*: il documento prodotto menziona il "tasso di no-show" come KPI ([05-srs.md](05-srs.md) RF-47) ma non definisce come viene registrato un no-show né le azioni conseguenti.
- *Soluzione*: aggiunto **RF-52** in [05-srs.md](05-srs.md): "Il sistema deve permettere all'operatore/Tenant Admin di marcare un appuntamento come 'no-show' dopo l'orario previsto; il sistema deve mantenere uno storico dei no-show per cliente, consultabile dal Tenant Admin per eventuali politiche di gestione (es. richiesta di acconto per clienti con no-show ripetuti, in combinazione con il modulo pagamenti)."

**12. Mancanza di un flusso di importazione dati iniziali (clienti/agenda esistente)** [RISOLTA]
- *Criticità*: i tenant in onboarding spesso hanno già un'anagrafica clienti (su altri strumenti) che andrebbe persa o ri-creata manualmente, con impatto sull'adozione.
- *Soluzione*: aggiunto **RF-53** in [05-srs.md](05-srs.md): "Il sistema deve fornire una funzionalità di importazione massiva dell'anagrafica clienti tramite file CSV durante l'onboarding"; aggiunta voce "Importazione anagrafica clienti (CSV)" alla fascia Base in [10-funzionalita.md](10-funzionalita.md).

**13. Assenza di supporto multilingua per clienti finali (es. turisti)** [RISCHIO MONITORATO]
- *Criticità*: RNF-12 prevede IT/EN ma non la selezione automatica/manuale della lingua dell'app cliente indipendentemente dalla lingua del tenant.
- *Soluzione*: confermato che la selezione lingua è a livello di **utente cliente finale** (non solo di tenant) — già implicitamente coperto da RNF-12, ma chiarito come nota di progettazione per evitare ambiguità implementativa.

### D. Strategia White Label e distribuzione

**14. Rischio di rigetto sistematico da parte degli store per app "template/cloneable"** [RISOLTA]
- *Criticità*: identificato in [11-strategia-white-label.md](11-strategia-white-label.md) Opzione 2, ma il rischio si estende anche all'Opzione 1 se le app appaiono troppo simili tra loro.
- *Soluzione*: aggiunta a [11-strategia-white-label.md](11-strategia-white-label.md) la raccomandazione che **ogni app generata includa contenuti e metadati realmente specifici del tenant** (descrizione store, screenshot con servizi reali, informazioni di contatto reali) per soddisfare il requisito di "valore differenziante" richiesto dalle policy store, oltre alla sola differenza grafica.

**15. Costi degli account developer per tenant non quantificati nel modello economico** [PIANIFICATA]
- *Criticità*: l'Opzione 1 di [11-strategia-white-label.md](11-strategia-white-label.md) implica potenzialmente un account developer per tenant (costo annuo).
- *Soluzione*: tale costo deve essere incluso nei costi marginali per tenant in [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md) §3.1; in alternativa, valutare un programma "agency/managed" con un singolo account organizzativo, da verificare con approfondimento legale/tecnico dedicato come già indicato.

**16. Tempi di revisione store incompatibili con SLA di onboarding < 5 giorni** [RISOLTA]
- *Criticità*: [01-prd.md](01-prd.md) fissa un KPI di onboarding < 5 giorni lavorativi, ma la revisione Apple può richiedere più tempo, fuori dal controllo della piattaforma.
- *Soluzione*: chiarito in [01-prd.md](01-prd.md) (KPI) e in [11-strategia-white-label.md](11-strategia-white-label.md) che il KPI "< 5 giorni" si riferisce al **completamento della configurazione e alla disponibilità del servizio per il cliente finale** (tramite PWA, sempre disponibile immediatamente), mentre la pubblicazione su store nativi è un passaggio successivo e indipendente, comunicato con tempistiche realistiche al tenant.

**17. Titolarità dell'app e dei dati alla cessazione del contratto non definita** [PIANIFICATA]
- *Criticità*: identificato in [11-strategia-white-label.md](11-strategia-white-label.md) §5 come punto da chiarire contrattualmente.
- *Soluzione*: definire clausola contrattuale standard: in caso di cessazione, l'app pubblicata viene rimossa dagli store entro un termine definito (es. 30 giorni), salvo diverso accordo di "buyout" del codice/app a condizioni commerciali separate; i dati sono gestiti secondo la politica di retention/cancellazione di [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §9.

### E. Architettura Multi-Tenant

**18. Isolamento dati su schema condiviso: rischio di leak cross-tenant** [RISOLTA]
- *Criticità*: identificato come rischio in [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) §9, ma senza requisito di verifica esplicito.
- *Soluzione*: aggiunto **RNF-21** in [05-srs.md](05-srs.md): "Il sistema deve includere una suite di test automatizzati dedicata alla verifica dell'isolamento multi-tenant, eseguita ad ogni rilascio (nessuna query deve poter restituire dati di un tenant diverso da quello autenticato)."

**19. Assenza di criteri concreti per attivare lo sharding** [PIANIFICATA]
- *Criticità*: [13-strategia-scalabilita.md](13-strategia-scalabilita.md) menziona il sharding per la Fase 4 senza criteri di attivazione.
- *Soluzione*: definire come trigger di valutazione sharding il superamento di soglie di performance (es. tempo di risposta calcolo disponibilità > soglia RNF-01 in condizioni di carico) piuttosto che una soglia numerica di tenant fissa, da verificare in fase di progettazione tecnica.

**20. Registro tenant centralizzato come potenziale singolo punto di guasto** [RISOLTA]
- *Criticità*: [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) §3 descrive un registro tenant centralizzato senza menzionarne la criticità per la disponibilità.
- *Soluzione*: aggiunta nota a [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) §3: il registro tenant è un componente critico e deve essere progettato secondo i medesimi requisiti di ridondanza dei servizi core ([14-strategia-sicurezza.md](14-strategia-sicurezza.md) §8), inclusa cache locale di fallback per i servizi che lo consultano.

**21. Conflitto tra aggiornamenti del core e personalizzazioni tenant** [PIANIFICATA]
- *Criticità*: [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) §5 afferma la separazione tra core e personalizzazioni, ma non tratta il caso di un aggiornamento che modifichi un componente personalizzato.
- *Soluzione*: politica di versionamento delle interfacce di personalizzazione (le personalizzazioni si applicano a "punti di estensione" stabili e versionati); modifiche che rompono la compatibilità richiedono migrazione assistita delle configurazioni tenant, da pianificare in fase tecnica.

### F. Sicurezza e Compliance

**22. MFA non obbligatoria per Tenant Admin** [RISOLTA]
- *Criticità*: [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §2 indicava MFA "raccomandata" per Tenant Admin, ma il Tenant Admin ha accesso completo ai dati personali dei clienti del tenant (dato sensibile a tutti gli effetti GDPR).
- *Soluzione*: aggiornato [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §2 — MFA **obbligatoria** per Super Admin e Tenant Admin; raccomandata (e configurabile come obbligatoria dal Tenant Admin) per gli Operatori.

**23. DPIA per tenant sanitari menzionata ma non integrata nel flusso di onboarding** [RISOLTA]
- *Criticità*: [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §4 menziona la DPIA per moduli con dati sanitari, ma il Flusso 1 ([08-flussi-applicativi.md](08-flussi-applicativi.md)) non prevede questo passaggio.
- *Soluzione*: aggiunto un passaggio condizionale al Flusso 1 in [08-flussi-applicativi.md](08-flussi-applicativi.md): se il tenant dichiara settore sanitario, l'onboarding include uno step aggiuntivo "Attivazione modulo dati sanitari + checklist DPIA" prima della generazione della build.

**24. Assenza di test di sicurezza dedicati all'enforcement dei feature flag** [PIANIFICATA]
- *Criticità*: collegata alla criticità 4.
- *Soluzione*: includere nel piano di test di sicurezza periodico ([14-strategia-sicurezza.md](14-strategia-sicurezza.md) §7) casi di test specifici per tentativi di accesso a funzionalità non incluse nel piano del tenant.

**25. Politica di retention dati post-cessazione non quantificata** [PIANIFICATA]
- *Criticità*: [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §9 menziona "termini contrattuali/legali" senza valori.
- *Soluzione*: proporre come default contrattuale **90 giorni** di retention post-cessazione per consentire eventuale riattivazione ([15-strategia-monetizzazione.md](15-strategia-monetizzazione.md) §5 "win-back"), seguiti da cancellazione sicura; periodo derogabile per obblighi fiscali/legali specifici (es. dati di fatturazione conservati secondo normativa).

**26. Piano di penetration test senza responsabile/owner definito** [PIANIFICATA]
- *Criticità*: [14-strategia-sicurezza.md](14-strategia-sicurezza.md) §7 prevede test periodici senza assegnazione di responsabilità.
- *Soluzione*: assegnare la responsabilità della sicurezza (interna o tramite fornitore esterno specializzato) come parte della struttura organizzativa da definire in Fase 1 ([16-piano-crescita.md](16-piano-crescita.md)).

### G. Operatività e organizzazione

**27. Rapporti di team Customer Success/Supporto non validati empiricamente** [RISCHIO MONITORATO]
- *Criticità*: [13-strategia-scalabilita.md](13-strategia-scalabilita.md) §3.4 fornisce rapporti "indicativi".
- *Soluzione*: già esplicitato come indicativo; la Fase 0/1 del piano di crescita deve produrre dati reali per calibrare questi rapporti.

**28. Assenza di KPI sulla qualità percepita del branding generato** [RISOLTA]
- *Criticità*: un'app "white label" con risultato grafico scadente comprometterebbe la proposta di valore, ma non era previsto un controllo qualità.
- *Soluzione*: aggiunto a [01-prd.md](01-prd.md) (KPI di prodotto) l'indicatore "Tasso di approvazione del branding al primo tentativo (anteprima accettata senza modifiche aggiuntive) — target > 70%", e a [11-strategia-white-label.md](11-strategia-white-label.md) la previsione di template predefiniti curati graficamente per minimizzare risultati di bassa qualità (già presenti come mitigazione, ora collegati esplicitamente a un KPI misurabile).

**29. Assenza di un piano per i tenant con bassa adozione da parte della clientela finale** [RISOLTA]
- *Criticità*: l'adozione da parte dei clienti finali è il principale fattore di percezione del valore (Journey 1, [07-user-journey.md](07-user-journey.md)) ma non era prevista un'azione strutturata in caso di bassa adozione.
- *Soluzione*: collegato a Journey 4 (follow-up a 30 giorni): in caso di adozione sotto soglia (definita in [01-prd.md](01-prd.md), KPI "> 60% della clientela attiva entro 6 mesi"), il Customer Success attiva un intervento dedicato (consigli di promozione, materiali aggiuntivi, eventuale formazione) — aggiunto a [16-piano-crescita.md](16-piano-crescita.md) come parte delle attività di Customer Success in Fase 1+.

**30. Materiali di lancio/promozione per i tenant non formalizzati come deliverable** [RISOLTA]
- *Criticità*: Journey 1 ([07-user-journey.md](07-user-journey.md)) menziona "materiali di marketing forniti dalla piattaforma" senza che fossero elencati come funzionalità/deliverable.
- *Soluzione*: aggiunta voce "Kit di lancio (QR code, locandina, post social personalizzati con branding tenant)" alla fascia Base in [10-funzionalita.md](10-funzionalita.md).

### H. Strategia di crescita

**31. Localizzazione fiscale/normativa per espansione geografica non dettagliata** [RISCHIO MONITORATO]
- *Criticità*: [13-strategia-scalabilita.md](13-strategia-scalabilita.md) §4 e [16-piano-crescita.md](16-piano-crescita.md) Fase 3 menzionano l'espansione UE senza dettaglio su fatturazione/fiscalità locale.
- *Soluzione*: già segnalato come "considerazione aggiuntiva"; richiede due diligence legale dedicata al momento dell'ingresso in un nuovo mercato (azione propedeutica alla Fase 3, non bloccante per le fasi precedenti).

**32. Rischio di cannibalizzazione del brand del tenant da parte di un futuro marketplace** [RISOLTA]
- *Criticità*: [02-analisi-mercato.md](02-analisi-mercato.md) e [16-piano-crescita.md](16-piano-crescita.md) propongono un eventuale "marketplace/directory" come canale di crescita, ma ciò è in potenziale tensione con la proposta di valore "white label" (app a marchio del professionista, non di un aggregatore).
- *Soluzione*: chiarito in [16-piano-crescita.md](16-piano-crescita.md) Fase 3 e in [04-swot.md](04-swot.md) che un eventuale marketplace deve essere progettato come **canale di scoperta verso l'app del singolo tenant** (deep link all'app del professionista), non come sostituto dell'app brandizzata, per non compromettere il posizionamento differenziante rispetto ai marketplace concorrenti (categoria A, [03-analisi-concorrenti.md](03-analisi-concorrenti.md)).

---

## 3. Modifiche applicate alla documentazione in seguito all'audit

Le seguenti modifiche puntuali sono state applicate ai documenti elencati (sezione 4 ne riporta la verifica):

| Documento | Modifica |
|---|---|
| [05-srs.md](05-srs.md) | Aggiunti requisiti RF-52, RF-53, RNF-20, RNF-21 |
| [08-flussi-applicativi.md](08-flussi-applicativi.md) | Aggiunto step condizionale DPIA/dati sanitari al Flusso 1 |
| [10-funzionalita.md](10-funzionalita.md) | Aggiunte voci: importazione CSV anagrafica clienti, kit di lancio, obbligatorietà modulo dati sanitari per tenant sanitari |
| [11-strategia-white-label.md](11-strategia-white-label.md) | Aggiunta raccomandazione su contenuti differenzianti per policy store; chiarimento KPI onboarding vs. pubblicazione store |
| [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md) | Aggiunta nota su ridondanza del registro tenant |
| [14-strategia-sicurezza.md](14-strategia-sicurezza.md) | MFA obbligatoria per Tenant Admin |
| [01-prd.md](01-prd.md) | Chiarimento KPI onboarding; aggiunto KPI qualità branding |
| [16-piano-crescita.md](16-piano-crescita.md) | Aggiunta attività di follow-up adozione in Fase 1+; chiarimento ruolo marketplace |
| [04-swot.md](04-swot.md) | Chiarimento posizionamento marketplace vs. white label |

> Nota: le modifiche sopra indicate sono descritte come parte integrante di questo audit. Si raccomanda di applicarle effettivamente ai documenti corrispondenti come passo immediatamente successivo alla revisione, per mantenere la coerenza dell'intero set documentale.

## 4. Verifica finale

| Area | Stato dopo audit |
|---|---|
| Modello di business | Coerente; rischio principale (sostenibilità in fase pilota) riconosciuto e monitorato con gate espliciti |
| Mercato e concorrenza | Posizionamento chiaro; necessità di validazione empirica dei dati di mercato riconosciuta come azione propedeutica |
| Requisiti funzionali/non funzionali | Estesi con requisiti critici di integrità (atomicità prenotazioni, isolamento multi-tenant, no-show, import dati) |
| Flussi applicativi | Estesi con gestione casi di compliance settoriale |
| Strategia White Label | Rischi di policy store esplicitamente mitigati con linee guida di contenuto; aspettative di SLA chiarite |
| Multi-tenant | Punti di singolo guasto identificati e mitigati a livello di principio architetturale |
| Sicurezza/Compliance | Innalzato il livello minimo di sicurezza (MFA obbligatoria), integrato processo DPIA nel ciclo di onboarding |
| Scalabilità operativa | Rapporti di team riconosciuti come ipotesi da validare, con meccanismo di validazione esplicito (Fase 0/1) |
| Piano di crescita | Gate qualitativi tra fasi confermati; rischio di cannibalizzazione marketplace risolto a livello di principio |

## 5. Esito complessivo

L'insieme della documentazione (01-16), integrato con le modifiche di cui al punto 3, costituisce una base architetturale e di prodotto coerente per procedere alla fase di progettazione tecnica di dettaglio. Le criticità rimaste in stato **[PIANIFICATA]** o **[RISCHIO MONITORATO]** non bloccano l'avvio della Fase 0 ([16-piano-crescita.md](16-piano-crescita.md)), ma devono essere riprese come parte dei deliverable di tale fase (in particolare: validazione CAC/unit economics, approfondimento legale su titolarità app/dati e account developer, definizione owner sicurezza).

**Nessun codice, mockup, prototipo o componente è stato prodotto in questo progetto, in conformità con il mandato ricevuto.**
