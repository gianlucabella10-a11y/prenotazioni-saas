# BOOKING EXPERIENCE (FASE 3 + FASE 6)

> Analisi passo-passo del flusso di prenotazione e del **budget di tempo**. Obiettivo dichiarato: **< 60 secondi**
> per un cliente medio, **< 20 secondi** per un cliente abituale. Progettazione, non implementazione.

---

## Il funnel

```
Servizio → Operatore → Data → Orario → Conferma → Promemoria → Storico
```

Nota di design: nell'app **Operatore, Data e Orario stanno su UNA schermata** (già così oggi, ed è corretto).
Tenerli separati aggiungerebbe 2 navigazioni inutili. Il funnel logico qui sotto è analizzato per completezza,
ma la resa fisica resta **2 schermate + conferma**.

---

## Passo per passo

### 1 · Scelta servizio
- **Obiettivo:** far scegliere *cosa* in un colpo d'occhio.
- **Tempo ideale:** 5–10s.
- **Azioni:** 1 tap (single-service default) → Continua. Multi-servizio possibile, non richiesto.
- **Errori da evitare:** liste lunghe senza categorie; obbligare a capire la multi-selezione; nascondere prezzo/durata (oggi mostrati ✔).

### 2 · Scelta operatore
- **Obiettivo:** minimizzare la scelta; molti clienti non hanno preferenza.
- **Tempo ideale:** 0–5s (spesso **zero**, con "Chiunque" di default).
- **Azioni:** default **"Chiunque"** (auto-assegna e massimizza gli slot); chip per scegliere una persona.
- **Errori da evitare:** obbligare a scegliere una persona (oggi si atterra sul "primo capace", ma manca "Chiunque"); mostrare operatori che non fanno quel servizio (oggi già filtrati ✔).

### 3 · Scelta data
- **Obiettivo:** arrivare al giorno giusto con il minimo scorrimento.
- **Tempo ideale:** 3–8s.
- **Azioni:** striscia orizzontale con **Oggi/Domani** evidenziati; default = oggi; "Prima disponibilità" salta al primo giorno con slot.
- **Errori da evitare:** calendario mensile pesante come default (lo strip veloce è giusto); non indicare quali giorni sono pieni.

### 4 · Scelta orario
- **Obiettivo:** un tap sullo slot.
- **Tempo ideale:** 3–8s.
- **Azioni:** slot come chip, raggruppati **Mattina/Pomeriggio** (già così ✔); evidenza dello slot scelto.
- **Errori da evitare:** griglie fitte illeggibili; slot troppo piccoli (touch target); nessun feedback sullo slot scelto (oggi c'è ✔).

### 5 · Conferma
- **Obiettivo:** confermare con certezza, senza passaggi ridondanti.
- **Tempo ideale:** 3–10s (più il capture account se ospite).
- **Azioni:** bottone unico che dice **"Conferma · [servizio] · [ora] · [prezzo]"** e prenota direttamente (nessuna schermata review separata — corretto).
- **Errori da evitare:** doppia schermata di riepilogo; chiedere l'account **prima** di qui (oggi il muro registrazione+verifica è a monte → va spostato qui); confermare senza mostrare il prezzo (oggi il bottone mostra solo l'ora → aggiungere prezzo/servizio).

### 6 · Promemoria
- **Obiettivo:** ridurre i no-show e dare fiducia.
- **Tempo ideale:** 0s (automatico).
- **Azioni:** messaggio "Ti ricorderemo 24h prima"; **Aggiungi al calendario** dallo schermo di successo; push reminder (backend già schedula).
- **Errori da evitare:** non comunicare che ci sarà un promemoria (oggi non detto); non offrire il calendario (oggi ASSENTE).

### 7 · Storico
- **Obiettivo:** ritrovare, gestire e **ri-prenotare** in un tap.
- **Tempo ideale:** 2–5s per ri-prenotare.
- **Azioni:** prossime/passate; per ciascuna **Sposta · Calendario · Indicazioni · Annulla**; "Prenota di nuovo".
- **Errori da evitare:** offrire solo l'annullamento (oggi è così); nessuna scorciatoia di re-booking.

---

## FASE 6 — Budget di tempo

### Cliente medio (registrato e verificato) — target < 60s
| Passo | Tempo | Note |
|-------|-------|------|
| Apertura → Home | 2s | splash + skeleton |
| "Prenota" | 1s | 1 tap |
| Servizio | 6s | 1 tap + Continua |
| Operatore | 0s | default "Chiunque" |
| Data | 5s | strip, spesso "oggi/domani" |
| Orario | 5s | 1 tap slot |
| Conferma | 3s | 1 tap |
| **Totale** | **~22s** | **ampiamente sotto i 60s** |

> **Verdetto:** per l'utente registrato l'obiettivo <60s è **già raggiungibile oggi** grazie ai default intelligenti.
> Gli affinamenti ("Chiunque", "Prima disponibilità", prezzo sul bottone) lo portano verso **~15–20s**.

### Cliente abituale — target < 20s
| Passo | Tempo | Note |
|-------|-------|------|
| Apertura → Home | 2s | |
| "Prenota di nuovo [ultimo]" | 1s | tutto pre-selezionato |
| Orario | 5s | 1 tap slot |
| Conferma | 3s | 1 tap |
| **Totale** | **~11s** | richiede lo shortcut "Prenota di nuovo" (oggi ASSENTE) |

### Primo cliente (oggi) — il vero problema
| Passo | Tempo | Note |
|-------|-------|------|
| Registrazione (5 campi + password ≥10 + privacy) | 60–120s | frizione alta |
| Uscire dall'app, aprire email, copiare codice 6 cifre | 30–90s | **fuori app** |
| Verifica | 10s | |
| Booking vero | ~22s | |
| **Totale** | **2–4 minuti** | **il muro d'ingresso uccide la prima conversione** |

> **Verdetto FASE 6:** il funnel di prenotazione è già veloce. **Il tempo si perde tutto PRIMA**, nel muro di
> registrazione+verifica. Spostare l'account al momento della conferma (capture minimo + OTP) è l'intervento
> che fa crollare il tempo della *prima* prenotazione da minuti a ~40 secondi.

---

## Dove si perdono secondi oggi (sintesi critica)

1. **Muro registrazione + verifica email a monte** → minuti nella prima prenotazione. *(Priorità assoluta.)*
2. **Nessun "Chiunque" / "Prima disponibilità"** → tap e scorrimenti evitabili.
3. **Nessun "Prenota di nuovo"** → l'abituale rifà tutto il funnel.
4. **Nessun prezzo sul bottone di conferma** → micro-esitazione prima del commit.
5. **Spinner invece di skeleton** → percezione di lentezza anche quando è veloce.
