# WHITE LABEL CUSTOMIZATION ARCHITECTURE

> **Progettazione, non implementazione.** Come dovrebbe essere il sistema di branding completo perché ogni app
> sembri disegnata su misura, **mantenendo un solo motore** e **restando nel dominio prenotazioni**.
> Riferimento allo stato reale: [CURRENT_CUSTOMIZATION_STATE.md](CURRENT_CUSTOMIZATION_STATE.md).
> Rischio per voce: [CUSTOMIZATION_RISK_MATRIX.md](CUSTOMIZATION_RISK_MATRIX.md). Ordine: [CUSTOMIZATION_ROADMAP.md](CUSTOMIZATION_ROADMAP.md).

---

## Principio guida

Un'app sembra "una copia con logo diverso" quando cambiano **solo colore e logo**. Sembra "fatta apposta" quando
cambiano **7 assi percepiti**: **colore, tipografia, forma, movimento, immagini, iconografia, linguaggio**.
Oggi il motore copre bene **colore/forma** e in parte **linguaggio**; il salto enterprise è su **tipografia,
immagini, movimento, iconografia**.

Regola architetturale: **ogni personalizzazione è un DATO** (token nel `theme`/`content`/manifest), **mai un branch
di codice** (`if cliente == X`). Un solo binario Flutter, guidato da `GET /app/config` + asset di build.
Legenda priorità: **P0** = fondante per il "feel bespoke"; **P1** = forte impatto; **P2** = rifinitura; **P3** = nice-to-have.
Stato: 🟢 presente · 🟡 parziale · 🔴 assente (vedi FASE 0).

---

## FASE 1 — SISTEMA DI BRANDING COMPLETO (per layer)

### Layer 0 — Identità di prodotto (store / sistema operativo)

| Voce | Descrizione | Scopo | Dove viene usata | Priorità | Stato |
|------|-------------|-------|------------------|----------|-------|
| Nome App | Nome completo del prodotto | Identità primaria | Store, launcher, header, manifest | P0 | 🟢 |
| Nome breve | Etichetta launcher corta (≤12) | Icona home leggibile | Launcher iOS/Android | P1 | 🔴 |
| Icona app | Icona nativa (launcher) | Riconoscibilità sistema | Home OS, store | P0 | 🟢 (da logo) |
| Splash | Schermata d'avvio | Prima impressione | Avvio app | P0 | 🟢 (da logo) |
| Favicon | Icona web/PWA | Build web | Browser/PWA | P2 | 🟢 |
| Bundle ID / Package | Identità store immutabile | Pubblicazione | Store, firma | P0 | 🟢 (build) |
| Dominio custom | Dominio del tenant per link/PWA | Percezione "sito mio" | Deep-link, PWA, email | P2 | 🔴 (schema only) |

### Layer 1 — Colore (palette semantica, light + dark)

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Primario | Colore brand dominante | Pulsanti, app bar, CTA | Tutta l'app | P0 | 🟢 |
| Secondario | Colore di supporto | Elementi secondari | Chip, badge | P0 | 🟢 |
| Accent | Richiamo/tertiary | Highlight, link | Elementi di enfasi | P1 | 🟢 |
| Success / Warning / Danger | Stati semantici | Feedback (conferma/errore) | Banner, badge stato | P1 | 🟢 |
| Background | Sfondo scaffold | Base visiva | Ogni schermata | P1 | 🟢 |
| Card / Surface | Superficie contenitori | Gerarchia | Card, sheet | P1 | 🟢 |
| Divider | Colore separatori | Ritmo/leggibilità | Liste, sezioni | P2 | 🟡 |
| Shadow (colore/intensità) | Ombra come token | Profondità di brand | Card, FAB | P2 | 🟡 |
| Dark theme | Palette scura completa | Comfort/premium | Modalità scura | P0 | 🟢 |
| Contrast guardrail | Validazione WCAG | Leggibilità garantita | Gate salvataggio | P0 | 🟢 |

### Layer 2 — Tipografia (la personalità) — **il moltiplicatore #1**

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Famiglia display | Font dei titoli | **Carattere del brand** | Titoli, hero, header | **P0** | 🔴 (switch senza .ttf) |
| Famiglia body | Font del testo | Leggibilità/tono | Corpo, liste | **P0** | 🔴 |
| Pesi disponibili | Regular/Medium/Bold | Gerarchia tipografica | Ovunque | P1 | 🔴 |
| Scala tipografica | Fattore dimensioni | Densità informativa | Ovunque | P1 | 🟢 |
| Font pack per template | Set curato (display+body) impacchettato | Identità coerente | Bundle build | **P0** | 🔴 |

> **Verità critica:** finché i `.ttf` non sono impacchettati, *ogni tenant ha lo stesso font di sistema*. La tipografia
> è ~50% della percezione di "brand": questo è il singolo intervento a più alto ritorno. Vincolo licenze: usare
> famiglie open (Inter, Poppins, Oswald, Playfair, Lora, Manrope, Montserrat) impacchettate nel binario per template.

### Layer 3 — Forma & Superficie

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Border radius | Raggi small/medium/large | Tono (soft vs squadrato) | Card, bottoni, input | P1 | 🟢 |
| Elevation / shadow level | Livello ombra | Profondità/materialità | Card, app bar | P1 | 🟢 |
| Densità | Comfortable/standard/compact | Ritmo dell'app | Liste, tap target | P1 | 🟢 |
| Stile bottoni | Filled/outline/soft | Personalità CTA | Bottoni | P2 | 🟡 |
| Card style | Bordo/ombra/flat | Coerenza superfici | Card | P2 | 🟡 |

### Layer 4 — Movimento (motion identity)

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Animation level | none/subtle/expressive | Tono (sobrio vs vivace) | Transizioni globali | P1 | 🔴 |
| Transizioni di pagina | Fade/slide/shared-axis | Continuità percepita | Navigazione | P2 | 🔴 |
| Stile loader | Spinner/branded/skeleton | Momenti d'attesa "on brand" | Caricamenti | P2 | 🔴 |
| Feedback tattile | Ripple/scale on-press | Qualità percepita | Interazioni | P3 | 🔴 |

### Layer 5 — Immagini & Illustrazioni — **il moltiplicatore #2**

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Hero image | Copertina home | "È il MIO posto" | Home | **P0** | 🟡 (solo URL) |
| Gallery | Foto attività/lavori | Prova sociale/estetica | Scheda attività | P1 | 🔴 |
| Background image | Sfondo brandizzato | Atmosfera | Home/login | P2 | 🔴 |
| Empty-state illustration | Illustrazione stati vuoti | Calore vs testo nudo | Liste vuote | P1 | 🔴 (testo) |
| Onboarding imagery | Immagini di benvenuto | Prima impressione | Onboarding | P1 | 🔴 |
| Placeholder brandizzati | Avatar/logo fallback | Coerenza sempre | Mancanza immagini | P2 | 🔴 (iniziali) |
| Logo varianti | Bianco/nero/orizzontale/quadrato/mono | Uso corretto su ogni sfondo | App bar, splash, dark, notifiche | P1 | 🔴 (1 logo) |

### Layer 6 — Iconografia

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Icon set/stile | Outline/filled/rounded coerente col template | Coesione visiva | Ovunque | P2 | 🔴 (Material) |
| Icone servizio | Glifi per categoria servizio | Riconoscibilità | Catalogo | P3 | 🔴 |

### Layer 7 — Voce & Terminologia — **il moltiplicatore #3**

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Messaggio benvenuto | Saluto home | Tono personale | Home | P1 | 🟢 |
| Titolo/descrizione home | Copy editoriale | Contesto | Home | P1 | 🟢 |
| Testo CTA | Etichetta pulsante prenota | Linguaggio del settore | Home/booking | P1 | 🟢 |
| Empty/placeholder/error copy | Microcopy stati | Cura percepita | Stati vuoti/errore | P1 | 🟡 |
| **Terminology pack per verticale** | "Prenota il taglio" / "la visita" / "la seduta" | Sembra scritta per QUEL settore | Ovunque compaia il termine | **P0** | 🔴 |
| Tono (formale/informale) | Registro linguistico | Coerenza voce | Copy globale | P2 | 🔴 |
| Lingua/i | it/en (+ future) | Mercato | Ovunque | P2 | 🟡 (it/en) |

### Layer 8 — Contatti, Legale, Dominio

| Voce | Descrizione | Scopo | Dove | Priorità | Stato |
|------|-------------|-------|------|----------|-------|
| Telefono/Email/WhatsApp | Contatti diretti | Conversione | Scheda attività | P1 | 🟢 |
| Instagram/Facebook/TikTok | Social | Fiducia/estetica | Scheda attività | P2 | 🟢 |
| Website / Maps / Indirizzo / Coordinate | Presenza fisica | Trovabilità | Scheda/Maps | P2 | 🟢 |
| Privacy / Termini / Cookie / Supporto | Link legali | Compliance store | Profilo/legale | P1 | 🟢 |
| Copyright / "Powered by" | Firma/whitelabel | Percezione proprietà | Footer | P2 | 🟡 |
| P.IVA | Anagrafica | Footer legale | Scheda attività | P3 | 🟢 |

---

## FASE 2 — ESPERIENZA DEL CLIENTE FINALE

Obiettivo: il cliente scarica e pensa **"questa è l'app del mio barbiere"**, non "una copia".
I leve reali, in ordine di impatto percepito:

1. **Prima schermata (0-3 secondi)** — splash + hero + font + palette devono dire *subito* di chi è.
   Oggi: splash con logo su colore + hero opzionale. Manca: **font del brand** e **immagine reale del locale**.
2. **Tipografia** — un titolo in Playfair (elegante) vs Oswald (barber) vs Inter (medicale) cambia tutto.
   È percepita anche da chi non "vede" il design. **Gap critico attuale.**
3. **Immagini reali** — foto del locale/lavori nella hero e nella scheda: nulla comunica "è mio" come una foto vera.
4. **Terminologia** — il barbiere dice "Prenota il taglio", il dentista "Prenota la visita", il coach "Prenota la sessione".
   Stesso motore, **parole del settore**. Oggi default generico "Prenota ora".
5. **Microcopy e stati vuoti** — "Ancora nessun taglio in agenda" con illustrazione ≫ "Nessun appuntamento" testo nudo.
6. **Onboarding** — 2-3 schede di benvenuto con immagini e voce del brand alla prima apertura.
7. **Movimento** — un'app "luxury" ha transizioni lente e morbide; una "fitness" rapide ed energiche.
8. **Coerenza icone** — un set icone coerente col template evita il "sapore Material" identico ovunque.

**Terminologia/microcopy per verticale (esempi da pacchettizzare):**

| Concetto | Barber | Beauty/Spa | Medico/Dentista | Coach/PT | Consulente/Legale |
|----------|--------|-----------|------------------|----------|-------------------|
| CTA prenota | "Prenota il taglio" | "Prenota il trattamento" | "Prenota la visita" | "Prenota la sessione" | "Prenota la consulenza" |
| Operatore | "Barbiere" | "Operatrice" | "Dottore/Specialista" | "Coach/Trainer" | "Professionista" |
| Servizio | "Servizio" | "Trattamento" | "Prestazione" | "Sessione" | "Consulenza" |
| Empty agenda | "Nessun taglio in agenda" | "Nessun trattamento prenotato" | "Nessuna visita in programma" | "Nessuna sessione fissata" | "Nessun appuntamento" |

---

## FASE 3 — CATALOGO TEMPLATE (skin coerenti su un solo motore)

Un **template** è un **preset che imposta coerentemente tutti i layer** (palette + tipo + forma + motion + immagini +
voce + struttura). Convive con lo stesso motore perché è **configurazione**, non codice. Oggi esistono 5 skin (colore+
font_style+layout); qui il target completo. Ognuno definisce: *palette · display/body font · forma · motion · imagery ·
voce · struttura home*.

| Template | Personalità | Palette | Tipografia | Motion | Immagini | Verticali target |
|----------|-------------|---------|-----------|--------|----------|------------------|
| **Minimal** | Essenziale, arioso | Neutri + 1 accent | Inter / Manrope | subtle | poche, ampio bianco | generico, consulenza |
| **Luxury** | Premium, scuro, oro | Dark + oro/rame | Playfair + Lora | expressive lento | full-bleed, foto ricche | barber premium, spa, hotel |
| **Modern** | Contemporaneo, vivace | Colori saturi | Poppins / Montserrat | expressive | grafiche geometriche | beauty, fitness |
| **Elegant** | Raffinato, morbido | Toni tenui | Cormorant + Inter | subtle | foto soft, pastel | beauty, spa, hair |
| **Professional** | Sobrio, affidabile | Blu/grigi | Inter / IBM Plex | none/subtle | icone > foto | medico, legale, commercialista |
| **Barber** | Maschile, dark, bold | Nero + oro/rosso | Oswald + Roboto Condensed | subtle deciso | foto b/n, texture | barbershop |
| **Beauty** | Delicato, visuale | Rosa/viola | Poppins + Nunito | expressive | gallery centrale | estetista, nail, make-up |
| **Restaurant** | Caldo, appetitoso | Terracotta/bordeaux | Playfair + Lato | subtle | food photography | ristoranti (prenota tavolo) |
| **Medical** | Pulito, rassicurante | Teal/verde/bianco | Inter | none | icone, pochissime foto | studio medico, poliambulatorio |
| **Dental** | Clinico, fresco | Azzurro/bianco | Inter / Nunito | none | icone sorriso | dentista, ortodonzia |
| **Fitness** | Energico, dinamico | Nero + neon | Montserrat Bold | expressive rapido | foto azione | palestra, PT, crossfit |
| **Hotel** | Ospitale, sofisticato | Navy/beige/oro | Cormorant + Inter | subtle | foto ambienti | hotel, B&B, wellness |
| **Spa** | Calmo, naturale | Verde salvia/sabbia | Lora + Manrope | subtle lento | natura/acqua | spa, centri benessere |

**Regole di convivenza (un motore):**
- Il template imposta **token** (palette/forma/motion) + **riferimenti asset** (font pack, illustration pack, icon set) + **voice pack** + **struttura** (`layout_variant` + `sections`).
- Il **motore prenotazioni non cambia mai**: stessi endpoint, stesso flusso, stessa logica. Cambia solo la *presentazione*.
- Il tenant **sceglie un template** (guardrail qualità) e può fare **override mirati** (colore, hero, copy). Non "progetta" da zero.
- Nuovi template = nuove voci di config + eventuali asset pack impacchettati: **nessuna modifica al motore**.

---

## Modello dati proposto (concettuale, non implementativo)

Estendere le strutture **esistenti** (`theme`/`content` JSON + manifest), senza tabelle parallele:
- `theme` → aggiungere `typography.family_display/family_body/weights`, `motion.level`, `divider`, `shadow`.
- `content` → aggiungere `gallery[]`, `onboarding[]`, `terminology{}` (o riferimento a un `voice_pack`).
- **Asset pack** (font/illustrazioni/icone) = risorse **impacchettate per template** nel binario, referenziate per nome nel manifest (build-time).
- `template` resta la chiave che compone tutto; i pack sono selezionati dal template, con override tenant sui soli token safe.

> Coerente con [CURRENT_CUSTOMIZATION_STATE.md] e con la Smart Build Matrix: token = runtime, asset pack/font = build.
