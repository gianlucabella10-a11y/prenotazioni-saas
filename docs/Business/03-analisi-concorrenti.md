# 03 — Analisi dei Concorrenti

> Nota: l'analisi seguente descrive **categorie di soluzioni concorrenti** e relative caratteristiche funzionali generiche, senza riferimento a marchi, design o contenuti proprietari specifici. L'obiettivo è posizionare la proposta della piattaforma rispetto alle alternative disponibili sul mercato.

## 1. Categorie di concorrenti

### A. Piattaforme di prenotazione generaliste (marketplace/aggregatori)
Applicazioni e portali che aggregano l'offerta di molti professionisti in un'unica app, permettendo ai consumatori di cercare e prenotare servizi nelle vicinanze.

**Caratteristiche tipiche:**
- Directory di professionisti ricercabile per categoria/posizione
- Sistema di prenotazione integrato, recensioni, pagamenti
- Commissioni per prenotazione o abbonamento mensile al professionista
- Brand del marketplace prevalente sul brand del singolo professionista

**Punti di forza:** alta visibilità verso nuovi clienti, funzionalità mature, infrastruttura consolidata
**Punti di debolezza:** il professionista non possiede il rapporto diretto con l'app, dipendenza dalla piattaforma, commissioni ricorrenti elevate, scarsa personalizzazione del brand

### B. Software gestionali per saloni/studi con modulo di prenotazione online
Software gestionali (spesso desktop/web) pensati per la gestione interna dell'attività (agenda, cassa, magazzino, clienti) che offrono come modulo aggiuntivo un link/widget di prenotazione online.

**Caratteristiche tipiche:**
- Gestione agenda multi-operatore avanzata
- Modulo prenotazione online tramite link/pagina web, talvolta widget embeddabile
- App mobile, quando presente, è generica (stesso brand del fornitore software per tutti i clienti) o assente
- Pricing a fascia per numero di postazioni/operatori

**Punti di forza:** funzionalità gestionali interne molto complete (cassa, magazzino, contabilità)
**Punti di debolezza:** assenza di vera app brandizzata per il cliente finale; l'esperienza di prenotazione spesso è una semplice pagina web, non un'app nativa con notifiche push

### C. App white label per singoli professionisti (categoria di riferimento funzionale del progetto)
Soluzioni che forniscono al singolo professionista (o piccola catena) un'app mobile a proprio marchio, con funzionalità di prenotazione, gestione servizi/operatori/orari e comunicazione con i clienti.

**Caratteristiche tipiche:**
- App nativa con nome, icona, colori personalizzati per il singolo professionista
- Gestione di servizi, listino, operatori, orari di apertura/chiusura, ferie
- Notifiche push per promemoria appuntamenti e promozioni
- Dashboard web di gestione per il titolare
- Modello di pricing: setup fee + canone mensile (modello a cui il presente progetto si ispira sul piano funzionale)

**Punti di forza:** forte identità di brand del professionista, relazione diretta con il cliente, esperienza app nativa
**Punti di debolezza:** costo per il piccolo professionista (setup + canone), necessità di adozione da parte della clientela (scaricare una nuova app)

### D. Strumenti generici di scheduling (calendar booking)
Strumenti di pianificazione appuntamenti orientati a professionisti che lavorano principalmente via link di prenotazione (consulenti, coach, servizi B2B), integrati con calendari personali.

**Caratteristiche tipiche:**
- Pagina di prenotazione pubblica collegata al calendario personale
- Integrazioni con strumenti di videoconferenza
- Nessuna app dedicata per il cliente finale, nessun branding "app store"

**Punti di forza:** semplicità, ottima integrazione calendario, prezzo contenuto
**Punti di debolezza:** nessuna presenza come app installabile, esperienza poco "consumer", non adatto a settori con clientela ricorrente fisica (barbiere, estetista)

## 2. Matrice di posizionamento

| Dimensione | Marketplace (A) | Gestionali con widget (B) | White label per professionista (C) | Scheduling generico (D) | **Piattaforma proposta** |
|---|---|---|---|---|---|
| App brandizzata per il professionista | No | No/Raro | Sì | No | **Sì** |
| Gestione multi-operatore/multi-sede | Parziale | Sì | Parziale | No | **Sì** |
| Relazione diretta col cliente finale | No (intermediata) | Parziale | Sì | Parziale | **Sì** |
| Configurabilità self-service da dashboard | Parziale | Sì | Variabile | Sì | **Sì** |
| Setup fee + canone fisso (no commissioni per prenotazione) | No (spesso commissioni) | Sì | Sì | Sì | **Sì** |
| Adatto a verticali regolamentati (sanità) | Raro | Parziale | Variabile | No | **Sì (roadmap compliance)** |
| Tempo di attivazione per nuovo cliente | N/A | Settimane | Variabile | Immediato | **Target: giorni** |

## 3. Posizionamento competitivo della piattaforma

La piattaforma si posiziona nella categoria **C (White label per professionista)**, con l'obiettivo di:

1. Offrire un **time-to-market** di attivazione molto rapido grazie all'architettura multi-tenant (un solo codebase, configurazione via dashboard, pipeline automatizzata di build)
2. Estendere la copertura **verticale** oltre il beauty (sanità, fisioterapia, consulenza), con moduli e compliance dedicati
3. Mantenere un **pricing semplice e prevedibile** (setup + canone fisso), senza commissioni per prenotazione, a differenza dei marketplace
4. Offrire un **percorso di crescita modulare** (add-on a pagamento) per aumentare l'ARPU senza aumentare il canone base

## 4. Rischi competitivi

- **Aumento della concorrenza nella categoria C**: ingresso di nuovi player con architetture multi-tenant simili e pricing aggressivo
- **Discesa di prezzo dei marketplace**: se i marketplace riducono drasticamente le commissioni, potrebbero risultare più attraenti per i professionisti meno sensibili al brand proprio
- **Soluzioni "fai da te" basate su app builder generici** (no-code per app mobile) a basso costo, anche se con minore specializzazione di settore

Le strategie di mitigazione sono trattate in [04-swot.md](04-swot.md) e [16-piano-crescita.md](16-piano-crescita.md).
