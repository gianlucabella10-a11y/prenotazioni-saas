# FOUNDER_COMMAND_CENTER — La schermata principale, ripensata

> Non una dashboard tecnica — una cabina di comando. Il cruscotto già implementato (`/control-room`, sessione "FOUNDER EXPERIENCE") è la base: qui si descrive come dovrebbe apparire per essere compresa in meno di 10 secondi. Solo specifica — nessuna nuova implementazione in questa sessione oltre a quanto già fatto in Fase 3.

## Il problema del cruscotto attuale

Il cruscotto di oggi (`HomeController::index()`) è corretto nei dati ma organizzato come una **pagina di report**: alert in cima, poi 4 blocchi paritari (Clienti, Sistema, Ultimi clienti, Ultime build). Per capirlo in 10 secondi, un Founder deve comunque leggere ogni blocco — non c'è un singolo segnale che dica immediatamente "va tutto bene o no".

## La cabina di comando ideale

```
┌─────────────────────────────────────────────────────────┐
│  🟢 TUTTO REGOLARE            (o 🟡 / 🔴 con 1 riga)      │  ← risposta in <2 secondi
├─────────────────────────────────────────────────────────┤
│   47        3         2         14g fa                   │
│  clienti   build      job      ultimo                     │  ← 4 numeri, <5 secondi
│  attivi    oggi     in coda    backup                      │
├─────────────────────────────────────────────────────────┤
│  [+ Nuovo cliente]  [Backup ora]  [Ricostruisci flotta]   │  ← azioni immediate
├─────────────────────────────────────────────────────────┤
│  Dettaglio (clienti recenti, build recenti) — sotto,      │
│  consultabile solo se serve, non necessario per capire    │
│  lo stato generale                                         │
└─────────────────────────────────────────────────────────┘
```

## Il singolo indicatore in cima (il cambiamento più importante)

Un solo semaforo, calcolato dagli stessi alert già prodotti da `HomeController::buildAlerts()`:
- 🟢 **Tutto regolare** — nessun alert.
- 🟡 **Attenzione** — alert di livello `warn` presenti (es. backup non recentissimo, build fallite ieri) ma nulla di urgente.
- 🔴 **Richiede azione ora** — alert di livello `danger` presenti (job fermo, disco quasi pieno, job falliti).

Questo non è un dato nuovo da calcolare — è una sintesi di ciò che il cruscotto già calcola, mostrata per prima e più in grande di tutto il resto.

## I 4 numeri che contano davvero

Non tutti i dati del cruscotto attuale sono ugualmente importanti ogni giorno. I 4 che rispondono a "come sta andando l'azienda oggi":
1. **Clienti attivi** (non il dettaglio per stato — solo il numero che conta commercialmente)
2. **Build oggi** (attività, non storico)
3. **Job in coda** (salute del sistema, in 1 numero)
4. **Giorni dall'ultimo backup** (rischio, in 1 numero)

Tutto il resto (dettaglio per stato, elenco ultimi clienti, elenco ultime build) resta disponibile ma **sotto la piega**, non nella prima schermata — coerente col principio "capire in 10 secondi, approfondire solo se serve".

## Azioni immediate, non solo link

Le 3 azioni più frequenti (creare cliente, backup, ricostruire flotta) come pulsanti in prima vista, non come voci di menu da cercare — riducendo il tempo tra "vedo un problema" e "lo risolvo" a zero click intermedi.

## Cosa NON deve esserci nella prima vista

Log applicativi, audit trail, gestione tester, dettagli tecnici — tutto ciò che richiede lettura e interpretazione, non un colpo d'occhio, resta in pagine dedicate già esistenti (`/control-room/logs`, `/control-room/audit`, schede cliente/app). Mettere tutto in prima pagina violerebbe esattamente l'obiettivo "10 secondi".

## Perché questa non è già l'implementazione

Il cruscotto attuale (Fase 3 di questa sessione e della precedente) già fornisce ogni dato necessario — la trasformazione qui descritta è di **gerarchia visiva** (cosa viene prima, cosa viene dopo, cosa scompare sotto la piega), non di nuovi dati o nuova logica. È deliberatamente lasciata come specifica, non implementata ora, perché tocca la choice di design UI più che l'automazione operativa (il mandato esplicito di questa sessione).
