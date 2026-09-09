# FOUNDER CUSTOMIZATION GUIDE

> Guida operativa per chi possiede la piattaforma: come far sembrare **ogni** app fatta su misura, **in fretta e su
> scala**, restando su un solo motore e nel dominio prenotazioni. Documento di indirizzo, non di implementazione.

---

## Il modello mentale (da tenere sempre a mente)

- **Un motore, molte facce.** Il codice non sa chi è il cliente: legge `GET /app/config` + asset di build. Nessun ramo per-cliente.
- **7 assi di percezione**: colore · **tipografia** · forma · movimento · **immagini** · iconografia · **linguaggio**. I tre in grassetto sono ciò che distingue "app su misura" da "app col logo cambiato".
- **Template-first.** Non si personalizza da zero: si sceglie un **template** (preset coerente) e si rifinisce. È così che si mantiene qualità a scala.
- **Runtime vs build.** Quasi tutto è runtime (si aggiorna alla prossima apertura). Solo icona nativa/splash/font nuovi/bundle-id richiedono una build. La piattaforma **te lo dice già** (Smart Build Matrix).

---

## Playbook: onboarding di un nuovo cliente in ~3 minuti

1. **Aspetto** → scegli il **template** più vicino al settore (Barber, Beauty, Medical, Luxury…). L'app è già credibile.
2. **Identità** → carica **logo**, imposta **nome** e **2 colori** (il gate contrasto ti protegge).
3. **Contenuti** → carica **1 foto vera** del locale (hero) e scegli il **pacchetto terminologia** del settore.
4. **Contatti & Legale** → telefono, WhatsApp, social, link privacy/termini.
5. **Anteprima & Pubblica** → guarda il mockup live, salva (runtime) e — se serve — genera la build.

> Regola d'oro: **Template + Logo + 1 foto vera + terminologia di settore** = già "la sua app". Il resto è rifinitura.

---

## Chi tocca cosa (delega intelligente)

| Livello | Chi lo fa | Esempi |
|---------|-----------|--------|
| 🟢 **SAFE** — delega al cliente | Il cliente, da solo | Colori, copy, contatti, hero, scelta template, densità, dark |
| 🟡 **ADVANCED** — fallo tu (founder) | Tu, con anteprima | Override token, gallery, override dark, ordine sezioni, motion |
| 🔴 **DEVELOPER ONLY** — team piattaforma | Solo dev | Font nuovi, icona/splash nativi, bundle-id, dominio, lingue, nuovi template |

Dettaglio in [CUSTOMIZATION_RISK_MATRIX.md](CUSTOMIZATION_RISK_MATRIX.md).

---

## I 3 non-negoziabili (la differenza tra premium e "solito template")

1. **Tipografia vera.** Non consegnare mai un'app col font di sistema. Il template deve portare font reali. *(Oggi è il gap #1: vedi roadmap Fase 1.)*
2. **Un'immagine reale.** Almeno la hero deve essere una foto del locale/lavori, non uno stock generico.
3. **Il linguaggio del settore.** "Prenota il taglio" per il barbiere, "Prenota la visita" per il medico. Mai il default generico.

Se questi tre ci sono, il cliente dice "è la mia app". Se mancano, dice "è un template".

---

## Guardrail di qualità (perché non può venire male)

- **Contrasto WCAG** validato al salvataggio → niente testo illeggibile.
- **Preset chiusi** per forma/motion/densità → niente combinazioni rotte.
- **Reset al template** sempre disponibile → nessun cliente resta "incastrato".
- **Solo font/asset dalla whitelist impacchettata** → coerenza garantita; il caos (upload liberi) resta developer-only.

---

## Posizionamento commerciale (come venderlo)

- **Racconto:** "Non ti diamo un template. Ti diamo **la tua app**: tuo nome sullo store, tua icona, tuo font, tue foto, tuo linguaggio. Stessa affidabilità di un prodotto costruito da zero, senza il costo."
- **Tiering suggerito per profondità di personalizzazione:**
  - **Base**: template + logo + colori + contatti (tutto SAFE, self-service).
  - **Pro**: + immagini/gallery + terminologia + override avanzati + dark su misura.
  - **Enterprise**: + template dedicato + font dedicato + dominio custom + onboarding brandizzato.
- **Leva competitiva:** un solo binario → onboarding di un cliente in minuti, non settimane. Nessun costo marginale di codice per cliente.

---

## Checklist "app pronta a impressionare"

- [ ] Template scelto coerente col settore
- [ ] Logo caricato (+ variante per dark, se disponibile)
- [ ] **Font del template attivo** (non quello di sistema)
- [ ] **Hero = foto reale** del cliente
- [ ] **Terminologia di settore** impostata
- [ ] 2 colori brand con contrasto validato
- [ ] Dark mode verificata nell'anteprima
- [ ] Contatti + WhatsApp + social + link legali
- [ ] Anteprima live controllata (light **e** dark)
- [ ] Build generata se sono cambiati icona/splash/font

---

## Cosa NON fare (mantenere il focus)

Restare nel dominio **prenotazioni**. Nessun e-commerce, loyalty, coupon, pagamenti, CRM, magazzino, POS, chat, AI,
marketplace. La forza della piattaforma è **fare una cosa in modo eccellente e farla sembrare unica per ciascuno**.
Aggiungere funzioni diluisce esattamente questo vantaggio.
