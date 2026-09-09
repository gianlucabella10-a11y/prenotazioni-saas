# CUSTOMER APP UX ARCHITECTURE (FASE 2 · 4 · 7 · 8)

> Home, analisi UX per-schermata, accessibilità e benchmark competitivo. Progettazione, non implementazione.
> Basato sull'app reale ([CURRENT_CUSTOMER_APP.md](CURRENT_CUSTOMER_APP.md)). Missione: **il miglior software di prenotazione**, senza funzioni estranee.

---

## FASE 2 — LA HOME

### Cosa deve vedere nei primi 3 secondi
1. **Chi è** — logo/nome/foto del posto (identità immediata, "è il mio barbiere").
2. **Un solo grande pulsante "Prenota"** — l'azione dominante, impossibile da mancare.
3. **Il prossimo appuntamento** (se esiste) — glanceable, con data/ora/operatore.

### Cosa deve capire (in un colpo d'occhio)
- "Questa è l'app di **[posto]**."
- "Posso **prenotare in un tap**."
- "Ho già un appuntamento **il [giorno]**." (se applicabile)

### Cosa deve poter fare
- **Prenotare** (primario, sempre).
- **Prenota di nuovo [ultimo servizio]** (per l'abituale — oggi ASSENTE).
- Vedere/gestire il prossimo appuntamento.
- Raggiungere Info attività e Profilo (secondari, discreti).

### Cosa NON deve vedere
- Nessuna funzione fuori dal booking (chat, marketplace, offerte, loyalty…).
- Nessun secondo CTA che compete col "Prenota".
- Nessun banner marketing, nessun feed, nessuna griglia di icone.
- Nessun gergo tecnico, nessuna configurazione.

### Regola della Home
> **Una schermata, una missione: portare a "Prenota".** Se un elemento non aiuta a prenotare o a gestire una
> prenotazione, non sta in Home. La Home attuale è già vicina a questo; va solo aggiunto il re-booking e tolto
> ogni elemento che distrae dal CTA.

---

## FASE 4 — ANALISI UX PER SCHERMATA

Domande per ognuna: *troppo lunga? troppe info? elementi inutili? può essere più semplice?*

| Schermata | Troppo lunga? | Troppe info? | Elementi inutili? | Semplificazione |
|-----------|---------------|--------------|-------------------|-----------------|
| **Splash** | No | No | No | OK. Aggiungere transizione a skeleton se lenta. |
| **Home** | No | Ai limiti | I doppi link "card" possono diluire il CTA | Un solo CTA dominante + "Prenota di nuovo"; secondari in coda. |
| **Servizi** | No | No | No | OK. Se molti servizi → raggruppare per categoria; default single-tap resta. |
| **Data+Operatore+Orario** | No (densa ma giusta) | No | No | **Ottima.** Aggiungere "Chiunque", "Prima disponibilità", riepilogo sticky. |
| **Conferma (bottone)** | — | No | No | Aggiungere **prezzo+servizio** sul bottone. |
| **Successo** | **Troppo corta (vuota)** | No | — | **Aggiungere azioni**: calendario, indicazioni, reminder-copy, animazione. |
| **Registrazione** | **Troppo lunga per un booking** | Sì (5 campi + password) | Password ≥10 come prima barriera | **Spostare a fine flusso**; capture minimo + OTP. |
| **Verifica email** | No | No | — | Buona in sé; **non deve essere un muro pre-booking**. Autofill SMS/OTP se possibile. |
| **Le mie prenotazioni** | No | No | — | Aggiungere **Sposta / Calendario / Indicazioni / Prenota di nuovo**. |
| **Info attività** | Lunga (ricca) | Ai limiti | No (è la "scheda") | OK come schermata secondaria; non deve competere col booking. |
| **Profilo** | No | No | No | OK, standard. |

**Verdetto FASE 4:** le schermate di **booking** sono già asciutte e ben progettate. I problemi UX stanno agli
**estremi del flusso**: ingresso (registrazione troppo lunga, a monte) e uscita (successo troppo vuota). È lì che
va concentrata la semplificazione.

---

## FASE 7 — ACCESSIBILITÀ

| Area | Requisito | Stato/azione |
|------|-----------|--------------|
| **Caratteri** | Rispettare la dimensione testo di sistema (Dynamic Type / font scale) | Il tema ha `typography.scale`; garantire che rispetti anche l'impostazione OS, senza troncamenti. |
| **Contrasto** | WCAG AA su testo e **su chip/slot** | Backend valida la palette (✔); estendere la garanzia a chip slot/operatore e stati selezionati. |
| **Touch target** | ≥ 48dp per slot, chip, bottoni | Slot/chip devono avere area toccabile ampia; oggi ChoiceChip va verificato ≥44–48dp. |
| **Anziani** | Testo grande, pochi passi, poca digitazione | "Chiunque"+"Prima disponibilità"+OTP+autofill riducono digitazione; un'azione per schermata. |
| **Utenti poco esperti** | Nessun gergo, azione primaria ovvia, errori indulgenti | Copy semplice (vedi MICROCOPY_GUIDE), un solo pulsante primario, sempre una via d'uscita. |
| **Prima apertura** | Esplorabile senza account, nessun muro | Home/servizi/disponibilità visibili da ospite; account solo alla conferma. |
| **Screen reader** | Label semantiche su icone/slot | Verificare `Semantics` su icone informative e slot orari. |
| **Motion sensibile** | Rispettare "riduci animazioni" | Il livello motion (progettato nel white-label) deve avere "none"; rispettare reduce-motion di sistema. |
| **Solo-colore** | Mai segnalare stato solo col colore | Slot/servizio selezionato: colore **+** icona/segno (oggi il servizio usa check ✔; replicare sugli slot). |

**Principio:** l'app deve essere prenotabile **da un ottantenne al primo tentativo**, con testo grande, pochi tap,
zero digitazione obbligatoria oltre il numero di telefono.

---

## FASE 8 — BENCHMARK (ispirazione, non copia)

| Software | Cosa fa meglio | Cosa fa peggio | Cosa prendiamo |
|----------|----------------|----------------|----------------|
| **Booksy** | Re-booking rapido, promemoria, profili operatore | Marketplace rumoroso, upsell, affollato | Re-booking + reminder, **senza** il rumore marketplace |
| **Fresha** | Booking pulito, buon calendario | Spinge pagamenti/marketing | Pulizia del booking, **senza** attrito pagamenti |
| **Treatwell** | Discovery/recensioni | Pesante, ad-driven, lento | Nulla di marketplace; solo velocità |
| **Calendly** | Semplicità assoluta "scegli uno slot", link | B2B, niente servizi/operatori verticali | **Radicale semplicità** del "pick a slot" |
| **Google Calendar** | Aggiungi-al-calendario, reminder, glanceability | Non è booking verticale | **Integrazione calendario** + glance del prossimo appuntamento |
| **Apple Calendar** | Frictionless, nativo, affidabile | Non prenota nulla | Add-to-calendar nativo, zero attrito |
| **Mindbody** | Ricchissimo (classi, membership) | Bloated, lento, complesso | Come **non** essere: niente bloat |
| **Square Appointments** | Conferma pulita, integrazione | POS-centrico, US-centrico | Chiarezza della conferma |

### Cosa possiamo fare **meglio di tutti**
1. **La prima prenotazione più veloce del mercato** — ospite + OTP + default intelligenti: nessuno rende il *primo* booking davvero <60s.
2. **Sensazione "app fatta apposta"** per ogni attività (white-label vero), non un marketplace con loghi.
3. **Zero distrazioni** — solo prenotazione: niente marketplace, upsell, pagamenti, chat. La calma è il prodotto.
4. **Dark mode accessibile automatica + guardrail di contrasto** — raro e premium.
5. **Onboarding dell'attività in minuti** (un solo binario) → più clienti, più app, stessa qualità.

### Il posizionamento in una frase
> **"La velocità e la pulizia di Calendar, applicate alla prenotazione di servizi, con l'anima del tuo posto.
> Nessun marketplace. Nessuna distrazione. Prenoti e hai chiuso."**
