# CUSTOMIZATION RISK MATRIX (FASE 5)

> Ogni personalizzazione classificata come **SAFE**, **ADVANCED** o **DEVELOPER ONLY**, con motivazione.
> Serve a decidere **chi** può toccare cosa e con **quali guardrail**. Complementare alla Smart Build Matrix
> (runtime vs build) già presente nel codice (`BuildImpactMatrix`).

---

## Definizione dei livelli

| Livello | Chi | Caratteristiche | Guardrail richiesto |
|---------|-----|-----------------|---------------------|
| 🟢 **SAFE** | Tenant (self-service) | Runtime, reversibile, **non può rompere né rendere illeggibile** l'app | Validazione automatica + anteprima |
| 🟡 **ADVANCED** | Tenant guidato / Founder | Runtime o asset non-nativo; **può degradare coerenza/UX** se usato male | Anteprima obbligatoria + avviso + possibilità di reset al template |
| 🔴 **DEVELOPER ONLY** | Team piattaforma | Tocca **binario, identità store, pipeline, font/asset impacchettati** | Richiede build + review + test |

---

## Matrice per personalizzazione

### Layer 0 — Identità di prodotto
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Nome App | 🟢 SAFE | Testo runtime (in-app). *Nota:* il nome **store** è 🔴 (metadati di pubblicazione). |
| Nome breve | 🟢 SAFE | Solo etichetta, nessun impatto strutturale. |
| Icona app (launcher) | 🔴 DEVELOPER ONLY | Asset **nativo**: entra solo con build + ri-firma. |
| Splash | 🔴 DEVELOPER ONLY | Asset nativo di avvio (build). |
| Favicon | 🔴 DEVELOPER ONLY | Asset di build web. |
| Bundle ID / Package | 🔴 DEVELOPER ONLY | **Immutabile**, identità store; errore = app irriconoscibile/duplicata. |
| Dominio custom | 🔴 DEVELOPER ONLY | DNS/deep-link/certificati: fuori dal runtime, richiede provisioning. |

### Layer 1 — Colore
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Primario / Secondario | 🟢 SAFE | Runtime + **gate contrasto WCAG** già presente: non può produrre UI illeggibile. |
| Accent | 🟢 SAFE | Ruolo tertiary, nessun rischio leggibilità critico. |
| Success/Warning/Danger | 🟢 SAFE | Semantici; on-color calcolato automaticamente. |
| Background / Surface | 🟡 ADVANCED | Se scelti male riducono contrasto col testo: serve anteprima (il gate copre primary/surface, non ogni combinazione). |
| Divider / Shadow | 🟡 ADVANCED | Impatto estetico sottile; errori peggiorano il ritmo visivo. |
| Dark palette (override manuale) | 🟡 ADVANCED | La derivazione automatica è SAFE; l'**override manuale** può rompere il contrasto dark → anteprima + validazione. |

### Layer 2 — Tipografia
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Scala tipografica | 🟢 SAFE | Range limitato (0.8-1.4), non rompe layout. |
| Scelta font **da whitelist impacchettata** | 🟡 ADVANCED | Cambio forte di personalità; solo famiglie già nel binario → coerente ma impattante: anteprima. |
| Aggiunta di un **nuovo font** alla whitelist | 🔴 DEVELOPER ONLY | Richiede impacchettare il `.ttf` (licenza + build + peso binario). |

### Layer 3 — Forma & Superficie
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Border radius (preset) | 🟢 SAFE | Range guardrailed. |
| Elevation / shadow level | 🟢 SAFE | 0-4, nessun rischio. |
| Densità | 🟢 SAFE | 3 opzioni, tap target sempre validi. |
| Stile bottoni/card | 🟡 ADVANCED | Varianti che, combinate, possono rompere coerenza: preferibile guidato dal template. |

### Layer 4 — Movimento
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Animation level (none/subtle/expressive) | 🟢 SAFE | Preset chiusi, nessun impatto funzionale. |
| Transizioni di pagina (preset) | 🟡 ADVANCED | Scelte estreme possono disorientare; preset consigliato dal template. |
| Stile loader | 🟢 SAFE | Preset chiusi. |

### Layer 5 — Immagini & Illustrazioni
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Hero image (upload/URL) | 🟢 SAFE | Runtime; overlay di leggibilità applicato automaticamente. |
| Gallery / Background | 🟡 ADVANCED | Immagini pesanti/di bassa qualità degradano performance ed estetica: linee guida + compressione. |
| Empty-state / onboarding illustration | 🟡 ADVANCED | Se non coerenti col template stonano; meglio set curati per template. |
| Logo varianti (bianco/nero/orizzontale) | 🟡 ADVANCED | Il tenant deve caricare le versioni giuste; fallback automatico se assenti. |
| Icona app / splash da immagine | 🔴 DEVELOPER ONLY | Asset nativi (build). |

### Layer 6 — Iconografia
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Icon set/stile (da template) | 🟡 ADVANCED | Cambio coerente solo se il set è impacchettato; non arbitrario. |
| Upload icone custom | 🔴 DEVELOPER ONLY | Asset da impacchettare/validare. |

### Layer 7 — Voce & Terminologia
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Microcopy (welcome/CTA/empty) | 🟢 SAFE | Testo runtime, lunghezze validate. |
| Terminology pack (scelta verticale) | 🟢 SAFE | Selezione di un pacchetto curato → sempre coerente. |
| Terminology **custom parola-per-parola** | 🟡 ADVANCED | Rischio incoerenza/typo; anteprima consigliata. |
| Nuova lingua | 🔴 DEVELOPER ONLY | Richiede stringhe tradotte + delegate di localizzazione (build). |

### Layer 8 — Contatti, Legale, Dominio
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Telefono/Email/WhatsApp/Social/Maps | 🟢 SAFE | Dati runtime, validati (formato/URL https). |
| Privacy/Termini/Cookie/Supporto | 🟢 SAFE | URL https validati; nascosti se vuoti. |
| Copyright / Powered-by toggle | 🟡 ADVANCED | Impatta la percezione di proprietà; scelta commerciale (piano). |

### Meta / Struttura
| Voce | Livello | Motivazione |
|------|---------|-------------|
| Scelta template | 🟢 SAFE | Preset curato: cambia tutto in modo coerente, reversibile, runtime (font/asset se già nel binario). |
| Ordine/visibilità sezioni | 🟡 ADVANCED | Può nascondere contenuti utili; guardrail su sezioni obbligatorie. |
| Creazione di un **nuovo template** | 🔴 DEVELOPER ONLY | Definisce asset pack + struttura: lavoro di piattaforma. |
| Regole prenotazione | 🟢 SAFE (fuori branding) | Runtime, validate; incluse per completezza. |

---

## Principi operativi che ne derivano

1. **Il 70% delle personalizzazioni deve essere SAFE** → il tenant fa da solo, senza rischi (colori, copy, contatti, hero, template, densità, dark).
2. **ADVANCED = sempre con anteprima e "reset al template"** → l'utente non resta mai "incastrato" in una configurazione brutta.
3. **DEVELOPER ONLY = tutto ciò che è binario/nativo/store** → font nuovi, icone native, bundle id, dominio, lingue, nuovi template. Coincide con la colonna "build" della Smart Build Matrix.
4. **Guardrail > libertà**: la superiorità enterprise nasce dal fatto che *è impossibile fare un'app brutta*. La libertà totale (developer-only) resta al team piattaforma.
