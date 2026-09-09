# MICROCOPY GUIDE (FASE 5)

> Tutti i testi dell'app di prenotazione: titoli, pulsanti, errori, conferme, loading, empty state, notifiche,
> success. Progettazione, non implementazione. Alcuni testi esistono già nel codice: qui il **sistema** e il
> **target**. Si integra con i *voice pack per verticale* del white-label (barbiere/medico/coach…).

---

## Tono di voce (5 regole)

1. **Umano, non robotico.** Parla come una persona gentile della reception, non come un sistema.
2. **Breve.** Una frase, poche parole. Se puoi togliere una parola, toglila.
3. **Positivo e rassicurante.** Anche gli errori guidano, non colpevolizzano.
4. **Concreto.** Dì cosa succede o cosa fare, non concetti astratti.
5. **Del settore.** Usa le parole del cliente ("taglio", "visita", "seduta"), mai "risorsa/entità/item".

> Regola d'oro: **il testo di un pulsante deve dire esattamente cosa succederà** quando lo tocchi.

---

## Titoli (schermate)

| Schermata | Testo | Note |
|-----------|-------|------|
| Home | *(nessun titolo: identità del brand)* | Il brand è il titolo |
| Servizi | **"Cosa prenoti?"** | Più caldo di "Nuova prenotazione" (attuale) |
| Data/Orario | **"Quando ti va bene?"** | Più umano di "Scegli data e orario" (attuale) |
| Operatore (sezione) | **"Con chi?"** | Corto; default "Chiunque" |
| Conferma/Successo | **"Tutto pronto ✓"** / **"Richiesta inviata"** | Vedi Success |
| Le mie prenotazioni | **"Le tue prenotazioni"** | |
| Verifica | **"Confermiamo che sei tu"** | Meno burocratico di "Verifica la tua email" |

---

## Pulsanti

| Contesto | Testo | Regola |
|----------|-------|--------|
| CTA primario home | **"Prenota"** / (verticale: "Prenota il taglio") | Un solo verbo |
| Avanti servizi | **"Continua"** | OK (attuale) |
| Conferma finale | **"Conferma · [servizio] · [ora] · [prezzo]"** | Dì tutto ciò che confermi |
| Slot non scelto | **"Scegli un orario"** | Guida, non blocca (attuale ✔) |
| Re-booking | **"Prenota di nuovo"** | Per l'abituale |
| Prima disponibilità | **"Primo orario libero"** | Scorciatoia |
| Success → gestione | **"Aggiungi al calendario"** · **"Le mie prenotazioni"** | Azione utile prima di tutto |
| Waitlist | **"Avvisami se si libera"** | Recupero |
| Riprova | **"Riprova"** | OK (attuale ✔) |

**Da evitare:** "Invia", "OK", "Submit", "Prosegui con la registrazione". Mai pulsanti generici.

---

## Loading

- Non usare testo se c'è uno **skeleton** (preferito).
- Se serve testo: **"Un istante…"** (mai "Caricamento in corso…" / percentuali).
- Booking in corso: il pulsante mostra lo spinner, testo **"Confermo…"**.

---

## Empty state (con azione, mai testo nudo)

| Dove | Testo | Azione |
|------|-------|--------|
| Nessun servizio | **"Ancora nessun servizio prenotabile."** | — (raro) |
| Nessuno slot nel giorno | **"Niente libero questo giorno."** + **"Prova un altro giorno o avvisami se si libera."** | Cambia giorno / Waitlist |
| Nessun operatore per i servizi | **"Nessun operatore fa questi servizi insieme."** | Torna ai servizi |
| Storico vuoto | **"Non hai ancora prenotato."** + **"Prenota"** | CTA prenota |

*(Ogni empty state andrebbe accompagnato da un'illustrazione coerente col template — vedi white-label.)*

---

## Errori (guidano, non colpevolizzano)

| Situazione | Testo | Perché |
|-----------|-------|--------|
| Slot appena occupato | **"Quell'orario è appena stato preso. Eccone altri liberi."** | Rassicura + mostra alternative (già ricarica ✔) |
| Rete assente | **"Connessione assente. Controlla e riprova."** | Concreto |
| Sessione scaduta | **"Per sicurezza rifai l'accesso."** | Non allarma |
| Codice OTP errato | **"Codice non corretto. Controlla e riprova."** | Neutro |
| Troppo tardi per cancellare | **"Questo appuntamento non è più annullabile online. Chiama il [posto]."** | Dà una via d'uscita |
| Generico | **"Qualcosa è andato storto. Riprova tra poco."** | Mai stack/codici tecnici |

**Regola:** mai mostrare codici errore grezzi o messaggi backend. Sempre una via d'uscita.

---

## Conferme / Success

- Titolo: **"Tutto pronto ✓"** (confermato) / **"Richiesta inviata"** (approvazione richiesta).
- Recap: servizio · data/ora · operatore · prezzo (dal record reale ✔).
- Rassicurazione: **"Ti ricorderemo 24 ore prima."** (se reminder attivo).
- Se in attesa: **"Ti avvisiamo appena [posto] conferma."**
- Azioni: **Aggiungi al calendario** · **Indicazioni** · **Le mie prenotazioni**.

---

## Notifiche push

| Tipo | Titolo | Corpo |
|------|--------|-------|
| Conferma | **"Prenotazione confermata"** | "[Servizio] il [giorno] alle [ora] con [operatore]." |
| Reminder 24h | **"Ci vediamo domani"** | "[Servizio] alle [ora]. Tocca per i dettagli." |
| Richiesta approvata | **"Confermato ✓"** | "[Posto] ha confermato il tuo appuntamento del [giorno]." |
| Slot liberato (waitlist) | **"Si è liberato un orario"** | "[Giorno] alle [ora]. Prenota prima che vada." |
| Cancellazione (dal posto) | **"Appuntamento annullato"** | "Il [giorno] è stato annullato. Riprenota quando vuoi." |

**Tono push:** come un messaggio dell'attività, breve, con il beneficio in chiaro. Mai marketing.

---

## Terminologia per verticale (estratto)

| Concetto | Barber | Beauty | Medico | Coach/PT |
|----------|--------|--------|--------|----------|
| CTA | "Prenota il taglio" | "Prenota il trattamento" | "Prenota la visita" | "Prenota la sessione" |
| Operatore | "Barbiere" | "Operatrice" | "Dottore" | "Coach" |
| Servizio | "Servizio" | "Trattamento" | "Prestazione" | "Sessione" |
| Storico vuoto | "Nessun taglio ancora" | "Nessun trattamento ancora" | "Nessuna visita ancora" | "Nessuna sessione ancora" |

*(Pacchetti gestiti dal sistema white-label; qui per coerenza di voce.)*

---

## Checklist microcopy

- [ ] Ogni pulsante dice cosa farà
- [ ] Nessun testo tecnico o codice errore visibile
- [ ] Ogni empty state ha un'azione
- [ ] Ogni errore ha una via d'uscita
- [ ] Rassicurazione reminder sul success
- [ ] Terminologia del settore ovunque compaia "servizio/operatore"
- [ ] Nessuna parola di troppo
