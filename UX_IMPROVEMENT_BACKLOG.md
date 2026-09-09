# UX IMPROVEMENT BACKLOG (FASE 4)

> Le migliorie ordinate per **ROI = impatto percepito ÷ sforzo**. Non le più complesse: quelle che fanno dire
> **"questa app è fantastica"**. Progettazione, non implementazione. Basato sull'app reale
> ([CURRENT_CUSTOMER_APP.md](CURRENT_CUSTOMER_APP.md)). Legenda sforzo: **XS · S · M · L**.

---

## Dove concentrarsi (sintesi FASE 4)

Il **booking core è già ottimo**. I guadagni stanno agli **estremi del flusso**:
- **Ingresso** troppo lento (registrazione + verifica prima di prenotare).
- **Uscita** troppo povera (success screen cieca, niente calendario/indicazioni/reschedule/rebook).
- **Micro-percezione** (skeleton, animazioni, riepiloghi, "Chiunque", "prima disponibilità").

---

## 🎯 TOP 20 per ROI

### Blocco A — Magia immediata (fai subito: tutte XS/S)

| # | Miglioria | Perché fa "wow" | Sforzo |
|---|-----------|-----------------|--------|
| 1 | **Aggiungi al calendario** (success + storico) | Il gesto più utile dopo aver prenotato; sensazione "app seria" | S |
| 2 | **"Chiunque" come operatore di default** | Meno scelte, più slot, un tap in meno | S |
| 3 | **Rassicurazione reminder** sul success ("Ti ricorderemo 24h prima") | Fiducia immediata, zero sforzo | XS |
| 4 | **Prezzo + servizio sul bottone di conferma** | Toglie l'ultima esitazione prima del commit | S |
| 5 | **Oggi/Domani + mese** sullo strip giorni | Chiarezza istantanea, meno errori di data | S |
| 6 | **Indicazioni (mappa)** da success/storico | Utile e "premium", un tap | S |
| 7 | **Animazione + haptic** alla conferma | Il "momento" emotivo che si ricorda | S |
| 8 | **Riepilogo sticky** (servizio·operatore·giorno) nello Step 2 | Contesto sempre visibile, meno ansia | S |

### Blocco B — Grande valore, sforzo contenuto (fai a ruota)

| # | Miglioria | Perché fa "wow" | Sforzo |
|---|-----------|-----------------|--------|
| 9 | **"Primo orario libero"** (quick-book) | Prenoti in 2 tap, sensazione di velocità estrema | S–M |
| 10 | **"Prenota di nuovo [ultimo]"** in home/storico | L'abituale prenota in ~10s; fedeltà | S–M |
| 11 | **Skeleton loaders** al posto degli spinner | L'app *sembra* più veloce ovunque | M |
| 12 | **Empty-state illustrati** + copy amichevole | Calore invece di testo nudo | M |
| 13 | **Annulla con feedback immediato** ("slot liberato") | Controllo e trasparenza | S |
| 14 | **Indicatore "quasi pieno"** sugli slot (scarsità onesta) | Urgenza gentile, decisione più rapida | S |

### Blocco C — Alto valore, media complessità (pianifica)

| # | Miglioria | Perché fa "wow" | Sforzo |
|---|-----------|-----------------|--------|
| 15 | **Sposta appuntamento (reschedule)** dallo storico | Evita cancella+riprenota; enorme comodità | M |
| 16 | **Waitlist "avvisami se si libera"** su 0 slot | Recupera prenotazioni perse (tabella backend già esiste) | M |
| 17 | **Operatore preferito / preferiti** | Personalizzazione, ri-booking più rapido | M |
| 18 | **Onboarding 2 card brandizzate** alla prima apertura | Prima impressione, identità | M |

### Blocco D — Le due scommesse strategiche (massimo impatto sulla *prima* conversione)

| # | Miglioria | Perché fa "wow" | Sforzo |
|---|-----------|-----------------|--------|
| 19 | **Verifica email spostata a DOPO il primo booking** | Elimina il muro d'ingresso: prenoti *poi* confermi | M |
| 20 | **OTP telefono / checkout ospite (passwordless)** | Prima prenotazione in ~40s senza password: nessun concorrente lo fa bene | L |

---

## Perché questo ordine

- I **Blocchi A–B (14 voci)** sono quasi tutti **S o meno** e coprono l'80% del "wow" percepito: calendario,
  "Chiunque", prezzo sul bottone, animazione, prima-disponibilità, prenota-di-nuovo, skeleton. **Sono la priorità.**
- Il **Blocco D** costa di più ma è ciò che sblocca la **crescita** (la prima conversione oggi è il collo di
  bottiglia). Vale come "grande scommessa" una volta chiuso il resto.
- **Nessuna** di queste voci aggiunge funzioni fuori dal dominio prenotazioni: sono tutte *miglior booking*.

---

## Cosa NON fare (per non tradire la missione)

- Niente chat, marketplace, pagamenti, loyalty, coupon, AI, ecc.
- Niente feed, niente "scopri", niente gamification.
- Niente schermate di configurazione per il cliente finale.
- Ogni aggiunta deve superare il test: **"rende la prenotazione più veloce, più chiara o più rassicurante?"**
  Se no, non entra.
