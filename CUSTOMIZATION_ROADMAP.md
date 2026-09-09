# CUSTOMIZATION ROADMAP (FASE 6)

> Ordine di realizzazione delle personalizzazioni, dalla base attuale alla piattaforma white-label completa.
> Criterio di ordinamento: **massimo impatto sul "feel bespoke" per unità di sforzo**, rispettando le dipendenze.
> Nessuna implementazione qui: solo sequenza, motivazione, dipendenze, rischio.

---

## Principio di sequenziamento

Oggi la personalizzazione è **cromatica/testuale**. Le tre leve che *da sole* trasformano la percezione — **tipografia,
linguaggio, immagini** — vanno per prime perché costano poco (i meccanismi esistono già) e rendono moltissimo.
La struttura dinamica e il motion, più costosi, vengono dopo. La Control Room "5 sezioni + anteprima" arriva
quando c'è abbastanza da mostrare (i template completi).

```
Colore ✔ → TIPOGRAFIA → VOCE → IMMAGINI → TEMPLATE COMPLETI → CONTROL ROOM UX → MOTION/ICONE → STRUTTURA → ONBOARDING → LOGO/PLACEHOLDER → DOMINIO → ADVANCED
        (base)   ↑ i 3 moltiplicatori del "feel bespoke"
```

---

## Le fasi

### Fase 1 — Tipografia reale (font pack per template) ⭐ *sblocco #1*
- **Cosa:** impacchettare famiglie open (Inter, Poppins, Oswald, Playfair, Lora, Manrope, Montserrat…) e attivare `font_style` per davvero.
- **Perché prima:** oggi ogni tenant ha lo **stesso font di sistema**; la tipografia è ~50% della percezione di brand. Il meccanismo (`AppThemeBuilder` switch + `font_style` nel config) **è già cablato**: manca solo il bundle.
- **Dipendenze:** nessuna (solo scelta licenze + build). **Rischio:** basso. **Impatto:** altissimo.

### Fase 2 — Voice & Terminology pack per verticale ⭐ *sblocco #3*
- **Cosa:** pacchetti di microcopy per settore (barbiere/estetista/medico/coach/legale): CTA, "operatore", "servizio", stati vuoti.
- **Perché ora:** costa pochissimo (stringhe), riusa il `content` system già presente, e fa dire "è scritta per il mio settore".
- **Dipendenze:** content system (🟢). **Rischio:** basso. **Impatto:** alto.

### Fase 3 — Immagini & illustrazioni ⭐ *sblocco #2*
- **Cosa:** pipeline hero image (upload gestito), gallery, e **set di illustrazioni** (empty-state/onboarding) coerenti per template.
- **Perché ora:** nulla comunica "è mio" come una **foto vera** del locale; gli empty-state illustrati alzano la qualità percepita.
- **Dipendenze:** Storage (🟢). **Rischio:** medio (curatela asset, performance immagini). **Impatto:** alto.

### Fase 4 — Template completi (da 5 a 13)
- **Cosa:** portare i template a coprire **tutti i layer** (palette+tipo+forma+motion+immagini+voce+struttura), non solo colore+font+layout.
- **Perché ora:** solo dopo Fasi 1-3 i template hanno "ingredienti" veri (font, illustrazioni, voce) da comporre.
- **Dipendenze:** Fasi 1,2,3. **Rischio:** medio. **Impatto:** alto.

### Fase 5 — Control Room UX (5 sezioni + anteprima live)
- **Cosa:** riorganizzare in 5 sezioni preset-first con **mockup live** (vedi CONTROL_ROOM_CUSTOMIZATION_UX.md).
- **Perché ora:** quando c'è molto da personalizzare serve un'UX semplice e un'anteprima; prima non c'era abbastanza da mostrare.
- **Dipendenze:** template completi. **Rischio:** medio (frontend + preview engine). **Impatto:** alto (adozione/onboarding).

### Fase 6 — Motion & Iconografia
- **Cosa:** animation level (none/subtle/expressive), transizioni di pagina, stile loader; set icone coerente per template.
- **Perché ora:** rifinitura che distingue "luxury lento" da "fitness rapido"; percepibile ma non fondante.
- **Dipendenze:** template. **Rischio:** medio. **Impatto:** medio.

### Fase 7 — Struttura dinamica (layout engine)
- **Cosa:** composizione reale delle `sections` (oggi solo toggle hero-vs-standard) + gallery/menu/staff come blocchi ordinabili.
- **Perché ora:** più costoso (rework client), ma porta la differenziazione strutturale vera.
- **Dipendenze:** template. **Rischio:** più alto (regressioni). **Impatto:** medio-alto.

### Fase 8 — Onboarding brandizzato
- **Cosa:** 2-3 schede di benvenuto per template, con immagini e voce del brand.
- **Dipendenze:** immagini (Fase 3) + voce (Fase 2). **Rischio:** basso-medio. **Impatto:** medio.

### Fase 9 — Logo varianti & placeholder brandizzati
- **Cosa:** logo bianco/nero/orizzontale/quadrato/mono + placeholder/avatar brandizzati; uso corretto per sfondo/dark/notifiche.
- **Dipendenze:** Asset Factory (🟢). **Rischio:** basso. **Impatto:** medio.

### Fase 10 — Dominio custom & PWA brandizzata
- **Cosa:** dominio white-label per deep-link/PWA/email (schema `tenant_domains` esiste, manca la pipeline).
- **Dipendenze:** infra/DNS. **Rischio:** più alto (certificati, provisioning). **Impatto:** medio (percezione "prodotto proprio").

### Fase 11 — Advanced di piattaforma
- **Cosa:** onboarding di nuovi font (pipeline licenza+bundle), **template authoring** per-tenant, A/B test tra template, export "brand kit".
- **Dipendenze:** tutto sopra. **Rischio:** alto. **Impatto:** strategico (scalabilità dell'offerta).

---

## Vista sintetica

| Fase | Titolo | Impatto | Sforzo | Rischio | Sblocca |
|------|--------|---------|--------|---------|---------|
| 1 | Tipografia (font pack) | ★★★★★ | Basso | Basso | Personalità |
| 2 | Voice/terminology | ★★★★ | Molto basso | Basso | "Del mio settore" |
| 3 | Immagini/illustrazioni | ★★★★ | Medio | Medio | "È il mio posto" |
| 4 | Template completi (13) | ★★★★ | Medio | Medio | Coerenza totale |
| 5 | Control Room UX | ★★★★ | Medio | Medio | Adozione |
| 6 | Motion/iconografia | ★★★ | Medio | Medio | Rifinitura |
| 7 | Struttura dinamica | ★★★ | Alto | Alto | Differenziazione strutturale |
| 8 | Onboarding brandizzato | ★★★ | Medio | Basso | Prima impressione |
| 9 | Logo varianti/placeholder | ★★ | Basso | Basso | Coerenza |
| 10 | Dominio custom/PWA | ★★ | Alto | Alto | "Prodotto mio" |
| 11 | Advanced (authoring/A-B) | ★★★ | Alto | Alto | Scala dell'offerta |

---

## Traguardo "piattaforma completa"

Al termine della **Fase 5** la piattaforma è già **percepita come white-label premium** (font + voce + immagini +
13 template + Control Room con anteprima). Le Fasi 6-11 la portano a **livello enterprise/leader di categoria**.
Nessuna fase introduce funzioni fuori dal dominio prenotazioni: sono tutte **presentazione**, su un solo motore.
