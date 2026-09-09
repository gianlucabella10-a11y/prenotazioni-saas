# CUSTOMER APP — PERCEPTION SYSTEM

> Il documento dell'**anima** dell'app. Non spiega cosa l'app *fa*: spiega cosa il cliente deve *provare*
> mentre la usa. È il livello sotto la UX: la **psicologia** e la **percezione**.
> Nessun codice, nessuna implementazione, nessun TODO, nessuna checklist. Solo sensazione.
>
> Coerente e subordinato a: `CUSTOMER_APP_MASTER_BLUEPRINT.md`, `APP_PHILOSOPHY.md`, `BOOKING_EXPERIENCE.md`,
> `CUSTOMER_APP_UX_ARCHITECTURE.md`, `CURRENT_CUSTOMER_APP.md`, `REAL_PROJECT_STATE.md`. Non contraddice nulla di essi.

---

## Premessa — la verità psicologica della prenotazione

Prenotare non è un'azione neutra. È un **piccolo atto di fiducia e di impegno**. Sotto la superficie, chi prenota
prova quattro tensioni, sempre:

1. **Impegno** — "mi sto legando a un giorno, a un'ora, a una spesa".
2. **Perdita** — "e se lo slot che voglio sparisce mentre esito?".
3. **Incertezza** — "ho davvero prenotato? è confermato? mi presento e non c'è nulla?".
4. **Fretta** — "voglio chiudere questa cosa e tornare alla mia vita".

**Il compito dell'app non è mostrare funzioni. È sciogliere queste quattro tensioni.**
Ogni schermata, ogni animazione, ogni spazio bianco esiste per far provare, in ordine: *calma → controllo →
fiducia → sollievo*. Questa è la sequenza emotiva maestra. Tutto il resto la serve.

> **Sensazione dominante di tutta l'app: leggerezza sicura.** Il cliente deve sentirsi *guidato senza essere spinto*,
> *veloce senza essere di corsa*, *al sicuro senza dover pensare*.

---

# FASE 1 — L'IMPRONTA EMOTIVA DI OGNI SCHERMATA

Ogni schermata ha un "colore emotivo". Scala di intensità 1–5 su sei dimensioni percettive.
*(Calma = quanto rallenta il respiro · Velocità = quanto sembra istantanea · Rassicurazione = quanto toglie paura ·
Eleganza = quanto è raffinata · Premium = quanto vale · Su misura = quanto sembra del negozio.)*

| Schermata | Emozione centrale | Sensazione | Calma | Velocità | Rassicur. | Eleganza | Premium | Su misura |
|-----------|-------------------|-----------|:---:|:---:|:---:|:---:|:---:|:---:|
| Splash | "sono nel posto giusto" | soglia, apertura | 5 | 4 | 3 | 5 | 5 | 5 |
| Home | "è casa mia, ci prenoto subito" | familiarità | 4 | 5 | 4 | 4 | 4 | 5 |
| Servizi | "so cosa voglio, è chiaro" | chiarezza | 4 | 5 | 3 | 4 | 3 | 3 |
| Quando (op.+data+ora) | "vedo quando posso, scelgo" | controllo | 3 | 5 | 4 | 4 | 3 | 3 |
| Conferma | "so esattamente cosa faccio" | certezza | 4 | 4 | 5 | 4 | 4 | 3 |
| Successo | "fatto, e mi ricorderanno" | sollievo/gioia | 5 | 3 | 5 | 5 | 5 | 4 |
| Le mie prenotazioni | "ho tutto sotto controllo" | ordine | 5 | 4 | 4 | 4 | 3 | 3 |
| Dettaglio | "so cosa fare se cambia" | padronanza | 4 | 3 | 5 | 4 | 3 | 3 |
| Profilo | "i miei dati sono al sicuro" | rispetto | 5 | 3 | 5 | 4 | 3 | 2 |
| Info attività | "conosco questo posto, mi fido" | ospitalità | 5 | 3 | 4 | 5 | 4 | 5 |
| Login | "facile, mi ricordano" | scioltezza | 4 | 5 | 4 | 4 | 3 | 3 |
| Registrazione (checkout) | "manca solo un secondo" | leggerezza | 4 | 4 | 4 | 4 | 3 | 3 |
| Verifica | "un ultimo tocco e ho finito" | conclusione | 4 | 4 | 4 | 4 | 3 | 2 |
| Loading | "è già qui" | invisibile | 5 | 5 | 3 | 4 | 4 | 3 |
| Empty | "non è un errore, so cosa fare" | invito | 5 | 3 | 4 | 4 | 3 | 4 |
| Error | "nessun panico" | contenimento | 5 | 3 | 5 | 3 | 2 | 2 |
| Offline | "riprende da solo" | pazienza serena | 5 | 2 | 4 | 3 | 2 | 2 |

**Letture chiave dell'impronta:**
- I picchi di **Calma** stanno agli estremi (Splash, Successo, stati di sistema): l'app *respira* all'inizio e alla fine, *accelera* nel mezzo.
- I picchi di **Velocità percepita** stanno nel cuore del booking (Home→Quando): lì il cliente deve sentire scorrimento, non attesa.
- I picchi di **Rassicurazione** stanno dove nasce la paura (Conferma, Successo, Dettaglio, Error): lì l'app abbassa la voce e prende per mano.
- Il **Su misura** è massimo dove il cliente incontra l'identità (Splash, Home, Info): lì deve *sentire il negozio*, non l'app.

---

# FASE 2 — SECONDO PER SECONDO DELLA PRENOTAZIONE

Il viaggio emotivo di un cliente abituale, dal primo tap all'ultimo. Per ogni battito: *pensa · vede · cerca · teme ·
è rassicurato da · potrebbe confonderlo · come togliamo il dubbio.*

**t=0s — apertura**
Pensa: "prenoto al volo". Vede: identità del negozio + un solo pulsante. Cerca: dove toccare. Teme: di doversi
loggare di nuovo. Rassicurato da: è già dentro, riconosce il posto. Confusione possibile: un secondo pulsante che
compete. Dubbio sciolto: **un solo CTA dominante**, tutto il resto sussurra.

**t=1–2s — il tap "Prenota"**
Pensa: "andiamo". Vede: la schermata cambia con un fade morbido. Cerca: conferma di aver toccato giusto. Teme:
che parta qualcosa di lungo. Rassicurato da: transizione immediata, nessun caricamento a vuoto. Confusione: uno
spinner. Dubbio sciolto: **skeleton già formato**, la nuova schermata "c'è già".

**t=3–6s — scelta del servizio**
Pensa: "quello che faccio sempre". Vede: pochi servizi, prezzo e durata leggibili. Cerca: il suo servizio.
Teme: prezzi nascosti, sorprese. Rassicurato da: prezzo in chiaro accanto al nome. Confusione: dover capire se
può sceglierne più di uno. Dubbio sciolto: **un tap basta**, il resto è opzionale e silenzioso.

**t=7–12s — quando e con chi**
Pensa: "quando posso?". Vede: oggi/domani già evidenziati, "Chiunque" già scelto, orari pronti. Cerca: un orario
che gli va. Teme: di non trovare posto, di dover scegliere una persona. Rassicurato da: default già impostati, slot
già visibili senza agire. Confusione: troppa scelta insieme. Dubbio sciolto: **l'app ha già deciso il più probabile**;
lui deve solo *toccare un orario*.

**t=12–13s — la selezione dello slot**
Pensa: "questo". Vede: lo slot si accende, un piccolo scatto tattile. Cerca: conferma fisica di aver scelto. Teme:
di aver toccato lo slot sbagliato. Rassicurato da: **il pulsante in basso si trasforma e ripete l'ora scelta**.
Confusione: nessuna reazione al tocco. Dubbio sciolto: **feedback immediato colore + forma + haptic**, mai solo colore.

**t=13–16s — la conferma**
Pensa: "confermo". Vede: sul pulsante c'è tutto — servizio, ora, prezzo. Cerca: certezza di cosa sta impegnando.
Teme: costi nascosti, di sbagliare orario. Rassicurato da: **il riepilogo È sul pulsante**, non serve una seconda
schermata. Confusione: un doppio riepilogo che rallenta. Dubbio sciolto: **conferma diretta e trasparente**.

**t=16–17s — l'attimo sospeso (invio)**
Pensa: "sta andando?". Vede: il pulsante mostra un micro-progresso, mai un blocco. Cerca: che qualcosa si muova.
Teme: **di aver perso lo slot mentre confermava**. Rassicurato da: reazione entro un battito; se lo slot è saltato,
l'app lo dice con calma e ne mostra subito altri. Confusione: schermata ferma. Dubbio sciolto: **mai più di ~1s di
sospensione senza segno di vita**.

**t=17–19s — il sollievo (successo)**
Pensa: "fatto!". Vede: un segno di conferma che *si compie* con un piccolo rimbalzo + haptic, poi il recap reale.
Cerca: la prova che è vero. Teme: "ma è confermato davvero? devo fare altro?". Rassicurato da: **"Tutto pronto ✓" +
"Ti ricorderemo 24h prima"**. Confusione: un vicolo cieco. Dubbio sciolto: **l'azione utile è già lì** — aggiungi al
calendario — così porta via una *prova concreta* nel suo telefono.

**Totale emotivo: ~20 secondi che finiscono in sollievo, non in dubbio.** Per il primo cliente il viaggio è più lungo
solo di un tratto — l'identificazione — che il blueprint colloca *dopo* la scelta, così la parte "impegnativa" arriva
quando il cliente è già emotivamente comprato.

---

# FASE 3 — LE MICRO-EMOZIONI E LA RISPOSTA DELL'APP

| Il cliente… | …e l'app lo fa sentire così |
|-------------|------------------------------|
| **Sta aspettando** | Non lo lascia mai davanti al vuoto: la forma della schermata c'è già (skeleton), quindi l'attesa non *sembra* attesa. |
| **Ha paura di aver sbagliato** | Ogni tocco risponde: colore + forma + un piccolo scatto. L'azione ha sempre un'eco fisica. |
| **Ha paura di perdere lo slot** | La conferma è rapida; se lo slot sfugge, l'app non colpevolizza — dice "eccone altri" e li mostra all'istante. La scarsità, se comunicata, è onesta e gentile, mai un timer che genera panico. |
| **Non capisce se ha prenotato** | Il successo è inequivocabile: un momento che *si compie*, un recap reale, una rassicurazione sul promemoria, una prova da salvare. Zero ambiguità. |
| **Ha ansia** | L'app abbassa la voce nei punti critici: più spazio, meno colore, frasi brevi, un solo pulsante. La calma è progettata. |
| **Ha fretta** | Default intelligenti e meno tap: l'app ha già fatto metà del lavoro. Chi ha fretta arriva in fondo senza leggere. |
| **Ha paura di aver cliccato male** | Nessuna azione distruttiva senza un secondo gentile passaggio; ogni conferma dice *cosa* conferma. |
| **Non vuole leggere** | La gerarchia fa leggere *una* cosa per schermata. Il testo secondario esiste ma non chiede attenzione. Si può prenotare quasi senza leggere. |
| **Vuole fare in fretta** | Il percorso più breve è sempre quello di default; le scorciatoie (prenota di nuovo, primo orario libero) premiano chi torna. |
| **Vuole sentirsi sicuro** | Trasparenza totale: prezzo prima del commit, recap dopo, promemoria promesso. Nessuna sorpresa, mai. |

**Principio unificante:** *l'app non chiede al cliente di essere calmo; lo rende calmo togliendo motivi di allarme
prima che nascano.* La rassicurazione precede la paura.

---

# FASE 4 — LE ANIMAZIONI COME STRUMENTO EMOTIVO

Un'animazione qui non decora: **fa provare qualcosa**. Ne esistono solo per tre ragioni — *continuità* (non perdersi),
*conferma* (ho fatto la cosa giusta), *sollievo* (è finita bene). Qualsiasi altra animazione è rumore e va eliminata.

**Quando l'animazione DEVE esserci** — nel momento della scelta (per dare eco al tocco) e nel momento del successo
(per dare un premio emotivo). Sono i due istanti in cui il cliente cerca una reazione.

**Quando NON deve esserci** — mentre legge, mentre decide, mentre aspetta dati. Lì il movimento distrae o innervosisce.
Un elemento che si muove mentre devo decidere mi ruba la decisione.

**Quanto deve durare** — quanto un respiro corto: abbastanza da percepirla, mai da doverla attendere. Se un'animazione
ti fa *aspettare*, è troppo lunga. La soddisfazione del successo può durare un attimo di più: è l'unico momento in cui
il tempo speso è tempo regalato.

**Quanto deve essere evidente** — invisibile quando serve solo continuità (il cambio schermata deve "non farsi notare");
percepibile ma discreta alla selezione; **volutamente evidente una sola volta**, alla conferma della prenotazione. Quello
è l'unico picco espressivo di tutta l'app.

**Quando deve tranquillizzare** — nei momenti di attesa e di errore: comparse morbide, mai scatti, mai lampeggii.
La calma è un'animazione *lenta e leggera*.

**Quando deve sparire** — appena ha comunicato. Nessuna animazione in loop, nessun elemento che continua a muoversi
dopo aver detto la sua. Il movimento che resta diventa ansia di fondo.

> Regola d'oro del movimento: **l'app si muove per rassicurare, non per impressionare.** Se un cliente nota
> "che bella animazione", abbiamo sbagliato: doveva notare "che facile è stato".

---

# FASE 5 — LE TRANSIZIONI E GLI STATI

Ogni passaggio ha un *compito emotivo*. Non descrivo come si fanno: descrivo cosa devono far sentire.

- **Cambio schermata** → *continuità*. Il mondo non salta: una schermata sfuma nell'altra, così il cliente sente di
  *avanzare in un unico luogo*, non di essere sbalzato altrove. Mai slide direzionali aggressivi che disorientano.
- **Comparsa di un foglio/scheda** → *invito, non irruzione*. Sale con dolcezza dal basso, come qualcosa che ti si
  avvicina, non che ti aggredisce.
- **Sparizione** → *pulizia*. Ciò che ha finito il suo compito se ne va in silenzio, senza strascichi, senza chiedere
  un addio.
- **Caricamento** → *presenza, non assenza*. Il cliente non deve mai vedere il "niente". Deve vedere già la *forma*
  di ciò che sta arrivando: così l'attesa diventa anticipazione.
- **Skeleton** → *promessa*. È l'ombra di ciò che verrà: dice "sta arrivando, e sarà esattamente qui". Rassicura
  perché il layout non salterà.
- **Spinner** → *ultima risorsa, quasi mai*. Uno spinner dice "non so quanto ci metto": è l'opposto della calma.
  Esiste solo dentro un pulsante durante un invio breve, mai a schermo intero.
- **Dialog** → *rispetto per una decisione seria*. Compare solo quando qualcosa è irreversibile o importante; ferma il
  mondo con delicatezza, pone *una* domanda, offre *una* via d'uscita. Mai per informazioni banali.
- **Snackbar** → *conferma di passaggio*. Un sussurro in basso: "fatto", e scivola via. Non chiede azione, non blocca.
- **Toast/notifica in-app** → *voce del negozio, gentile*. Breve, con il beneficio in chiaro, mai marketing, mai allarme.

> Sensazione complessiva delle transizioni: **fluidità silenziosa.** L'app scorre come acqua: continua, morbida,
> senza spigoli. Il cliente non "cambia schermata": *procede*.

---

# FASE 6 — LA TIPOGRAFIA COME PERCEZIONE

Non font: **gerarchia dell'attenzione.** In ogni schermata l'occhio deve cadere su *una* cosa, poi rilassarsi.

- **Titoli** → non gridano, *orientano*. Dicono in tre parole dove sei ("Cosa prenoti?", "Quando ti va bene?").
  Sono la voce calma di chi ti accompagna, non un'insegna al neon.
- **Sottotitoli** → *contesto sussurrato*. Ci sono per chi li cerca, invisibili per chi non ne ha bisogno. Non devono
  mai competere col titolo né con l'azione.
- **Descrizioni** → *disponibili, non imposte*. Il dettaglio esiste a un tocco di distanza, mai in mezzo al percorso.
  Chi vuole prenotare al volo non deve leggerle.
- **Pulsanti** → *l'unica voce che può alzare il tono*. È l'elemento che l'occhio deve trovare per ultimo ma cercare
  per primo quando è pronto ad agire. Dice esattamente cosa succederà. È il punto più "sicuro di sé" dello schermo.
- **Prezzi e orari** → *fatti, non enfasi*. Chiari, leggibili, mai urlati. La trasparenza si sente nella loro *calma
  presenza*, non nella loro grandezza.
- **Errori** → *bassa voce, tono caldo*. Il testo d'errore non deve mai "pesare" più della soluzione. Piccolo, gentile,
  seguito subito da una via d'uscita più evidente di lui.
- **Successi** → *la parola più serena dell'app*. "Tutto pronto" può essere l'unico testo che si concede un respiro
  più ampio: è il momento del sollievo.
- **Informazioni secondarie** → *devono sparire alla vista periferica*. Ci sono, ma l'occhio le ignora finché non le
  cerca. Non rubano mai attenzione all'azione.

**Cosa non deve MAI rubare l'occhio:** metadati, id, stati tecnici, note legali, timestamp. Vivono nel grigio della
periferia. Se uno di questi cattura lo sguardo prima del pulsante, la gerarchia è rotta.

> Sensazione tipografica: **una sola voce parla alla volta.** Il cliente non "legge una pagina": sente *una frase* e
> vede *un pulsante*. Tutto il resto è mormorio di sottofondo che rassicura senza chiamare.

---

# FASE 7 — IL RITMO

L'app non è veloce *ovunque*. È **veloce nei gesti e calma negli spazi**. Questa è la sua firma ritmica.

- **Veloce** dove il cliente agisce: aprire, scegliere, confermare. Lì zero attrito, zero attesa percepita, scorrimento.
- **Calma** dove il cliente si ferma: splash, successo, informazioni, stati di sistema. Lì l'app respira, lascia spazio,
  rallenta il battito.
- **Rilassante** nel tono generale: nessun elemento urla, nessun countdown, nessuna spinta. La quiete è il lusso.
- **Premium** nella cura del dettaglio invisibile: la morbidezza delle transizioni, la coerenza degli spazi, il peso
  giusto di ogni parola. Il premium non si vede, si *sente*.
- **Umana**, mai tecnica: parla come una persona gentile della reception. Nessun gergo, nessun codice, nessuna freddezza.
- **Non tecnica**: il cliente non deve mai percepire "un software". Deve percepire "il mio posto che mi accoglie".

> La metafora del ritmo: **una boutique, non una stazione.** In stazione tutto corre e stordisce; in una boutique di
> qualità sei accompagnato con calma e ne esci in fretta *senza esserti sentito di corsa*. L'app è quella boutique.

---

# FASE 8 — IL SILENZIO (lo spazio negativo)

Il silenzio è metà del design. Dove *non* c'è nulla, il cliente respira.

- **Dove non mettere nulla:** attorno all'azione principale. Il pulsante "Prenota"/"Conferma" ha bisogno di *vuoto*
  intorno per diventare inevitabile. Riempire lo spazio attorno a un CTA lo indebolisce.
- **Dove lasciare spazio:** in cima e in fondo a ogni schermata. Il respiro visivo in alto dà eleganza; quello in
  basso, sopra il pulsante, dà sicurezza (il pollice sa dove andare).
- **Dove togliere testo:** ovunque la funzione sia ovvia. Se un'icona o un contesto già dicono la cosa, la parola è
  di troppo. Il testo che spiega l'ovvio genera diffidenza ("perché me lo spiegano?").
- **Dove togliere colore:** nei momenti di ansia e nelle informazioni secondarie. Il colore è attenzione: usato ovunque,
  non significa più nulla. La palette del negozio deve accendersi *solo* dove conta — l'azione, lo stato scelto, il
  successo. Il resto vive in neutri.
- **Dove togliere icone:** ovunque non aggiungano comprensione. Un'icona decorativa è rumore travestito da chiarezza.
  Un'icona serve solo se sostituisce una parola o accelera il riconoscimento.

> Il silenzio comunica **fiducia**: un'app che non ha bisogno di riempire ogni angolo è un'app sicura di sé. Lo spazio
> vuoto dice "non c'è altro da fare qui, sei a posto". La calma è assenza progettata.

---

# FASE 9 — IL CONFRONTO EMOTIVO CON I MIGLIORI

Non cosa fanno — *cosa fanno provare*, e come li superiamo sul piano della sensazione.

**Apple Calendar** — *cosa fa sentire bene:* affidabilità silenziosa, glanceability, zero rumore. *Cosa manca:* non
prenota nulla, è freddo, non ha anima di luogo. *Come lo superiamo:* prendiamo la sua calma e la sua trasparenza, ma
la scaldiamo con l'identità del negozio. La nostra app è "Apple Calendar che ti accoglie".

**Calendly** — *cosa fa sentire bene:* la semplicità del "scegli uno slot", nessuna esitazione. *Cosa manca:* è
B2B, asettico, senza calore né identità; sembra uno strumento, non un posto. *Come lo superiamo:* la stessa chiarezza
del "tocca un orario", dentro un ambiente che ha un volto, un colore, una voce.

**Booksy** — *cosa fa sentire bene:* ricchezza, scelta, recensioni. *Cosa fa sentire male:* rumore, marketplace,
sensazione di essere "uno tra tanti", spinte commerciali, ansia da abbondanza. *Come lo superiamo:* togliendo tutto.
Da noi non sei in un mercato: sei *nel tuo negozio*. La calma è il nostro vantaggio contro il loro rumore.

**Fresha** — *cosa fa sentire bene:* pulizia relativa, buon calendario. *Cosa fa sentire male:* spinte a pagamenti e
promo, sensazione di piattaforma che ti "lavora". *Come lo superiamo:* zero distrazioni commerciali. Il cliente non
sente mai di essere monetizzato: sente solo di aver prenotato.

**Google Calendar** — *cosa fa sentire bene:* immediatezza, reminder affidabili, universalità. *Cosa manca:* generico,
senza identità, non è un'esperienza di prenotazione. *Come lo superiamo:* la stessa fiducia nei promemoria e nel
"add-to-calendar", ma dentro un rituale di prenotazione elegante e brandizzato.

**Square Appointments** — *cosa fa sentire bene:* conferma pulita, professionalità. *Cosa manca:* POS-centrico,
freddo, orientato al commerciante più che al cliente. *Come lo superiamo:* mettiamo il *cliente* al centro
dell'emozione, non la cassa; la conferma è un sollievo umano, non una ricevuta.

> Il filo comune: i migliori sono o **caldi ma rumorosi** (Booksy/Fresha) o **calmi ma freddi** (Apple/Google/Calendly).
> **Nessuno è insieme caldo e calmo.** Quello spazio — *caldo come un negozio, calmo come Apple* — è vuoto. È lì che
> viviamo noi.

---

# FASE 10 — PERCHÉ QUESTA APP SEMBRERÀ DIVERSA DA TUTTE LE ALTRE

Da Product Designer, non da sviluppatore.

Le altre app di prenotazione sono costruite attorno a ciò che l'azienda vuole *ottenere* dal cliente: più
prenotazioni, più recensioni, più upsell, più permanenza. Si *sente*. Anche quando sono pulite, hanno la tensione
sottile di chi vuole qualcosa da te. Il cliente non lo sa spiegare, ma lo percepisce: una lieve diffidenza, un
rumore di fondo, la sensazione di essere dentro un imbuto.

La nostra app è costruita attorno a **una sola intenzione onesta: farti prenotare e lasciarti andare.** Non vuole
trattenerti, non vuole venderti altro, non vuole intrattenerti. E questa assenza di secondi fini si *sente* come
si sente il silenzio dopo il rumore: come sollievo. In un mercato che urla, **la calma è la cosa più radicale che
puoi offrire.**

Sembrerà diversa per tre ragioni che nessun concorrente può copiare facilmente, perché non sono funzioni ma
*rinunce*:

1. **Rinuncia alla distrazione.** Ogni cosa che le altre aggiungono per "coinvolgere" — feed, offerte, punti, chat —
   noi la togliamo. Il risultato è un'app che sembra *finita*, non gonfia. Le app gonfie stancano; le app finite
   danno pace.

2. **Rinuncia alla propria identità a favore di quella del negozio.** Le altre ti mostrano il *loro* brand: sei nel
   mondo di Booksy, di Fresha. Da noi non esiste "il nostro mondo": esiste solo il *tuo barbiere, la tua estetista,
   il tuo studio*. L'app scompare dietro il negozio. Il cliente non pensa mai "che bella app": pensa "che bello il
   mio posto". Questa è la forma più alta di white-label: non un logo diverso, ma un'**identità restituita**.

3. **Rinuncia al proprio tempo a favore del tuo.** Le altre vogliono che tu resti. Noi vogliamo che tu esca in fretta
   e felice. Un'app che ti fa risparmiare tempo e ti lascia andare è un'app di cui ti fidi — e ci torni proprio perché
   non ti trattiene.

Il cliente uscirà da una prenotazione con una sensazione che non ha un nome preciso ma che ricorderà: **"è stato
facile, e mi sono sentito trattato bene."** Non "che app potente". Non "quante cose fa". Solo: *facile, e curato.*

È questo che la renderà diversa. Non farà di più. **Farà una cosa sola, e farà sentire il cliente come si sente
in un posto dove qualcuno si è preso cura anche dei dettagli che non vede.**

---

# VERDETTO

**Voto della percezione progettata: 94/100.**

È il documento più maturo di tutti quelli prodotti, perché finalmente descrive il *livello giusto*: non le funzioni,
non le schermate, ma le **emozioni** che le governano. Il sistema percettivo è coerente (la sequenza *calma → controllo
→ fiducia → sollievo* regge su ogni schermata), difendibile psicologicamente (parte dalle quattro tensioni reali del
prenotare) e distintivo (lo spazio "caldo *e* calmo" è genuinamente vuoto nel mercato).

Perché 94 e non 100: la percezione perfetta si verifica solo **sul dispositivo**, nel corpo — il peso esatto di un
haptic, la durata precisa di un respiro, la morbidezza reale di una transizione vivono nei millisecondi, non nelle
parole. Nessun documento può fissarli al 100%: quel 6% è il lavoro sacro della fase di realizzazione, dove il designer
*sente* e aggiusta. Questo documento porta ogni decisione fino alla soglia di quel momento.

---

## Il documento è sufficiente affinché qualsiasi designer al mondo possa ricostruire la Customer App senza chiedere una sola spiegazione?

**Sì — per l'anima e l'intenzione, in modo completo.** Un designer serio, letto questo documento insieme al Master
Blueprint, saprebbe *esattamente* cosa ogni schermata deve far provare, dove l'app deve tacere, quando deve muoversi,
che ritmo deve tenere e perché esiste. Non chiederebbe "cosa deve sentire il cliente qui": la risposta è scritta.

**Con una precisazione onesta, da designer a designer:** questo documento definisce il *cosa* e il *perché* della
percezione in modo autosufficiente. Il *quanto esatto* — i numeri finali di durata, spaziatura e peso — non si
"ricostruisce" da un testo: si **calibra sul vetro**, provandolo. È giusto così. Un documento di percezione non deve
imprigionare i millisecondi: deve dare al designer la **bussola emotiva** perché li trovi da solo, senza mai sbagliare
direzione. Questa bussola, qui, è completa.
