# CONTROL ROOM — "PERSONALIZZAZIONE APP" UX (FASE 4)

> Come organizzare la personalizzazione perché sia **semplicissima**: **massimo 5 sezioni**, niente schermate
> infinite, preset-first, anteprima live, safe-by-default. Progettazione, non implementazione.

---

## Filosofia UX

1. **Preset prima di tutto.** Si sceglie un **template** e l'app è già bella e coerente. Il 90% dei clienti non tocca altro.
2. **Progressive disclosure.** Le opzioni fini stanno dietro un "Personalizza in dettaglio"; non affollano la schermata.
3. **Anteprima live sempre visibile.** Ogni modifica si vede su un mockup del telefono, in tempo reale. Mai "salvare alla cieca" (oggi è il gap principale).
4. **Safe-by-default.** Solo le voci SAFE sono in prima pagina; ADVANCED dietro toggle; DEVELOPER ONLY non compaiono qui.
5. **Setup in 3 minuti.** Percorso guidato: Template → Logo/Nome → Colori → Pubblica.

---

## Le 5 sezioni (e solo queste)

### 1 · ASPETTO (Template)
Il cuore. Griglia visuale dei **13 template** (Minimal, Luxury, Barber, Beauty, Medical, …), ognuno con
thumbnail realistica. Un click applica **palette + tipografia + forma + motion + immagini + voce + struttura**.
- CTA: "Usa questo stile" → l'anteprima si aggiorna.
- Sotto: "Personalizza in dettaglio" (ADVANCED) → override token (colori extra, forma, densità, motion).
- *Obiettivo: da qui esci con un'app già credibile.*

### 2 · IDENTITÀ (Brand)
Il minimo che rende l'app "sua":
- Nome app + nome breve.
- **Logo** (upload) + varianti opzionali (bianco/orizzontale) con fallback automatico.
- **2 colori chiave** (primario, accento) con validazione contrasto e swatch suggeriti dal template.
- Modalità: Chiaro / Scuro / Automatico (dark generato in automatico).

### 3 · CONTENUTI (Voce & Immagini)
Ciò che fa dire "questa è l'app del MIO posto":
- **Immagine hero** (upload) + eventuale gallery.
- **Terminologia del settore** (dropdown: Barbiere / Estetista / Medico / Coach / …): imposta CTA, "operatore", "servizio", stati vuoti.
- Messaggio di benvenuto + titolo/descrizione home.
- (ADVANCED) Onboarding: 2-3 schede di benvenuto.

### 4 · CONTATTI & LEGALE
Dati che compaiono nella scheda attività:
- Telefono, email, WhatsApp, Instagram, Facebook, TikTok, sito, indirizzo/coordinate.
- Privacy, Termini, Cookie, Supporto.
- (ADVANCED) Dominio custom · Powered-by on/off.

### 5 · ANTEPRIMA & PUBBLICA
- **Mockup telefono live** (light/dark) sempre a fianco durante tutte le sezioni; qui a schermo intero.
- Riepilogo **"cosa si aggiorna subito vs cosa richiede una nuova build"** (Smart Build Matrix già esistente).
- Pulsante unico: **"Salva"** (runtime, immediato) e, se servono asset nativi, **"Genera build"** (con avviso chiaro).

---

## Wireframe concettuale (testuale)

```
┌───────────────────────────────────────────────────────────┐
│  PERSONALIZZAZIONE APP — [Nome Cliente]        [Anteprima ▸]│
├───────────────────────────────┬───────────────────────────┤
│  ① ASPETTO   ② IDENTITÀ        │                           │
│  ③ CONTENUTI ④ CONTATTI ⑤ PUBBL│      ┌───────────────┐    │
│                               │      │   MOCKUP LIVE  │    │
│  [ griglia template visuale ] │      │   (telefono)  │    │
│  ◉ Barber  ○ Luxury  ○ Minimal│      │  light │ dark │    │
│                               │      └───────────────┘    │
│  ▸ Personalizza in dettaglio  │   "Si aggiorna subito ✔"  │
│                               │   "Richiede build ⟳: icona"│
│  [ Salva ]        [ Genera build ]                         │
└───────────────────────────────────────────────────────────┘
```

---

## Regole di semplicità (non negoziabili)

- **Mai più di 5 tab.** Se serve una sesta cosa, va dentro "Personalizza in dettaglio" di una tab esistente.
- **Ogni campo ha un default sensato dal template**: nessun campo obbligatorio a parte logo e nome.
- **Nessun codice colore da digitare** senza swatch/preview; nessun termine tecnico ("radius", "elevation") esposto: si usano etichette umane ("Angoli", "Ombre", "Spaziatura").
- **Reset sempre possibile**: "Torna allo stile del template" ripristina in un click.
- **Un solo bottone di salvataggio per sezione**; la differenza runtime/build è comunicata, non fatta gestire all'utente.

---

## Differenza rispetto ad oggi

| Oggi | Target FASE 4 |
|------|---------------|
| Dashboard tenant a 7 categorie in un'unica pagina lunga | 5 sezioni, preset-first |
| Nessuna anteprima (si salva alla cieca) | **Anteprima live** costante |
| Colori + testi | Template completo in 1 click + override guidati |
| Termini tecnici in UI | Etichette umane |

> Nota: la personalizzazione **fine** resta nel dashboard del professionista; la Control Room del founder deve poter
> fare **onboarding rapido di un nuovo cliente** in 3 minuti scegliendo template + logo + colori + contatti.
