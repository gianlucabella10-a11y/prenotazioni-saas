# CUSTOMER FLOW — dall'installazione alla prenotazione (FASE 1)

> Il flusso **ideale**, schermata per schermata, click per click, con animazioni, messaggi e conferme.
> Progettazione, non implementazione. Confrontato con lo stato reale ([CURRENT_CUSTOMER_APP.md](CURRENT_CUSTOMER_APP.md)).
> Principio guida: **prenotare deve essere la cosa più facile che l'app permette di fare.**

---

## Percorso A — Primo cliente (prima installazione)

### A1 · Splash (0–1.5s)
- **Vede:** logo del brand su sfondo brand, animazione di entrata morbida (fade+scale).
- **Fa:** nulla. Carica `GET /app/config` (già cache-ETag).
- **Regola:** mai più di ~1.5s percepiti; se lento, transizione a skeleton della Home, non spinner infinito.

### A2 · Onboarding (opzionale, saltabile) — 2 schede
- **Scopo:** dare identità in 5 secondi, non spiegare. 2 card: "Benvenuto da [Nome]" + "Prenota in un minuto".
- **Fa:** *Salta* sempre visibile. Non è un tutorial: è un saluto brandizzato.
- **Delta vs oggi:** oggi ASSENTE. Priorità media (dopo il booking core).

### A3 · Home (visibile anche da ospite)
- **Vede nei primi 3s:** identità (logo/nome/foto), **un** grande pulsante **"Prenota"**, e — se ha senso — "prossimo appuntamento".
- **Fa:** tocca "Prenota". *Può esplorare servizi e disponibilità senza account.*
- **Delta vs oggi:** oggi la Home è dietro login+verifica. **Redesign chiave: rendere la Home e la scelta prenotazione esplorabili da ospite.** L'account si chiede **al momento di confermare**, non prima.

### A4 · Step 1 · Servizi
- **Vede:** lista servizi con durata e prezzo; selezione a tap. Default: singolo servizio.
- **Fa:** tocca 1 servizio → **Continua**. (Multi-servizio resta possibile ma non richiesto.)
- **Animazione:** check morbido sul servizio scelto.
- **Errore da evitare:** obbligare a capire "puoi sceglierne più di uno". Il default single-tap deve bastare.

### A5 · Step 2 · Quando e con chi (una schermata)
- **Vede:** striscia giorni con **Oggi/Domani** evidenziati, chip operatori (incl. **"Chiunque"**), slot Mattina/Pomeriggio.
- **Default intelligenti:** oggi + "Chiunque" + primo slot suggerito. Anche **"Prima disponibilità"** in cima (un tap → salta alla scelta ottimale).
- **Fa:** tocca uno slot → il bottone diventa **"Conferma · [servizio] · [ora] · [prezzo]"**.
- **Delta vs oggi:** aggiungere "Chiunque", "Prima disponibilità", riepilogo prezzo sul bottone. Il resto c'è già ed è ottimo.

### A6 · Conferma & account (solo se ospite)
- **Vede:** foglio conciso: riepilogo prenotazione + **un solo campo** per identificarsi (telefono con **OTP** o email). Consenso privacy inline.
- **Fa:** inserisce il numero → riceve codice → conferma. *Prenota e si registra nello stesso gesto.*
- **Delta vs oggi:** oggi serve registrazione completa (password ≥10) **+ verifica email prima** di prenotare. **Redesign: capture minimo al momento della conferma; verifica leggera (OTP) contestuale.** È l'intervento a più alto impatto sulla prima conversione.
- **Regola:** mai chiedere password nel primo booking. Mai far uscire dall'app per copiare un codice se si può usare OTP/autofill SMS.

### A7 · Successo (il "momento")
- **Vede:** icona animata (check con micro-bounce) + **haptic** leggero, recap reale (servizio, data, operatore, prezzo).
- **Fa (azioni utili, non un vicolo cieco):**
  - **➕ Aggiungi al calendario** (Apple/Google)
  - **🗺 Indicazioni** (mappa)
  - **Vedi le mie prenotazioni** / **Home**
- **Messaggio di fiducia:** "Ti ricorderemo 24h prima." (se reminder attivo)
- **Delta vs oggi:** oggi recap corretto ma cieco. **Aggiungere calendario + indicazioni + animazione/haptic + rassicurazione reminder.**

---

## Percorso B — Cliente abituale (ritorno)

### B1 · Splash → Home (già autenticato)
- **Vede subito:** "Prenota di nuovo · [ultimo servizio con lo stesso operatore]" + "Prenota" + prossimo appuntamento.
- **Fa:** un tap su "Prenota di nuovo" → salta a A5 con tutto pre-selezionato → **1 tap slot → Conferma.**
- **Tempo obiettivo:** **< 20 secondi.**
- **Delta vs oggi:** aggiungere lo shortcut "Prenota di nuovo" e "Prima disponibilità".

---

## Percorso C — Gestione (dopo la prenotazione)

### C1 · Le mie prenotazioni (storico)
- **Vede:** prossime in alto, passate sotto. Per ciascuna: servizio, data, operatore, stato.
- **Fa:** **Sposta** (reschedule) · **Aggiungi al calendario** · **Indicazioni** · **Annulla** (entro il limite).
- **Delta vs oggi:** oggi c'è solo Annulla. **Aggiungere Sposta + calendario + indicazioni.**

### C2 · Nessuno slot disponibile → Waitlist
- **Vede (quando 0 slot):** "Nessun orario libero. **Avvisami se si libera**".
- **Fa:** un tap → entra in lista d'attesa; riceve push quando si apre uno slot.
- **Delta vs oggi:** ASSENTE lato app (tabella backend esiste). Recupera prenotazioni altrimenti perse.

---

## Regole trasversali del flusso

1. **Mai una schermata "a vuoto"**: ogni stato (loading/empty/error) ha copy utile e un'azione.
2. **Skeleton, non spinner**, dove si carica una lista (servizi, slot, storico).
3. **Il pulsante primario è sempre uno solo** e dice esattamente cosa farà.
4. **Contesto sempre visibile** durante il booking (servizio · operatore · giorno in una riga sticky).
5. **Nessun vicolo cieco**: da ogni fine (successo, empty, errore) c'è un'azione ovvia in avanti.
6. **L'account è un dettaglio del checkout, non un cancello all'ingresso.**
