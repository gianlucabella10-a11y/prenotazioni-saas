# SCREEN_ANALYSIS — Analisi degli screenshot di riferimento

**Fonte**: 17 screenshot (IMG_9740-9756.HEIC) dell'app di riferimento funzionale "Giuffrida Barber" (iOS, v. 4.21.0, 3 sedi a Catania), catturati il 12/06/2026. Prima analisi: questi file non erano mai stati esaminati nelle fasi precedenti.

**Nota metodologica**: l'analisi mappa esclusivamente le **funzionalità**; grafica, marchio, testi e foto sono proprietà di terzi e non vengono replicati (vincolo di Fase 1, [docs/00-indice.md](docs/00-indice.md)).

---

## 1. Inventario schermate

| # | File | Schermata | Note |
|---|---|---|---|
| S1 | IMG_9740, 9744 | **Splash** — logo completo | Due scatti identici |
| S2 | IMG_9742, 9743 | **Splash** — variante solo logotipo | La splash è animata in 2 stadi (prima il nome, poi il sottotitolo) |
| S3 | IMG_9741 | **Selezione sede** | Messaggio di benvenuto + 3 card sede (foto ambiente, nome, indirizzo completo) |
| S4 | IMG_9745, 9746 | **Informazioni negozio** | Stessa schermata con 2 foto header diverse → carosello/galleria foto. Contiene: barra contatti (6 icone), indirizzo con link navigatore, staff, orari, listino |
| S5 | IMG_9747, 9752 | **Nuova prenotazione — step servizi** | Griglia servizi a icone, selezione multipla, stati disabilitati, info (ⓘ) per servizio, FAB avanti. In 9752 un servizio è selezionato (evidenziato) e gli altri appaiono disabilitati |
| S6 | IMG_9750 | **Nuova prenotazione — step data/operatore/ora** (con disponibilità) | Strip calendario orizzontale, chip servizio modificabile (matita), scelta barbiere con foto, slot raggruppati Mattina/Pomeriggio su timeline, campo Note, FAB conferma |
| S7 | IMG_9755 | Variante S6 con pochi slot | 1 slot mattina, 3 pomeriggio |
| S8 | IMG_9756 | Variante S6 — staff scrollato | Altri 3 barbieri visibili → 6 operatori totali, lista orizzontale scrollabile |
| S9 | IMG_9753, 9754 | **Nuova prenotazione — nessuna disponibilità** | "Non ci sono orari disponibili. Se lo desideri puoi inviare una richiesta di prenotazione." + bottone **INVIA RICHIESTA** + Note |
| S10 | IMG_9749 | **Prenotazioni — lista vuota** | Empty state + icona "storico" in alto a destra (toggle passate/future) |
| S11 | IMG_9751 | **Prenotazioni — lista con elemento** | Card: mese/giorno/ora a sinistra, barbiere con foto, chip servizio, **X** per cancellare |
| S12 | IMG_9748 | **Profilo utente** | Avatar, 6 azioni: Modifica profilo, Avvisi, Privacy policy, Termini e condizioni, **Cancella profilo**, Esci. Versione app in calce |

## 2. Funzionalità individuate per schermata

### S1-S2 — Splash brandizzata
- Splash a tema del tenant, animata in due stadi (logotipo → lockup completo)

### S3 — Selezione sede (multi-location)
- Selettore sede al primo accesso con card visuali: **foto della sede**, nome, indirizzo completo
- Tre sedi dello stesso brand → multi-location nativa nel flusso cliente

### S4 — Informazioni negozio (per sede)
- **Galleria foto** del negozio nell'header (sfogliabile)
- **Barra contatti rapidi**: avvisi (campanella), telefono (chiamata), sito web, Facebook, Instagram, WhatsApp
- Indirizzo con azione **"Apri sul navigatore"** (deep link mappe)
- **Staff della sede** con foto e nome (card orizzontali)
- **Orari di apertura settimanali** con doppia fascia (mattina/pomeriggio), giorni di chiusura espliciti ("CHIUSO") e **giorno corrente evidenziato**
- **Listino servizi** con durata indicativa e prezzo

### S5 — Selezione servizi
- Griglia con **icona per servizio**
- **Selezione multipla** ("Seleziona uno o più servizi") → prenotazione multi-servizio nella stessa visita
- **Dettaglio servizio** via ⓘ (descrizione/durata/prezzo)
- **Stati disabilitati**: dopo una selezione, i servizi non combinabili (o non offerti dallo staff compatibile) diventano non selezionabili
- Un servizio appare disabilitato già allo stato iniziale → servizi visibili ma momentaneamente non prenotabili

### S6-S8 — Data, operatore, orario
- **Strip calendario orizzontale** con navigazione per mese (freccia →); giorni non disponibili attenuati (Dom/Lun chiusi)
- Chip del **servizio selezionato sempre visibile e modificabile** (icona matita = torna allo step servizi)
- **Scelta operatore** con foto, lista orizzontale scrollabile (6 operatori), selezione evidenziata
- **Slot raggruppati per fascia** (Mattina / Pomeriggio) su timeline verticale
- **Campo Note libero** del cliente verso il negozio
- Gli slot cambiano al cambio operatore/giorno (ricalcolo dinamico)

### S9 — Richiesta manuale senza disponibilità
- Quando il giorno non ha slot: invito a inviare una **richiesta di prenotazione** libera (senza orario), con note, che lo staff gestirà manualmente
- È un flusso distinto sia dalla prenotazione confermata sia da una waitlist automatica

### S10-S11 — Le mie prenotazioni
- Lista prenotazioni future con: data (mese/giorno/ora), operatore con foto, servizi prenotati
- **Cancellazione diretta** dalla card (X)
- **Toggle storico** (icona orologio) per le prenotazioni passate

### S12 — Profilo
- Modifica profilo (anagrafica, avatar)
- **Avvisi** — centro notifiche in-app
- Privacy policy e Termini e condizioni (documenti legali del tenant)
- **Cancella profilo** — cancellazione account self-service (obbligo App Store, GDPR)
- Esci (logout)
- Versione app visibile

## 3. Pattern UX trasversali osservati

1. Flusso di prenotazione in **2 step** (servizi → giorno+operatore+ora) con riepilogo permanente, non un wizard a 4-5 passi: meno attrito
2. **Fasce orarie** (Mattina/Pomeriggio) invece di una lista piatta di orari
3. La **richiesta manuale** come fallback di vendita quando l'agenda è piena: il negozio non perde il contatto
4. Foto ovunque (sedi, staff): la fiducia visiva è parte del prodotto per questo target
5. Doppia fascia oraria giornaliera negli orari di apertura (pausa pranzo) — già supportata dal nostro modello a regole multiple per giorno

## 4. Confronto con i nostri user flow (docs/07-08)

| Flusso osservato | Nostro flusso | Esito |
|---|---|---|
| Selezione sede al primo avvio | Flusso 8 (onboarding cliente, "selezione sede se multi-sede") | ✅ Allineato |
| Servizi multipli → operatore → slot | Flusso 3 (calcolo disponibilità e prenotazione) | ✅ Allineato (ordine: noi servizio→operatore→slot, loro identico) |
| Richiesta manuale senza slot | **Non previsto**: il nostro Flusso 3 termina con "nessuno slot"; la waitlist (docs/30 §7) è un meccanismo diverso (notifica quando si libera) | ❌ Gap di flusso |
| Cancellazione da lista | Flusso 4 | ✅ Allineato |
| Centro avvisi in-app | Non previsto nei flussi (solo push/email) | ❌ Gap |
| Cancella profilo self-service | Previsto in docs/25 (`POST /me/gdpr/erasure`) e review #11 | ⚠️ Progettato, non implementato |

Il dettaglio funzionalità-per-funzionalità contro backend e API implementati è in [GIUFFRIDA_FEATURE_GAP.md](GIUFFRIDA_FEATURE_GAP.md).
