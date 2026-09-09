# ARCHITECTURE_FINAL_REVIEW — La piattaforma regge a 1.000 tenant paganti?

**Data**: 12/06/2026 · **Input**: MASTER_HANDOVER_FABLE5.md, GIUFFRIDA_FEATURE_GAP.md, SCREEN_ANALYSIS.md, docs/20-34, backend implementato (64 test verdi) · **Metodo**: revisione a quattro ruoli (CTO SaaS, Senior Flutter Architect, Senior Laravel Architect, Product Owner) sulla domanda: *"se dovessimo arrivare a 1.000 clienti paganti, cambieresti qualcosa dell'architettura attuale?"*

---

## 0. Verdetto sintetico

**L'architettura software è confermata: a 1.000 tenant non cambierei nessuna scelta strutturale** (monolite modulare, MySQL singolo con tenant_id, thin shell + config runtime, outbox notifiche). I numeri lo dimostrano (§2): a 1.000 tenant il backend lavora a una frazione della sua capacità.

**Cambierei invece quattro cose che NON sono codice**, e che a 1.000 tenant diventano il vero sistema:

| # | Cambiamento raccomandato | Quando deciderlo | Perché |
|---|---|---|---|
| 1 | **Sharding degli account Google Play** (max ~50 app per account organizzazione della piattaforma) invece di un unico account per tutte le app | **Subito, prima della 20ª app** | Un solo account = un solo ban = 1.000 tenant offline simultaneamente. È l'unico rischio catastrofico correlato dell'intero progetto |
| 2 | **Billing automatizzato (gateway abbonamenti) promosso a prerequisito di scala**, non add-on | Prima dei 100 tenant | A 1.000 tenant la fatturazione/dunning manuale è impraticabile; il Flusso 9 (sospensione per morosità) senza automazione non esiste |
| 3 | **"Store Operations" come componente di prodotto** (Release Train Orchestrator + console stato pubblicazioni), non come attività manuale | Progettazione prima dei 50 tenant, operativo prima dei 200 | A 1.000 tenant: ~4.000 submission iOS/anno ≈ 16 al giorno lavorativo. Senza tooling è il collo di bottiglia che ferma la crescita |
| 4 | **Funnel di onboarding ridisegnato attorno ai tempi Apple** (pagina web prenotazione subito → Android in giorni → iOS in settimane) | Subito, nel contratto standard | L'enrollment Apple del tenant (D-U-N-S incluso) richiede 2-4 settimane: incompatibile con la promessa "app in 5 giorni" se non si separano i canali |

Tutto il resto è confermato, con trigger di evoluzione già definiti (§9).

---

## 1. Premessa di metodo: cosa significa "1.000 tenant" in numeri

| Grandezza | Stima a 1.000 tenant | Fonte/ipotesi |
|---|---|---|
| MRR | **250.000 €/mese** + IVA | 250€ × 1.000 |
| Appuntamenti | ~400-600k/mese (≈ 6M/anno) | 400-600/tenant/mese, media tra verticali |
| Scritture booking | ~0,2/s medie, **picchi 5-20/s** | concentrazione serale/weekend |
| Letture availability | 5-15M richieste/mese, picchi 50-100 rps | 10-30× le prenotazioni, cache hit >80% |
| Clienti finali registrati | 300-800k utenti, ~500k device push | 300-800 clienti attivi/tenant |
| Notifiche | 1,5-2M/mese (push gratuite, email marginali) | conferma + 1-2 promemoria per appuntamento |
| Crescita DB | ~3 GB/anno transazionale + ~10 GB/anno notifiche/audit | righe stimate in docs/24 §5 |
| App pubblicate | fino a 1.000 Android + 1.000 iOS | una per tenant per piattaforma |
| Submission store | ~4.000 iOS/anno con 4 treni core | il numero più importante di questa tabella |

**La lettura corretta**: il carico computazionale è banale per l'architettura scelta. La complessità a 1.000 tenant è **operativa e distributiva** (store, billing, onboarding), non tecnica. La revisione si concentra di conseguenza.

---

## 2. Scalabilità tecnica (Senior Laravel Architect)

### Verdetto: CONFERMATA, nessun cambiamento strutturale

- **Monolite modulare**: a 1.000 tenant il team sarà di 5-10 persone — i microservizi resterebbero un errore (overhead operativo senza beneficio). I confini DDD già implementati preservano l'opzione di estrazione, che non prevedo necessaria nemmeno a 10.000.
- **MySQL singolo + tenant_id**: 6M appuntamenti/anno con gli indici già definiti (`tenant_id` primo in ogni indice composito) è territorio tranquillo per una `db.r6g.large`. Lo sharding resta correttamente un'opzione remota a trigger, non un piano.
- **Redis**: cache versionata O(1) per l'availability già implementata; a 1.000 tenant le hot key sono gestibili con i TTL esistenti.
- **Code**: i promemoria con job ritardati + sweep ibrido (docs/33 #50, implementato) distribuiscono naturalmente il carico; l'autoscaling dei worker su profondità coda copre i picchi serali.

### Cose da fare PRIMA di arrivarci (trigger già pianificati, qui confermati con soglie)

| Intervento | Trigger | Stato |
|---|---|---|
| Read replica per report/export | p95 letture pesanti che impattano le scritture, o CPU RDS >60% sostenuta — atteso tra 500 e 1.500 tenant | Pianificato (docs/32), confermo |
| **Ciclo di vita dati notifiche/audit**: archiviazione su S3 + pruning oltre 12 mesi | ~20M righe (anno 2 a 1.000 tenant) | **Da formalizzare ora come policy**, niente FK già predisposto |
| Spike gap-lock MySQL su booking concorrente | Prima del primo tenant con >5 operatori ad alta rotazione | Già obbligatorio in handover (#33), confermo priorità |
| RDS Proxy / connection pooling | >50 task Fargate concorrenti | Non prima di 2-3.000 tenant |
| Octane/FrankenPHP | Mai per necessità a questi carichi; solo se i costi Fargate diventassero rilevanti (non lo saranno: §3) | Non fare |

**Unica aggiunta architetturale che raccomando**: regola di governance esplicita — **vietato codice tenant-specific**; ogni divergenza passa da feature flag/configurazione. A 1.000 tenant la pressione commerciale per "la piccola modifica per il cliente importante" è la prima causa di morte dei white label. L'architettura la supporta già; va scritta come invariante nel contratto di sviluppo.

---

## 3. Costi AWS (CTO SaaS)

### Verdetto: CONFERMATA — i costi infrastrutturali sono un non-problema

| Voce a 1.000 tenant | Stima mensile | % su MRR (250k€) |
|---|---|---|
| Stack AWS produzione (docs/32 colonna Fase 2) | ~2.230 € | 0,9% |
| Staging + CI (+25-30%, audit #56) | ~650 € | 0,3% |
| Runner macOS per pipeline iOS (~110 h/mese con 4 treni/anno su 1.000 app) | ~500-800 € | 0,3% |
| FCM | 0 € | — |
| **Totale tecnico** | **~3.400-3.700 €** | **~1,4%** |

Il costo che domina a 1.000 tenant è **umano**: 4-7 persone CS/supporto (rapporti docs/13) + 1 ruolo dedicato "store operations" + commerciale. L'architettura contribuisce alla sostenibilità proprio tenendo il costo marginale tecnico per tenant a ~3,4€/mese (1,4% del canone): **nessuna ottimizzazione dei costi cloud merita priorità** rispetto all'automazione dei processi umani.

Unico monitoraggio specifico: il payload di `GET /app/config` crescerà (orari, social, galleria — gap P0). Con 800k utenti che lo rivalidano ad ogni avvio, l'ETag/304 già implementato è ciò che mantiene il traffico irrisorio: **mantenere la disciplina ETag su ogni estensione** del payload.

---

## 4. Modello white label: thin shell + configurazione runtime (Senior Flutter Architect)

### Verdetto: CONFERMATA — è LA decisione che rende possibile il business a 1.000 tenant

Controprova: senza thin shell, ogni modifica colori/listino di ogni tenant richiederebbe una submission. Con 1.000 tenant e la frequenza di ritocco osservata nel target (stagionalità, promozioni), il modello "tutto compilato" collasserebbe in settimane. Con il thin shell, le submission servono solo per: prima pubblicazione, cambio icona/nome, 3-4 treni motore/anno.

### Adeguamenti raccomandati (non cambi di rotta)

1. **Test di stress del tema in CI**: a 1.000 tenant esisteranno palette che nessuno ha provato. Servono golden/property test sul design system con palette generate (contrasto già validato dal backend, ma layout/leggibilità vanno provati lato Flutter). Da costruire nell'incremento Flutter, non dopo.
2. **Valutare il code push Dart (es. Shorebird)** come riduttore dei treni di rilascio: patch OTA del codice Dart senza submission. Da valutare con verifica di conformità alle policy store al momento dell'adozione — se regge, i treni annuali scendono e con essi il costo runner e il rischio review. **Valutazione, non impegno.**
3. **Limite app per progetto Firebase** (~20-30 raccomandate): a 1.000 tenant servono ~50-70 progetti nel pool. Il design (docs/29 §4) lo prevede; ciò che manca è il **provisioning automatizzato dei progetti** con monitoraggio quota — requisito esplicito dell'incremento infra.
4. **Generazione asset nativi** (icone/splash da logo master): a 30-60 onboarding/mese deve essere completamente automatica con approvazione visiva CS al primo build (già KPI in docs/01); il punto di attenzione è la qualità dei loghi sorgente — il vincolo di risoluzione minima bloccante è già progettato e va mantenuto rigido.

---

## 5. Strategia App Store / rischio Apple Review (CTO + Flutter Architect)

### Verdetto: CONFERMATA nella sostanza (account del tenant, guideline 4.2.6), con una presa d'atto operativa

La scelta "iOS pubblicato dall'account Apple del tenant" resta l'unica via sanzionata da Apple per le template app — ed è anche un **de-risking strutturale**: 1.000 account indipendenti = nessun ban correlato possibile lato Apple.

A 1.000 tenant però emergono tre verità operative da gestire come prodotto, non come eccezioni:

1. **L'enrollment Apple del tenant dura 2-4 settimane** (account org: D-U-N-S, verifica). Conseguenze: (a) va avviato **alla firma del contratto**, in parallelo al wizard; (b) il funnel di go-live va dichiarato nel contratto: *web booking subito → Android in giorni → iOS a enrollment completato*; (c) il KPI "<5 giorni" si applica al servizio attivo (web+Android), non a iOS.
2. **~4.000 submission/anno** con rejection rate fisiologico 5-10%: servono il Release Train Orchestrator (coda, stato, retry, fasce canary — già disegnato in docs/27 §3) e **una persona dedicata** alle store ops dal raggiungimento di ~200 tenant. Senza, ogni treno core diventa un mese di lavoro manuale.
3. **Rischio residuo di policy change Apple** (storicamente: stretta 2017 su 4.2.6/4.3, parziale retromarcia 2018): non eliminabile. Mitigazione strutturale già in architettura: la **pagina web di prenotazione per tenant** (stessa API) va trattata come canale di fallback di prima classe — raccomando di **alzarne la priorità** nell'incremento dashboard (è anche il canale "giorno zero" del funnel). Se Apple cambiasse le regole, il servizio sopravvive: si perde il canale premium, non il prodotto.

Gate già fissato e confermato: **validare con 2-3 tenant pilota reali su App Store prima di standardizzare la promessa contrattuale iOS** (docs/33 #41).

## 6. Strategia Google Play (CTO)

### Verdetto: DA CAMBIARE — unico vero cambiamento di strategia di questa review

La strategia attuale (account developer unico della piattaforma che pubblica tutte le app Android) contiene un **rischio catastrofico correlato**: le policy Google su contenuti ripetitivi/spam vengono applicate anche a livello di account, e la storia del Play Store include sospensioni di account white label con rimozione simultanea di tutte le app. A 1.000 tenant significherebbe: **un singolo provvedimento = 1.000 clienti paganti offline lo stesso giorno**. Inaccettabile a prescindere dalla probabilità.

L'alternativa simmetrica ad Apple (account Play del tenant) ha controindicazioni specifiche di Google: gli account personali nuovi hanno il requisito dei 20 tester per 14 giorni, e gli account organizzazione richiedono anch'essi verifiche (D-U-N-S); per micro-attività è attrito reale.

**Raccomandazione — sharding degli account piattaforma**:
- Più account organizzazione intestati alla software house, **max ~50 app per account**, popolati per coorti di tenant
- Blast radius di un eventuale provvedimento: ≤50 tenant (≤5% a 1.000), con runbook di emergenza (riattivazione su altro account via app transfer + comunicazione tenant)
- Differenziazione dei contenuti store per app (descrizione, screenshot dal catalogo reale, contatti) già obbligatoria da docs/27 §6 — resta la prima difesa
- Decisione **da prendere prima della 20ª app**: il transfer successivo è possibile ma è lavoro evitabile
- Rivalutare gli account per-tenant (org) come opzione per le nuove coorti in Fase 2, quando esisterà il tooling store-ops

## 7. Onboarding clienti (Product Owner)

### Verdetto: ARCHITETTURA CONFERMATA, PROCESSO DA RIDISEGNARE attorno ai vincoli store

Il provisioning tecnico è già eccellente: una transazione → tenant pronto in secondi con catalogo di settore, orari, brand placeholder, admin con MFA (implementato e testato). A 1.000 tenant (30-60 nuovi/mese in Fase 2) i colli di bottiglia sono altri:

1. **Sequenza go-live a tre canali** (il cambiamento chiave, vedi §5): firma → enrollment Apple avviato + wizard self-service → pagina web prenotazione live (giorno 0-2) → app Android pubblicata (giorno 3-7) → app iOS (settimana 3-6). Da riflettere in contratto, materiali di vendita e dashboard di stato onboarding.
2. **La dashboard super admin (oggi inesistente come UI) diventa il critical path**: a 30-60 onboarding/mese in parallelo, lo stato di ogni tenant (wizard, asset, enrollment, build, review) dev'essere visibile e azionabile da CS senza toccare il database. Confermo la priorità dell'incremento 5 dell'handover, suggerendo di anticiparne il sottoinsieme "console onboarding".
3. **Wizard self-service**: il flusso osservato negli screenshot conferma che il tenant tipo sa usare strumenti semplici; i preset di settore già implementati sono la leva giusta. Aggiunta dal gap: il kit di lancio (QR, locandine) è parte dell'onboarding, non un extra.
4. **Billing alla firma**: attivazione 990€ + mandato SDD/carta per il canone vanno raccolti nel funnel di onboarding — altro motivo per anticipare l'integrazione gateway (§0.2).

## 8. Cosa NON cambierei (anti-overengineering, tutti i ruoli)

A 1.000 tenant **non** servono, e introdurli ora sarebbe un errore:
- Microservizi, API gateway/BFF dedicati, event bus esterno (Kafka/SNS): il monolite modulare con eventi in-process copre 10-50× il carico previsto
- Sharding MySQL, multi-regione, Aurora: trigger non raggiunti; Aurora solo se failover <30s diventasse requisito contrattuale
- Database-per-tenant: la predisposizione ibrida (connessione risolta dal TenantContext) basta; nessun segnale che serva prima di richieste enterprise esplicite
- Riscrittura del guard/tenancy: i 5 livelli implementati sono adeguati; l'investimento giusto è nei test architetturali automatici (review #16), non in più meccanismi
- GraphQL, gRPC, websocket per l'app cliente: REST+ETag+push copre tutti i flussi osservati negli screenshot

## 9. Tabella riassuntiva dei trigger di evoluzione

| Soglia tenant | Azione | Area |
|---|---|---|
| **Subito (≤20 app)** | Decisione sharding account Play; funnel onboarding a 3 canali nel contratto; policy ciclo vita dati notifiche | Store / Processo / Dati |
| ≤50 | Progettazione Release Train Orchestrator; spike lock MySQL; pilota iOS su account tenant (gate contrattuale) | Store ops / DB / Apple |
| ≤100 | Gateway billing + dunning automatico (Flusso 9 reale); console onboarding super admin | Billing / Dashboard |
| ~200 | Persona dedicata store ops; valutazione code push Dart | Organizzazione / Flutter |
| 500-1.500 | Read replica; archiviazione notifiche/audit; pool Firebase automatizzato a regime | Infra |
| >1.500 (fuori scope domanda) | Rivalutazione account Play per-tenant; valutazioni Fase 3 (docs/16) | — |

## 10. Conclusione

**Risposta alla domanda principale: no, a 1.000 clienti paganti non cambierei l'architettura software — cambierei la strategia di distribuzione Android (sharding account Play), anticiperei billing e tooling store-ops, e ridisegnerei il funnel di onboarding attorno ai tempi di Apple.**

La piattaforma è stata progettata per 10.000 tenant e implementata partendo dalle invarianti giuste (isolamento, atomicità, config runtime): a 1.000 tenant lavora in scioltezza. Il rischio di execution non è nel codice: è negli store (mitigabile con le decisioni di questa review), nel billing (automatizzabile presto) e nelle operations umane (indirizzabili con tooling già progettato). Le quattro raccomandazioni del §0 sono le uniche modifiche richieste al piano esistente, e tre su quattro vanno decise **prima dei 50 tenant** — cioè nella prossima fase di lavoro, non in un futuro remoto.
