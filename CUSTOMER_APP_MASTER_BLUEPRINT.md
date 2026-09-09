# CUSTOMER APP — MASTER BLUEPRINT

> **Documento definitivo della Customer Experience.** Una volta approvato, ogni modifica futura dovrà rispettarlo.
> Progettazione, non implementazione. Nessun codice, nessuna migration, nessun test.
>
> **Basi progettuali usate:** `CURRENT_CUSTOMER_APP.md`, `CUSTOMER_APP_UX_ARCHITECTURE.md`, `BOOKING_EXPERIENCE.md`,
> `APP_PHILOSOPHY.md`, `MICROCOPY_GUIDE.md`, `CUSTOMER_FLOW.md`, `UX_IMPROVEMENT_BACKLOG.md`,
> `WHITE_LABEL_CUSTOMIZATION_ARCHITECTURE.md`, `REAL_PROJECT_STATE.md`, `FOUNDER_ONE_CLICK_FLOW.md`.
> *Nota onesta:* i documenti `WHITE_LABEL_DESIGN_SYSTEM.md` e `MOTION_SYSTEM.md` citati nel brief **non esistono
> nel repository**; la loro sostanza (design system, motion) è consolidata qui e nella White-Label Architecture.
> Nulla in questo blueprint contraddice `APP_PHILOSOPHY.md`.

---

# PARTE 0 — FONDAMENTA DI SISTEMA

## 0.1 I 10 principi (vincolanti)
Meno di 60 secondi · Meno tap possibili · Zero rumore · Una sola azione principale · Semplicità Apple ·
Sembra costruita per quel negozio · Rassicurante · Elegante · Premium · Velocissima.
*(Estesi in `APP_PHILOSOPHY.md`. Ogni scheda qui sotto è verificata contro questi principi.)*

## 0.2 Modello di navigazione
**Hub-and-spoke, nessuna bottom navigation.** La Home è il centro; tutto si raggiunge in push e torna alla Home.
Motivo: la bottom nav suggerisce "molte funzioni". Noi ne abbiamo **una**. Una barra in fondo sarebbe rumore.

```
Splash → (account solo al checkout) → HOME ★
   HOME → Prenota → Servizi → [Operatore·Data·Orario = 1 schermo] → Conferma → Successo
   HOME → Le mie prenotazioni → Dettaglio (Sposta / Calendario / Indicazioni / Annulla)
   HOME → Informazioni attività
   HOME → Profilo
Stati di sistema trasversali: Loading · Empty · Error · Offline
```

## 0.3 Decisione di flusso più importante del blueprint
**L'account è il checkout, non il cancello.** Il cliente **esplora e sceglie da ospite**; l'identità si chiede
**alla conferma**, nel modo più leggero possibile. Login/Registrazione/Verifica **non stanno più all'ingresso**:
vivono come passo finale, minimale, del primo booking. *(Cambio rispetto all'app attuale, dove sono un muro
a monte — vedi `CURRENT_CUSTOMER_APP.md`.)*

## 0.4 Budget di tempo (obiettivi vincolanti)
- Primo cliente: **< 60s** (oggi 2-4 min per il muro d'ingresso → da abbattere).
- Cliente abituale: **< 20s** (con "Prenota di nuovo").
- Ogni singola schermata di booking: **< 10s**.

## 0.5 Motion System (consolidato)
Quattro primitive, nient'altro. Durate brevi, curve morbide, **sempre** rispetto di "riduci animazioni" di sistema.

| Primitiva | Uso | Durata | Note |
|-----------|-----|--------|------|
| **Fade-through** | cambio schermata | 200–250ms | continuità, mai slide aggressivi |
| **Scale-in soft** | comparsa card/foglio | 150–200ms | entrata elegante |
| **Selection pop** | scelta slot/servizio | 80–120ms + haptic leggero | feedback tattile |
| **Success bounce** | conferma prenotazione | 300–400ms + haptic | l'unico "momento" espressivo |

**Animazioni che NON esistono nell'app:** parallax, bounce ovunque, spinner infiniti, transizioni >400ms,
elementi che entrano da direzioni diverse, micro-animazioni decorative senza funzione.

## 0.6 Tono di voce
Umano, breve, positivo, concreto, del settore. Ogni pulsante dice cosa farà. *(Sistema completo in `MICROCOPY_GUIDE.md`.)*

## 0.7 Regole globali di ogni schermata
1. **Una sola azione primaria**, visivamente dominante.
2. **Skeleton, mai spinner** per liste.
3. **Nessun vicolo cieco**: ogni stato ha una via in avanti.
4. **Contesto sempre visibile** durante il booking.
5. **White-label vero**: tipografia, colori, foto, parole del negozio (design system nella White-Label Architecture).
6. **Touch target ≥ 48dp**, contrasto WCAG AA, stato mai solo-colore.

---

# PARTE 1 — LE 19 SCHEDE SCHERMATA

> Formato costante. "Gerarchia occhio" = ordine in cui l'occhio deve cadere (1° → 2° → ultimo).
> Le schede **Operatore · Data · Orario** descrivono tre decisioni logiche che **compongono UN solo schermo
> fisico** (lo schermo "Quando"), coerente col principio "meno tap".

---

### 1 · SPLASH
- **Missione:** aprire la porta del brand mentre carica la config.
- **Emozione:** "sono nel posto giusto", calma.
- **Azione principale:** nessuna (attesa) · **Secondaria:** nessuna.
- **NON deve esserci:** spinner infinito, testo di caricamento, versione, copyright, tips.
- **Info importanti:** logo/nome del negozio. · **Da eliminare:** tutto il resto.
- **Tempo medio:** ≤1.5s · **Decisioni:** 0 · **Tap:** 0.
- **Gerarchia occhio:** 1° logo → 2° (nulla) → ultimo (nulla).
- **Animazioni sì:** fade+scale del logo in entrata; transizione a **skeleton Home** se lento. · **No:** spinner, progress bar.
- **Microcopy:** nessuna (il brand parla). · **Tono:** silenzioso.
- **Errori da evitare:** restare bloccati su spinner; mostrare errore tecnico se la config tarda (→ retry gentile).
- **Benchmark & come batterli:** Apple-grade: più pulito dello splash di Booksy/Fresha (che mostrano brand generico dell'app). Da noi lo splash è **del negozio**, non dell'app-piattaforma.

### 2 · LOGIN *(al checkout o per rientro, non all'ingresso)*
- **Missione:** far rientrare in 1 gesto chi ha già un account.
- **Emozione:** "facile, mi ricordano".
- **Azione principale:** Accedi · **Secondaria:** "Prima volta? Prenota e basta" (porta al flusso ospite).
- **NON deve esserci:** social wall, caroselli, promo, campi extra.
- **Info importanti:** email + password (o, in futuro, OTP). · **Da eliminare:** qualsiasi cosa oltre 2 campi.
- **Tempo medio:** 10–15s · **Decisioni:** 1 · **Tap:** 2–3.
- **Gerarchia occhio:** 1° campo email → 2° pulsante Accedi → ultimo link registrazione.
- **Animazioni sì:** transizione fade; shake leggero su errore credenziali. · **No:** nulla di decorativo.
- **Microcopy:** titolo "Bentornato"; pulsante "Accedi". · **Tono:** caloroso, breve.
- **Errori da evitare:** messaggi tecnici; bloccare senza spiegare; nascondere il recupero password.
- **Benchmark & come batterli:** più asciutto di Booksy (che spinge subito account/marketplace). Da noi il login **non è mai un ostacolo alla prima prenotazione**.

### 3 · REGISTRAZIONE *(minimale, al momento di confermare)*
- **Missione:** identificare il cliente col minimo attrito, **dopo** che ha già scelto lo slot.
- **Emozione:** "manca solo un secondo".
- **Azione principale:** Conferma prenotazione (crea account implicitamente) · **Secondaria:** "Ho già un account".
- **NON deve esserci:** password lunga obbligatoria come prima barriera, campi facoltativi in primo piano, ToS a muro.
- **Info importanti:** nome + telefono (o email) + consenso privacy inline. · **Da eliminare:** cognome/campi opzionali dal percorso critico (chiedibili dopo).
- **Tempo medio:** 15–25s · **Decisioni:** 1 (confermo) · **Tap:** 3–4.
- **Gerarchia occhio:** 1° riepilogo prenotazione → 2° campo identità → ultimo pulsante conferma.
- **Animazioni sì:** foglio che sale (scale-in). · **No:** step multipli animati.
- **Microcopy:** "Ci serve solo un contatto per confermare." · **Tono:** rassicurante, non burocratico.
- **Errori da evitare:** chiedere l'account **prima** della scelta slot (errore attuale); password ≥10 come muro; far scrivere troppo.
- **Benchmark & come batterli:** Calendly identifica al checkout con pochissimo; noi lo facciamo **dentro** il flusso di prenotazione, senza schermata separata → più veloce di Fresha/Booksy.
- **Proposta futura:** OTP telefono / passwordless (elimina del tutto la password) — *bigger bet, vedi backlog #20*.

### 4 · VERIFICA
- **Missione:** provare che il contatto è del cliente, **senza bloccare la prenotazione già fatta**.
- **Emozione:** "un ultimo tocco e ho finito".
- **Azione principale:** inserire codice · **Secondaria:** invia di nuovo.
- **NON deve esserci:** muro che impedisce di vedere la prenotazione appena creata; timer stressanti.
- **Info importanti:** dove è stato inviato il codice, campo 6 cifre. · **Da eliminare:** istruzioni lunghe.
- **Tempo medio:** 10–20s · **Decisioni:** 1 · **Tap:** 1 (con autofill SMS/OTP idealmente 0 digitazioni).
- **Gerarchia occhio:** 1° campo codice → 2° pulsante Verifica → ultimo "invia di nuovo".
- **Animazioni sì:** avanzamento morbido a conferma. · **No:** shake aggressivo.
- **Microcopy:** "Confermiamo che sei tu"; "Ti abbiamo inviato un codice a [contatto]." · **Tono:** gentile.
- **Errori da evitare:** far uscire dall'app per copiare il codice (usare autofill); trattare la verifica come un cancello pre-booking (errore attuale).
- **Benchmark & come batterli:** con autofill OTP battiamo tutti (Booksy/Fresha richiedono spesso email verificata a monte). Da noi la verifica è **contestuale e leggera**.

### 5 · HOME ★
- **Missione:** portare a "Prenota" in un colpo d'occhio; per l'abituale, ri-prenotare in 1 tap.
- **Emozione:** "questa è l'app del mio posto, ci prenoto in un secondo".
- **Azione principale:** **Prenota** · **Secondaria:** "Prenota di nuovo [ultimo]" / prossimo appuntamento.
- **NON deve esserci:** feed, offerte, marketplace, secondo CTA che compete, griglie di icone, marketing.
- **Info importanti:** identità (logo/foto/nome), **un** grande CTA, prossimo appuntamento. · **Da eliminare:** link secondari ridondanti che diluiscono il CTA.
- **Tempo medio:** 3–6s · **Decisioni:** 1 · **Tap:** 1.
- **Gerarchia occhio:** 1° identità/hero → 2° **Prenota** → ultimo prossimo appuntamento / link info.
- **Animazioni sì:** hero fade-in; skeleton mentre carica il prossimo appuntamento. · **No:** caroselli, banner animati.
- **Microcopy:** CTA "Prenota" (o verticale "Prenota il taglio"); saluto brandizzato. · **Tono:** personale, del negozio.
- **Errori da evitare:** trasformarla in dashboard; più CTA equivalenti; nascondere il prossimo appuntamento.
- **Benchmark & come batterli:** Booksy/Fresha aprono su marketplace/ricerca; noi apriamo su **un solo negozio e un solo pulsante**. Più focalizzata di tutti.

### 6 · SERVIZI
- **Missione:** far scegliere *cosa* in un colpo d'occhio.
- **Emozione:** "so cosa voglio, è chiaro".
- **Azione principale:** selezionare 1 servizio → Continua · **Secondaria:** aprire dettaglio varianti.
- **NON deve esserci:** upsell, "consigliati", prezzi barrati, badge promo.
- **Info importanti:** nome, durata, prezzo. · **Da eliminare:** descrizioni lunghe in lista (vanno nel dettaglio a richiesta).
- **Tempo medio:** 5–10s · **Decisioni:** 1 (di solito) · **Tap:** 1–2.
- **Gerarchia occhio:** 1° nome servizio → 2° prezzo/durata → ultimo icona info.
- **Animazioni sì:** selection pop sulla card; foglio dettaglio scale-in. · **No:** riordini animati, shimmer eccessivo.
- **Microcopy:** titolo "Cosa prenoti?"; pulsante "Continua". · **Tono:** diretto.
- **Errori da evitare:** obbligare a capire la multi-selezione (default single-tap); liste lunghe senza categorie.
- **Benchmark & come batterli:** più pulito del catalogo Fresha (fitto di upsell). Da noi la lista è **solo servizi**, senza spinte commerciali.

### 7 · OPERATORE *(parte dello schermo "Quando")*
- **Missione:** togliere la scelta a chi non ha preferenze.
- **Emozione:** "non devo pensarci".
- **Azione principale:** default **"Chiunque"** · **Secondaria:** scegliere una persona.
- **NON deve esserci:** obbligo di scelta; operatori che non fanno quel servizio (già filtrati).
- **Info importanti:** nome operatore, chip "Chiunque" in testa. · **Da eliminare:** foto grandi/bio in questo punto (vanno in Info attività).
- **Tempo medio:** 0–5s (spesso 0) · **Decisioni:** 0–1 · **Tap:** 0–1.
- **Gerarchia occhio:** 1° chip "Chiunque" → 2° operatori → ultimo (nulla).
- **Animazioni sì:** selection pop. · **No:** carosello animato.
- **Microcopy:** "Con chi?" · default "Chiunque". · **Tono:** leggero.
- **Errori da evitare:** rendere obbligatorio scegliere una persona (limite attuale: manca "Chiunque").
- **Benchmark & come batterli:** "Chiunque" massimizza gli slot come Fresha, ma **senza schermata dedicata**: sta insieme a data e orario. Meno tap di tutti.

### 8 · DATA *(parte dello schermo "Quando")*
- **Missione:** arrivare al giorno giusto col minimo scorrimento.
- **Emozione:** "vedo subito quando posso".
- **Azione principale:** toccare un giorno · **Secondaria:** "Primo orario libero".
- **NON deve esserci:** calendario mensile pesante come default; giorni non prenotabili indistinti.
- **Info importanti:** Oggi/Domani evidenziati, giorni con disponibilità. · **Da eliminare:** anni/mesi lontani in primo piano.
- **Tempo medio:** 3–8s · **Decisioni:** 1 · **Tap:** 1.
- **Gerarchia occhio:** 1° Oggi/Domani → 2° giorni successivi → ultimo scorrimento lungo.
- **Animazioni sì:** scroll fluido, selection pop. · **No:** flip calendario, transizioni mese aggressive.
- **Microcopy:** "Quando ti va bene?"; etichette "Oggi/Domani". · **Tono:** colloquiale.
- **Errori da evitare:** strip senza contesto mese; non segnalare i giorni pieni; default diverso da oggi.
- **Benchmark & come batterli:** lo strip veloce batte il calendario mensile di Booksy per la prenotazione ravvicinata (il caso reale del 90%). "Primo orario libero" fa ciò che Calendly non offre in mobile.

### 9 · ORARIO *(parte dello schermo "Quando")*
- **Missione:** un tap sullo slot.
- **Emozione:** "ci sono, scelgo e ho finito".
- **Azione principale:** toccare uno slot · **Secondaria:** cambiare giorno/operatore.
- **NON deve esserci:** griglie fitte illeggibili; slot minuscoli; pubblicità di orari premium.
- **Info importanti:** slot per fascia (Mattina/Pomeriggio), eventuale "quasi pieno". · **Da eliminare:** dettagli tecnici (durata già nota).
- **Tempo medio:** 3–8s · **Decisioni:** 1 · **Tap:** 1.
- **Gerarchia occhio:** 1° prima fascia disponibile → 2° slot selezionato → ultimo pulsante conferma.
- **Animazioni sì:** selection pop + haptic; il bottone si trasforma in "Conferma · [ora] · [prezzo]". · **No:** lampeggii, countdown.
- **Microcopy:** "Mattina/Pomeriggio"; bottone che diventa conferma. · **Tono:** essenziale.
- **Errori da evitare:** touch target piccoli; nessun feedback sullo slot scelto; slot vuoti senza via d'uscita (→ waitlist).
- **Benchmark & come batterli:** raggruppamento Mattina/Pomeriggio + conferma inline = meno passaggi di Fresha/Booksy (che spesso hanno una schermata riepilogo extra). Densità Calendly, calore del negozio.

### 10 · CONFERMA
- **Missione:** confermare con certezza in un tap, senza schermate ridondanti.
- **Emozione:** "so esattamente cosa sto prenotando".
- **Azione principale:** **Conferma · [servizio] · [ora] · [prezzo]** · **Secondaria:** modificare (torna indietro).
- **NON deve esserci:** una seconda schermata di riepilogo separata; costi nascosti; upsell finale.
- **Info importanti:** servizio, data/ora, operatore, prezzo. · **Da eliminare:** tutto ciò che non è il riepilogo essenziale.
- **Tempo medio:** 3–8s · **Decisioni:** 1 · **Tap:** 1 (+ identità se ospite).
- **Gerarchia occhio:** 1° riepilogo → 2° pulsante conferma → ultimo "modifica".
- **Animazioni sì:** pressione bottone → success bounce alla riuscita. · **No:** modali multipli.
- **Microcopy:** bottone completo di prezzo; "Ci sei quasi." · **Tono:** rassicurante.
- **Errori da evitare:** confermare senza mostrare il prezzo (limite attuale: bottone mostra solo l'ora); doppio riepilogo.
- **Benchmark & come batterli:** conferma **diretta** (niente review page) → più veloce di Booksy/Fresha; la trasparenza prezzo la rende affidabile come Apple.

### 11 · SUCCESSO
- **Missione:** chiudere con un "momento" e offrire l'azione utile successiva.
- **Emozione:** gioia + fiducia ("fatto, e mi ricorderanno").
- **Azione principale:** **Aggiungi al calendario** · **Secondaria:** Indicazioni / Le mie prenotazioni.
- **NON deve esserci:** vicolo cieco (solo "torna alla home"); cross-sell; richieste di recensione immediate.
- **Info importanti:** recap reale + "Ti ricorderemo 24h prima". · **Da eliminare:** dettagli tecnici, id prenotazione lungo.
- **Tempo medio:** 4–8s · **Decisioni:** 1 (azione utile) · **Tap:** 1.
- **Gerarchia occhio:** 1° icona/animazione successo → 2° recap → ultimo azioni (calendario/indicazioni).
- **Animazioni sì:** **success bounce + haptic** (l'unico momento espressivo). · **No:** coriandoli continui, loop.
- **Microcopy:** "Tutto pronto ✓" / "Richiesta inviata"; "Ti ricorderemo 24 ore prima." · **Tono:** caldo, orgoglioso ma sobrio.
- **Errori da evitare:** schermata cieca (limite attuale); niente calendario/indicazioni; nessuna rassicurazione reminder.
- **Benchmark & come batterli:** l'add-to-calendar nativo ci porta al livello di Apple Calendar; il "momento" + reminder ci mettono sopra Booksy/Fresha (che chiudono con un recap statico).

### 12 · LE MIE PRENOTAZIONI
- **Missione:** ritrovare, gestire e **ri-prenotare** in un tap.
- **Emozione:** "ho tutto sotto controllo".
- **Azione principale:** aprire il prossimo appuntamento · **Secondaria:** "Prenota di nuovo".
- **NON deve esserci:** solo la lista senza azioni; pubblicità; storico infinito senza gerarchia.
- **Info importanti:** prossime in alto (data/ora/operatore/stato), passate sotto. · **Da eliminare:** metadati tecnici.
- **Tempo medio:** 3–6s · **Decisioni:** 1 · **Tap:** 1–2.
- **Gerarchia occhio:** 1° prossimo appuntamento → 2° azioni rapide → ultimo storico passato.
- **Animazioni sì:** skeleton in caricamento; selection pop. · **No:** swipe complessi non scopribili.
- **Microcopy:** "Le tue prenotazioni"; badge stato ("Confermato"/"In attesa"). · **Tono:** ordinato.
- **Errori da evitare:** offrire solo l'annullamento (limite attuale: manca Sposta); nessun re-booking.
- **Benchmark & come batterli:** con Sposta + Calendario + Prenota di nuovo battiamo la gestione di Booksy; più pulito e veloce.

### 13 · DETTAGLIO PRENOTAZIONE
- **Missione:** dare tutti i dettagli di UN appuntamento e le azioni possibili.
- **Emozione:** "so cosa fare se cambia qualcosa".
- **Azione principale:** **Sposta** (se consentito) · **Secondaria:** Calendario / Indicazioni / Annulla.
- **NON deve esserci:** azioni pericolose senza conferma; costi sorpresa; recensioni forzate.
- **Info importanti:** servizio, data/ora, operatore, sede+mappa, stato, politica di cancellazione. · **Da eliminare:** log tecnici.
- **Tempo medio:** 5–10s · **Decisioni:** 1 · **Tap:** 1–2.
- **Gerarchia occhio:** 1° data/ora → 2° servizio/operatore → ultimo azioni (sposta/annulla).
- **Animazioni sì:** fade-through in ingresso; conferma morbida sull'azione. · **No:** transizioni pesanti.
- **Microcopy:** azioni chiare ("Sposta", "Aggiungi al calendario", "Annulla"); limiti spiegati con gentilezza. · **Tono:** trasparente.
- **Errori da evitare:** annulla senza conferma; nascondere la politica di cancellazione fino all'errore.
- **Benchmark & come batterli:** Sposta in-app + indicazioni + calendario in un unico posto: più completo del dettaglio di Fresha, più semplice di Mindbody.

### 14 · PROFILO
- **Missione:** gestire dati personali, consensi e uscita, senza fronzoli.
- **Emozione:** "i miei dati sono al sicuro e sotto controllo".
- **Azione principale:** modifica dati/consensi · **Secondaria:** link legali / logout / elimina account.
- **NON deve esserci:** impostazioni infinite, tema/config (è white-label del negozio), gamification, badge.
- **Info importanti:** nome, contatto, consensi, privacy/termini, elimina account. · **Da eliminare:** preferenze superflue.
- **Tempo medio:** 10–20s (raro) · **Decisioni:** 1 · **Tap:** 2–3.
- **Gerarchia occhio:** 1° dati personali → 2° consensi/legale → ultimo logout/elimina.
- **Animazioni sì:** nessuna oltre le transizioni standard. · **No:** decorazioni.
- **Microcopy:** etichette umane; elimina account chiaro (richiesto dagli store). · **Tono:** rispettoso, chiaro.
- **Errori da evitare:** seppellire "elimina account"; consensi ambigui.
- **Benchmark & come batterli:** conformità store (delete account sempre raggiungibile) + chiarezza GDPR: più pulito e rispettoso di Booksy/Mindbody.

### 15 · INFORMAZIONI ATTIVITÀ
- **Missione:** dare identità e contatti del negozio (la "scheda"), senza competere col booking.
- **Emozione:** "conosco questo posto, mi fido".
- **Azione principale:** contattare/raggiungere (telefono, WhatsApp, mappa) · **Secondaria:** social, orari, team.
- **NON deve esserci:** un secondo motore di prenotazione; feed; recensioni marketplace.
- **Info importanti:** foto/logo, orari, indirizzo+mappa, contatti, team. · **Da eliminare:** testi promozionali lunghi.
- **Tempo medio:** 10–20s · **Decisioni:** 0–1 · **Tap:** 1–2.
- **Gerarchia occhio:** 1° identità/foto → 2° contatti/mappa → ultimo team/social.
- **Animazioni sì:** fade-in immagini. · **No:** caroselli automatici.
- **Microcopy:** sezioni chiare ("Dove siamo", "Orari", "Il team"). · **Tono:** ospitale, del negozio.
- **Errori da evitare:** trasformarla in vetrina marketing; nasconderla o, al contrario, farla competere con "Prenota".
- **Benchmark & come batterli:** ricca come una scheda Booksy ma **senza il marketplace intorno**: è la scheda del *tuo* negozio, non un profilo in una directory.

### 16 · ERROR (stato di sistema)
- **Missione:** spiegare cosa è andato storto e come uscirne.
- **Emozione:** "nessun panico, so cosa fare".
- **Azione principale:** Riprova · **Secondaria:** contatta il negozio / torna indietro.
- **NON deve esserci:** stack trace, codici tecnici, colpevolizzazione.
- **Info importanti:** cosa è successo (in umano) + azione. · **Da eliminare:** dettagli tecnici.
- **Tempo medio:** 2–5s · **Decisioni:** 1 · **Tap:** 1.
- **Gerarchia occhio:** 1° messaggio → 2° Riprova → ultimo via d'uscita alternativa.
- **Animazioni sì:** comparsa morbida. · **No:** icone che tremano all'infinito.
- **Microcopy:** "Qualcosa è andato storto. Riprova tra poco." + via d'uscita. · **Tono:** calmo, mai allarmante.
- **Errori da evitare:** messaggi backend grezzi; nessuna azione; vicolo cieco.
- **Benchmark & come batterli:** errori "umani" con via d'uscita → superiori a Mindbody/Booksy (spesso criptici).

### 17 · OFFLINE (stato di sistema)
- **Missione:** dire che manca la rete e mostrare ciò che è comunque consultabile.
- **Emozione:** "non è colpa mia, e riprende da solo".
- **Azione principale:** Riprova · **Secondaria:** consultare i dati già in cache (prossimo appuntamento).
- **NON deve esserci:** blocco totale dell'app; messaggi tecnici.
- **Info importanti:** stato offline + cosa resta visibile. · **Da eliminare:** dettagli di rete.
- **Tempo medio:** 2–4s · **Decisioni:** 1 · **Tap:** 0–1.
- **Gerarchia occhio:** 1° banner offline → 2° contenuto in cache → ultimo Riprova.
- **Animazioni sì:** banner discreto che compare/scompare; riconnessione automatica. · **No:** pop-up bloccanti.
- **Microcopy:** "Connessione assente. Riprova quando torni online." · **Tono:** tranquillo.
- **Errori da evitare:** schermata bianca; perdere lo stato del booking in corso.
- **Benchmark & come batterli:** mostrare il prossimo appuntamento anche offline (glanceability tipo Apple Calendar) → meglio della maggior parte dei concorrenti che si bloccano.

### 18 · LOADING (stato di sistema)
- **Missione:** far percepire velocità mentre si caricano i dati.
- **Emozione:** "è già qui" (nessuna attesa percepita).
- **Azione principale:** nessuna · **Secondaria:** nessuna.
- **NON deve esserci:** spinner al centro, percentuali, "attendere prego".
- **Info importanti:** struttura della schermata in arrivo (skeleton). · **Da eliminare:** tutto il testo di attesa.
- **Tempo medio:** <1s percepito · **Decisioni:** 0 · **Tap:** 0.
- **Gerarchia occhio:** skeleton che rispecchia il layout reale.
- **Animazioni sì:** shimmer discreto sullo skeleton. · **No:** spinner infinito, salti di layout quando arrivano i dati.
- **Microcopy:** nessuna (o "Un istante…" solo se indispensabile). · **Tono:** invisibile.
- **Errori da evitare:** spinner generico; layout che "salta" all'arrivo dei dati.
- **Benchmark & come batterli:** skeleton ovunque = percezione di velocità stile Apple/Calendly, superiore agli spinner di Booksy/Fresha.

### 19 · EMPTY STATE (stato di sistema)
- **Missione:** trasformare un "vuoto" in un invito all'azione, con calore.
- **Emozione:** "non è un errore, so cosa fare".
- **Azione principale:** l'azione che riempie il vuoto (es. Prenota) · **Secondaria:** alternativa (waitlist/cambia giorno).
- **NON deve esserci:** testo nudo triste; icona di errore; vicolo cieco.
- **Info importanti:** illustrazione coerente col template + una frase + azione. · **Da eliminare:** spiegazioni lunghe.
- **Tempo medio:** 2–4s · **Decisioni:** 1 · **Tap:** 1.
- **Gerarchia occhio:** 1° illustrazione → 2° frase → ultimo azione.
- **Animazioni sì:** comparsa morbida dell'illustrazione. · **No:** animazioni ripetute.
- **Microcopy:** "Non hai ancora prenotato." + "Prenota"; "Niente libero questo giorno." + "Avvisami". · **Tono:** amichevole, propositivo.
- **Errori da evitare:** empty state a solo testo (limite attuale); nessuna azione.
- **Benchmark & come batterli:** empty state illustrati e "attivi" → più caldi e utili di quelli piatti di Fresha/Booksy.

---

# PARTE 2 — PROPOSTE FUTURE (annotazioni, fuori dal blueprint base)

Migliorie coerenti con la filosofia, da valutare **dopo** l'approvazione (non modificano il blueprint definitivo,
lo estendono). Dettaglio e ROI in `UX_IMPROVEMENT_BACKLOG.md`.

1. **OTP/passwordless** al checkout (elimina la password del tutto) — *bigger bet, massima conversione*.
2. **"Primo orario libero"** come scorciatoia globale dalla Home.
3. **Waitlist "avvisami se si libera"** (tabella backend già esistente, UI assente).
4. **Operatore preferito / preferiti** per ri-booking istantaneo.
5. **Onboarding 2 card brandizzate** alla prima apertura.
6. **Indicatore "quasi pieno"** sugli slot (scarsità onesta).

> Nessuna di queste aggiunge funzioni fuori dal dominio prenotazioni. Superano tutte il test:
> *"rende la prenotazione più veloce, più chiara o più rassicurante?"*

---

# PARTE 3 — CONTROLLO MODIFICHE

Una volta approvato:
1. Questo documento è la **fonte di verità** della Customer Experience.
2. Ogni modifica futura deve **citare** la scheda che cambia e **motivare** contro i 10 principi.
3. Nessuna schermata può introdurre un secondo CTA primario, una funzione fuori dominio, o un passo che aumenta i tap senza aumentare la chiarezza.
4. Il budget di tempo (<60s primo, <20s abituale) è un **requisito di accettazione**, non un auspicio.

---

# VERDETTO

**Customer Experience progettata: 92/100.**

Perché 92 e non di più: il blueprint elimina i due difetti reali di oggi (muro d'ingresso, coda cieca) e porta
il booking a un livello Apple-grade su ogni schermata, **restando fedele a "una sola cosa, fatta perfettamente"**.
Non arriva a 100 perché il salto definitivo (OTP/passwordless, waitlist, preferiti) resta **proposta futura**: è
la scelta corretta oggi (non gonfiare il primo rilascio), ma è ciò che manca per il "perfetto assoluto".

**Può diventare una delle migliori app di prenotazione sul mercato? Sì — a una condizione.**
Il motore e la disciplina ("zero rumore") sono già di categoria superiore. La differenza tra "molto buona" e
"la migliore" non sta in altre funzioni: sta nell'**esecuzione maniacale di questi dettagli** — default intelligenti,
account al checkout, add-to-calendar, skeleton, il "momento" di successo, la voce del negozio. Se implementata
**esattamente** come questo blueprint, sì: è credibilmente la migliore app di prenotazione **per singola attività**
sul mercato — perché nessun concorrente unisce *questa* velocità, *questa* pulizia e *questa* identità su misura.
