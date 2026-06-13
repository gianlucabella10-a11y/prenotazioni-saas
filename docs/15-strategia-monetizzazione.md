# 15 — Strategia di Monetizzazione

## 1. Struttura dei ricavi

### 1.1 Ricavi core (modello dichiarato)

| Voce | Importo | Natura |
|---|---|---|
| Fee di attivazione | 990 € una tantum | Copre onboarding, configurazione iniziale, generazione e pubblicazione della prima build |
| Canone ricorrente | 250 €/mese + IVA | Copre hosting, manutenzione, supporto, aggiornamenti, infrastruttura di notifica |

### 1.2 Ricavi incrementali (add-on, roadmap)

| Add-on | Modello di prezzo indicativo | Note |
|---|---|---|
| Pagamenti online / acconti | Fee fissa mensile + eventuale percentuale sulle transazioni | Dipende dal gateway di pagamento scelto |
| Multi-sede (oltre soglia base) | Sovrapprezzo per sede aggiuntiva | Es. +X €/mese per sede oltre la prima |
| Invio SMS | Costo a consumo (pass-through + margine) | I promemoria via SMS hanno un costo per messaggio |
| Modulo dati sanitari avanzato | Sovrapprezzo mensile fisso | Copre costi di compliance aggiuntivi |
| Marketing automation avanzato (segmentazione, campagne) | Sovrapprezzo mensile o incluso in piano Pro | - |
| Report avanzati multi-sede | Incluso in piano Enterprise | - |

## 2. Struttura dei piani (allineamento con [10-funzionalita.md](10-funzionalita.md))

| Piano | Target | Prezzo indicativo |
|---|---|---|
| **Base** | Singolo professionista/piccola attività, 1 sede | 990 € attivazione + 250 €/mese (come da modello dichiarato) |
| **Pro** | Attività con più operatori, marketing attivo, fino a 2-3 sedi | 990 € attivazione + 250 €/mese + add-on selezionati |
| **Enterprise** | Catene/multi-sede, settori regolamentati con esigenze di compliance avanzata | Prezzo personalizzato, basato su numero sedi/operatori e moduli |

> Nota: il piano Base a 250€/mese deve includere tutte le funzionalità necessarie a un professionista singolo per percepire valore immediato (vedi fascia "B" in [10-funzionalita.md](10-funzionalita.md)); gli add-on sono leve di crescita dell'ARPU (Average Revenue Per Tenant) senza alterare la promessa di prezzo base.

## 3. Unit Economics (struttura di analisi)

> I valori numerici sotto sono **placeholder strutturali** per la modellazione: vanno popolati con dati reali (costi infrastruttura, costi di vendita) durante la pianificazione finanziaria. La struttura dell'analisi è comunque parte della documentazione architetturale richiesta.

### 3.1 Componenti di costo per tenant (costo marginale mensile)

- Costo infrastruttura (hosting, storage, CDN) per tenant — decresce con la scala grazie a economie di scala multi-tenant
- Costo notifiche (push generalmente a basso costo, SMS a consumo)
- Costo supporto (proporzionale al numero di ticket per tenant)
- Costo di mantenimento build/pubblicazione (ammortizzato su scala grazie a pipeline automatizzata)

### 3.2 Componenti di costo di acquisizione (CAC)

- Costo commerciale (tempo agente/venditore, commissioni)
- Costo marketing (campagne locali, materiali demo)
- Costo di onboarding assistito (Customer Success)

### 3.3 LTV (Lifetime Value)

```
LTV = (Canone mensile - Costo marginale mensile) × Durata media abbonamento (mesi) + Fee di attivazione (contributo una tantum)
```

### 3.4 Rapporto LTV/CAC

- Target di riferimento (benchmark SaaS generali): LTV/CAC ≥ 3
- Il rapporto deve essere monitorato per segmento (es. barbieri vs. studi dentistici possono avere CAC e churn differenti)

## 4. Leve di crescita dei ricavi

1. **Espansione verticale (cross-sell add-on)**: ogni tenant attivo è un'opportunità di vendita di moduli aggiuntivi (pagamenti, marketing, multi-sede)
2. **Espansione di rete (referral)**: incentivi (es. sconto sul canone per N mesi) per tenant che referenziano nuovi clienti, riducendo il CAC
3. **Aumento prezzo per nuove coorti**: possibilità di rivedere il pricing per i nuovi tenant man mano che il prodotto matura, mantenendo prezzi storici per i tenant esistenti (politica di "grandfathering") per ridurre il churn
4. **Espansione geografica**: replicazione del modello in nuovi mercati (vedi [13-strategia-scalabilita.md](13-strategia-scalabilita.md))
5. **Marketplace opzionale**: eventuale modulo di visibilità aggregata (directory) con modello di pricing aggiuntivo (es. posizionamento in evidenza)

## 5. Gestione del churn

- **Prevenzione**: monitoraggio dell'adozione (numero di clienti finali che usano l'app, numero di prenotazioni) come indicatore predittivo di rischio churn — un tenant con bassa adozione percepisce meno valore e ha maggiore probabilità di cancellazione
- **Intervento proattivo**: Customer Success interviene sui tenant a basso utilizzo entro le prime settimane (Journey 4, [07-user-journey.md](07-user-journey.md))
- **Win-back**: offerte dedicate a tenant che hanno cessato il servizio, con possibile riattivazione semplificata (i dati storici, se conservati nei termini di retention, facilitano il ripristino)

## 6. Considerazioni sulla sostenibilità a basso numero di tenant

Con un numero limitato di tenant (es. fase pilota, 10-50 clienti), il canone di 250€/mese deve sostenere:
- Costi fissi di infrastruttura (anche se sotto-utilizzata)
- Costi di supporto (proporzionalmente più alti per cliente in fase iniziale, per via della curva di apprendimento)
- Costi di sviluppo/mantenimento del template core (costo fisso indipendente dal numero di tenant)

Questo implica che **la fase pilota sarà probabilmente in perdita o a margine minimo**, e che la sostenibilità economica del modello si raggiunge a partire da una soglia di tenant che ripaga i costi fissi (break-even). Questa soglia deve essere stimata con un piano finanziario dedicato (fuori scope di questo documento architetturale) e monitorata come parte del piano di crescita ([16-piano-crescita.md](16-piano-crescita.md)).

## 7. Politiche commerciali

- **Periodo minimo contrattuale**: da definire (es. 12 mesi), per garantire la sostenibilità della fee di attivazione ridotta rispetto al costo reale di onboarding
- **Politica di recesso anticipato**: penali o recupero della fee di attivazione scontata in caso di recesso prima del termine minimo
- **Politica di sospensione/riattivazione**: vedi Flusso 9 ([08-flussi-applicativi.md](08-flussi-applicativi.md))
