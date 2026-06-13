# 16 — Piano di Crescita fino a 10.000 Clienti

## 1. Approccio generale

Il piano è strutturato in fasi, ciascuna con obiettivi di numero di tenant, focus operativo prevalente, e prerequisiti tecnici/organizzativi da soddisfare prima di passare alla fase successiva. La crescita non è puramente commerciale: ogni fase richiede che la **scalabilità tecnica** ([13-strategia-scalabilita.md](13-strategia-scalabilita.md)) e **operativa** siano pronte per il volume successivo.

## 2. Fasi di crescita

### Fase 0 — Validazione (0 → 30 tenant)
**Obiettivo**: validare prodotto, processo di onboarding, pricing.

- Acquisizione tramite rete diretta/relazioni personali, primi clienti "pilota" anche a condizioni agevolate in cambio di feedback
- Onboarding altamente assistito (anche manuale dove necessario)
- Raccolta feedback strutturato su: facilità di configurazione, qualità del branding generato, percezione di valore da parte della clientela finale
- Prerequisito tecnico: MVP funzionante (moduli B e C di [09-moduli.md](09-moduli.md)), pipeline build almeno in modalità PWA ([11-strategia-white-label.md](11-strategia-white-label.md))

**Output**: lista di criticità di prodotto risolte (vedi anche [17-audit-revisione.md](17-audit-revisione.md)), pricing validato o aggiustato, casi studio/testimonianze per la fase successiva

### Fase 1 — Early Growth (30 → 300 tenant)
**Obiettivo**: costruire un motore di acquisizione ripetibile.

- Introduzione di un piccolo team commerciale/agenti territoriali con schema di commissione su attivazione + ricorrente
- Standardizzazione del processo di onboarding (wizard self-service, riduzione intervento manuale)
- Avvio pipeline di build nativa automatizzata (Opzione 1 di [11-strategia-white-label.md](11-strategia-white-label.md)) per i tenant che lo richiedono
- Costituzione di un team minimo di Customer Success (1 persona per ~150-300 tenant, [13-strategia-scalabilita.md](13-strategia-scalabilita.md))
- Introduzione dei primi add-on (es. SMS, multi-sede) per iniziare a validare l'espansione dell'ARPU
- Attivazione del **follow-up di adozione** (Journey 4, [07-user-journey.md](07-user-journey.md)): per i tenant sotto la soglia KPI di adozione clienti finali ([01-prd.md](01-prd.md)), il Customer Success attiva un intervento dedicato (consigli di promozione, materiali aggiuntivi, formazione)

**Prerequisiti tecnici**: automazione completa del Flusso 1 e Flusso 2 ([08-flussi-applicativi.md](08-flussi-applicativi.md)), monitoraggio piattaforma di base (Modulo A3)

### Fase 2 — Scale-up (300 → 1.500 tenant)
**Obiettivo**: industrializzare i processi, diversificare i canali, iniziare l'espansione verticale.

- Espansione della rete commerciale/partner per territorio
- Partnership con distributori/fornitori di settore (vedi [02-analisi-mercato.md](02-analisi-mercato.md), sezione canali)
- Introduzione di moduli verticali specifici (es. dati sanitari per dentisti/fisioterapisti — vedi [10-funzionalita.md](10-funzionalita.md))
- Strutturazione del supporto a livelli (Livello 1/2/3, [13-strategia-scalabilita.md](13-strategia-scalabilita.md))
- Valutazione separazione letture/scritture database e caching per sostenere il carico

**Prerequisiti tecnici**: caching, code asincrone per notifiche, parallelizzazione pipeline build

### Fase 3 — Espansione (1.500 → 5.000 tenant)
**Obiettivo**: diversificazione geografica e di canale, marketplace opzionale.

- Valutazione ingresso in un secondo mercato (paese UE con normative compatibili)
- Introduzione di un eventuale modulo "marketplace/directory" come canale di acquisizione aggiuntivo (vedi [02-analisi-mercato.md](02-analisi-mercato.md), opportunità). **Vincolo di design**: il marketplace deve funzionare come canale di scoperta che reindirizza (deep link) all'app brandizzata del singolo tenant, non come sostituto di essa, per non compromettere il posizionamento "white label" rispetto ai marketplace aggregatori (categoria A, [03-analisi-concorrenti.md](03-analisi-concorrenti.md))
- Automazione avanzata del supporto (knowledge base estesa, chatbot di primo livello)
- Programmi di referral strutturati per ridurre il CAC ([15-strategia-monetizzazione.md](15-strategia-monetizzazione.md))

**Prerequisiti tecnici**: architettura pronta per partizionamento dati per regione/fascia, monitoraggio avanzato (observability), revisione di sicurezza/compliance per nuovi mercati ([14-strategia-sicurezza.md](14-strategia-sicurezza.md))

### Fase 4 — Scala (5.000 → 10.000 tenant)
**Obiettivo**: consolidamento, efficienza operativa, preparazione per oltre 10.000 tenant.

- Ottimizzazione dei costi infrastrutturali per tenant (economia di scala)
- Automazione quasi totale dell'onboarding e del supporto di base
- Possibile introduzione di funzionalità basate su intelligenza artificiale come differenziatore (es. suggerimenti di scheduling, assistente virtuale per i clienti finali) — vedi opportunità in [04-swot.md](04-swot.md)
- Revisione complessiva dell'architettura multi-tenant per identificare eventuali necessità di sharding/partizionamento ulteriore ([12-strategia-multi-tenant.md](12-strategia-multi-tenant.md))

## 3. Tabella di sintesi

| Fase | Tenant target | Focus principale | Team commerciale (indicativo) | Team CS/Supporto (indicativo) |
|---|---|---|---|---|
| 0 — Validazione | 0-30 | Prodotto, pricing | Fondatori/diretto | Assistenza diretta |
| 1 — Early Growth | 30-300 | Motore di acquisizione | Piccolo team/agenti | 1-2 persone |
| 2 — Scale-up | 300-1.500 | Industrializzazione, verticali | Rete territoriale/partner | 5-8 persone (multilivello) |
| 3 — Espansione | 1.500-5.000 | Geografia, canali, marketplace | Rete multi-regione | 15-25 persone |
| 4 — Scala | 5.000-10.000 | Efficienza, automazione, AI | Rete consolidata + canali digitali | 30-50 persone |

> I numeri di team sono indicativi e derivano dai rapporti definiti in [13-strategia-scalabilita.md](13-strategia-scalabilita.md); andranno calibrati con i dati reali raccolti nelle fasi iniziali.

## 4. Rischi di execution per fase e mitigazioni

| Fase | Rischio principale | Mitigazione |
|---|---|---|
| 0 | Prodotto non risolve realmente il problema percepito | Ciclo di feedback stretto con i pilota, iterazione rapida |
| 1 | Onboarding non scalabile (troppo manuale) | Investimento in automazione prima di scalare la vendita |
| 2 | Supporto sommerso da ticket per problemi ripetitivi | Knowledge base, automazione risposte comuni, miglioramento UX da feedback |
| 3 | Complessità normativa nuovo mercato sottostimata | Due diligence legale/compliance prima dell'ingresso |
| 4 | Debito tecnico accumulato limita ulteriore crescita | Cicli regolari di revisione architetturale (vedi audit, [17-audit-revisione.md](17-audit-revisione.md)) |

## 5. Indicatori di avanzamento tra le fasi (gate)

Il passaggio da una fase alla successiva non dovrebbe essere puramente temporale, ma condizionato al raggiungimento di soglie qualitative:

- Tasso di churn sotto controllo (< 3% mensile, [01-prd.md](01-prd.md))
- Tempo di onboarding stabilmente sotto target (< 5 giorni)
- NPS tenant positivo e stabile (> 40)
- Unit economics positiva o con traiettoria chiara verso il break-even ([15-strategia-monetizzazione.md](15-strategia-monetizzazione.md))
