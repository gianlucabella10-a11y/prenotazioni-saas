# PRODUCT_DESIGN_ROADMAP — Trasformazione grafica (UX/UI premium)

> Documento guida per la fase di design. Analisi **read-only** delle schermate reali — nessun file applicativo è stato modificato.
> Data: 2026-06-14 · Fonti di verità: `FINAL_TECHNICAL_FREEZE_REPORT.md`, `FINAL_PRODUCT_READINESS_AUDIT.md`, `SCREEN_ANALYSIS.md`, codice reale di app e dashboard.
> Obiettivo: trasformare un prodotto **funzionalmente completo ma visivamente generico** in un **SaaS premium**, senza nuove feature, refactor o decisioni tecniche riaperte.

**Decisioni di indirizzo (confermate):**
1. **Logo + immagini** come leva premium → si collega l'upload `BrandAsset` (modello già presente, piccola connessione backend) e si introducono logo + foto attività/staff + icone servizi. **Ogni schermata mantiene un fallback testuale** se l'asset manca (nessun box rotto).
2. **App cliente e Dashboard in parallelo** → stesso livello di rifinitura nel P0.

## Evidenza (schermate reali lette, non da memoria)
- **App Flutter — 12 schermate** in `platform-mobile/apps/client_app/lib/features/*/presentation/`: splash, tenant_unavailable, login, register, verify_email, home, business_info, booking_services, booking_schedule (include scelta operatore inline), booking_success, my_appointments, profile.
- **Dashboard — Blade** in `platform-backend/resources/views/dashboard/`: auth (login, invite, mfa-setup, mfa-challenge), home, bookings, services (index+form), staff (index+form), availability, branding. Stile in `public/css/dashboard.css` (~5KB, design system minimale già coerente).
- **Token white-label condivisi**: `platform-backend/config/branding.php` ↔ `app_theme_builder.dart` + `white_label_config.dart`. 10 colori, 3 raggi, scala tipografica. **Contrasto WCAG AA 4.5 validato backend.**
- **Editor brand reale** (`branding/index.blade.php`): espone SOLO nome, slogan, 2 colori, 3 URL legali, contatti. **Nessun upload logo/foto, nessun font, nessun raggio.** Il modello `BrandAsset` esiste ma non è collegato.
- **Gap visivi confermati**: nessuna immagine in tutta l'app (logo = testo), staff/servizi senza foto/icone, splash = nome+spinner, loading = `CircularProgressIndicator` nudo, empty = testo grigio centrato. Calendario dashboard = tabella per giorno (non vista a blocchi). MFA = secret testuale (no QR).

---

# PARTE 1 — Audit UX App Cliente

**Stato UX attuale (sintesi app):** loop di prenotazione completo e coerente; Material 3 light-only; tematizzazione solo via colori+raggi. **Zero espressione di brand visivo.** Stati funzionali ma utilitari. Buone basi (pattern coerenti, validazione form, autofill) → rischio basso nell'elevare.

> Legenda priorità: **P0** = prima della demo · **P1** = qualità SaaS · **P2** = lusso/differenziazione.

| Schermata (file) | Stato UX | Priorità | Impatto business |
|---|---|---|---|
| Splash (`white_label/.../splash_screen.dart`) | Nome testo + spinner; no logo | **P0** | Prima impressione/fiducia |
| Tenant unavailable | Icona generica + messaggio; corretto | P2 | Reputazione in caso di sospensione |
| Login (`auth/.../login_screen.dart`) | Form pulito, no logo, no recupero password | **P0** | Accesso/fiducia |
| Registrazione | Form completo + consenso GDPR ok | P1 | Conversione registrazione |
| Verifica email | OTP 6 cifre + resend cooldown; buona | P1 | Completamento onboarding |
| Home (`home/.../home_screen.dart`) | CTA + prossimo appuntamento + link; no hero/brand | **P0** | Conversione prenotazione |
| Scheda attività (`business/.../business_info_screen.dart`) | Solo nome+sedi (no foto/orari/social/mappa) | **P0** | Fiducia/credibilità |
| Servizi (`booking/.../booking_services_screen.dart`) | Lista radio, no icone/foto | **P0** | Conversione/chiarezza |
| Scelta operatore (inline in `booking_schedule`) | Chip testuali, no foto | **P0** | Fiducia/conversione |
| Slot/Disponibilità (`booking_schedule_screen.dart`) | Day-strip + slot Mattina/Pom; manca campo Note | **P0** | Conversione (cuore del flusso) |
| Conferma (`booking_success_screen.dart`) | Recap reale + stato pending; buona | P1 | Soddisfazione/ritorno |
| Storico (`appointments/.../my_appointments_screen.dart`) | Tab In programma/Passate, annulla; no foto | P1 | Retention/gestione |
| Profilo (`profile/.../profile_screen.dart`) | Avatar generico, legali, logout, elimina account | P1 | Fiducia/compliance |

**Schermate MANCANTI (segnalate, non inventate):** Onboarding/intro valore · Selezione sede (oggi auto-sceglie la prima) · Recupero password (assente, verificato) · Impostazioni separate (oggi dentro Profilo) · Centro avvisi in-app.

**Dettaglio per schermata P0** (campi: obiettivo · info · CTA primaria · azioni secondarie · frizioni · errori UX · miglioramenti UI):

- **Splash** — Obiettivo: rassicurare che l'app è "del mio negozio". Info: nome. CTA: nessuna (auto-avanza). Frizioni: schermo quasi vuoto, spinner nudo → percezione "app generica/lenta". Miglioramenti: **logo centrale brandizzato**, sfondo brand (primary o surface), spinner sostituito da progress discreto/logo animato bi-fase; rimuovere il salto dal launch screen nativo bianco.
- **Login** — Obiettivo: accedere in fretta. Info: nome+slogan, email, password. CTA: Accedi. Secondarie: Registrati. Frizioni: **niente "Password dimenticata"** (vicolo cieco se la dimentichi); nessun logo. Miglioramenti: logo sopra il form, card centrata con elevazione soft, link recupero (placeholder UI), spaziatura 8pt, pulsante 48px.
- **Home** — Obiettivo: prenotare o vedere il prossimo appuntamento. Info: slogan, CTA "Prenota ora", card prossimo appuntamento, link prenotazioni/info. CTA: Prenota ora. Frizioni: layout piatto, nessuna immagine, gerarchia debole. Miglioramenti: **hero brandizzato** (logo + foto attività + saluto), card prossimo appuntamento con foto operatore, CTA prominente accent, quick-actions a icone.
- **Scheda attività** — Obiettivo: capire dove/chi/quando, fidarsi. Info attuale: solo nome+indirizzo+telefono. Frizioni: **manca tutto ciò che dà fiducia** (foto, orari, social, mappa, listino, staff). Miglioramenti UI: header galleria foto, barra contatti a icone, orari settimanali con giorno corrente evidenziato, mappa/deeplink, card staff con foto, listino. *(Nota: orari/social/galleria richiedono l'estensione config backend già nota — vedi P1; impostare già i layout con fallback.)*
- **Servizi** — Obiettivo: scegliere uno o più servizi. Info: nome, durata·prezzo, info variante. CTA: Continua. Frizioni: lista monotona, icona radio fredda, nessuna gerarchia di prezzo. Miglioramenti: card con **icona/foto servizio** (fallback icona categoria), selezione con accento e segno di spunta, prezzo enfatizzato, categoria come sezione.
- **Scelta operatore + Slot** — Obiettivo: scegliere con chi e quando. Info: day-strip, chip operatore, slot Mattina/Pom, CTA dinamica. CTA: "Conferma per le HH:MM". Frizioni: operatori senza volto (poca fiducia), **manca il campo Note** (gap noto), nessun fallback "richiesta senza slot". Miglioramenti UI: **avatar operatore** (foto o iniziali colorate), slot come pill premium con stato selezionato accent, header data sticky; (campo Note e richiesta-senza-slot restano gap funzionali da segnalare, non disegnare logica nuova).

---

# PARTE 2 — Audit UX Dashboard Professionista

**Stato UX attuale (dashboard):** `dashboard.css` è un design system minimale **già coerente** (sidebar brand, card, tabelle, badge, stat, responsive). Pulita e usabile, ma **piatta e "da gestionale"**: niente profondità, font di sistema, nessuna data-viz, calendario = tabella. Un titolare **la usa senza assistenza** (IA chiara), ma non comunica "prodotto premium".

| Area (file) | Problema UX | Miglioramento | Impatto cliente | Priorità |
|---|---|---|---|---|
| Login/MFA (`auth/*`) | MFA = **secret testuale** da copiare a mano (no QR) | Aggiungere QR scansionabile + secret come fallback | Setup più facile, meno supporto | P1 |
| Home/KPI (`home.blade.php`) | 3 stat + tabelle; nessuna gerarchia visiva/grafici | Card KPI con icona+trend, numeri tabellari, "oggi" come timeline | Colpo d'occhio in 3 secondi | **P0** |
| Prenotazioni (`bookings/index`) | **Tabella per giorno**, non calendario; azioni dense | Restyle righe/badge/azioni; (P1) vista calendario giorno/settimana a blocchi | Gestione giornata più chiara | **P0** restyle / P1 calendario |
| Servizi (`services/*`) | Form/tabella corretti ma grezzi | Card servizio, input premium, anteprima prezzo/durata | Percezione qualità in setup | **P0** |
| Operatori (`staff/*`) | Checkbox servizi + griglia orari fitta | **Foto operatore**, griglia orari più leggibile, chip servizi | Fiducia + coerenza con app | **P0** |
| Orari/Ferie (`availability/index`) | Form-heavy, griglia time fitta | Spaziatura, raggruppamenti, stati vuoti guidati | Meno errori di configurazione | P1 |
| Brand editor (`branding/index`) | Solo 2 colori+testo; **nessun upload logo/foto; nessuna anteprima** | **Upload logo + foto**, palette guidata, **anteprima live dell'app** | È il momento "wow" della vendita | **P0** |

**Domanda chiave — "un professionista può usarla senza chiamarti?":** Sì, l'architettura informativa è chiara e i ruoli (Titolare/Operatore) nascondono il superfluo. Manca però **onboarding guidato** (checklist primo setup) e **anteprima dell'app** durante la personalizzazione → P1 ad alto valore.

---

# PARTE 3 — White Label Design System + Guardrails

## Cosa il cliente PUÒ personalizzare (tenant)
- **Nome attività/app** (2–30) · **Slogan** (0–80)
- **Colore primario** + **Colore accento** (2 colori, con validazione contrasto)
- **Logo** *(da collegare: `BrandAsset`)* — usato in splash, login, home, scheda attività, sidebar dashboard
- **Foto attività/sede** *(nuovo)* — galleria header scheda attività
- **Foto operatori** *(nuovo)* — chip operatore + card appuntamento (fallback iniziali)
- **Icona/foto servizio** *(nuovo, opzionale)* — fallback icona categoria
- **URL legali** (privacy, termini, assistenza) · **Contatti** (sede, indirizzo, telefono)

## Cosa NON deve essere personalizzabile (platform-fixed)
Font/famiglia tipografica (solo scala) · layout e posizione elementi · forma/raggi componenti · colori semantici (success/warning/error) · colori `on_*`/surface/background (garantiti per contrasto) · spaziatura e griglia · iconografia di navigazione · componenti (bottoni/card/input) · animazioni.

**Motivazione:** evitare UX incoerente, mantenere qualità SaaS uniforme, impedire "app brutte dei clienti".

## White Label Guardrails (regole precise)
1. **Contrasto WCAG AA ≥ 4.5** validato lato backend (già attivo): combinazioni illeggibili rifiutate.
2. **Max 2 colori brand**; l'accento si usa solo per evidenziazione/selezione, mai per testo lungo.
3. **`on_primary`/`on_accent`** derivati/validati automaticamente (mai testo illeggibile).
4. **Logo**: PNG/SVG con trasparenza; ratio entro limiti; peso massimo; safe-area; resa garantita su sfondo brand e su chiaro.
5. **Foto**: ratio imposti (16:9 attività, 1:1 staff/servizio), crop guidato, compressione e risoluzione minima; numero massimo per galleria (es. 8).
6. **Fallback obbligatorio**: ogni asset mancante → stato testuale curato (iniziali, icona categoria, nome) — mai un placeholder rotto.
7. **Token immutabili**: il cliente non tocca raggi, spaziatura, tipografia, semantica colori.

---

# PARTE 4 — Design System Premium (Figma-ready)

## Color tokens (default piattaforma; *= tematizzabile per tenant)
| Token | Default | Uso |
|---|---|---|
| primary* | `#1F2937` | azioni, header, sidebar |
| on-primary | `#FFFFFF` | testo su primary |
| accent* (secondary) | `#C8A24B` | selezione/evidenza |
| on-accent | `#1F2937` | testo su accent |
| background | `#F9FAFB` | sfondo schermo |
| surface | `#FFFFFF` | card/superfici |
| surface-variant *(nuovo)* | `#F3F4F6` | profondità/sezioni |
| on-surface | `#111827` | testo principale |
| on-surface-muted | `#6B7280` | testo secondario |
| border/outline | `#E5E7EB` | bordi/divider |
| success / warning / error | `#15803D` / `#B45309` / `#B91C1C` | stati (fissi) |
| *tint badge* | `#DCFCE7` / `#FEF3C7` / `#FEE2E2` | sfondi badge stato |

## Typography (raccomandata: **Inter** — UI; numeri **tabular figures**)
| Stile | Size/Line | Peso | Uso |
|---|---|---|---|
| Display/H1 | 28/34 | 700 | titoli schermo |
| H2 | 22/28 | 600 | sezioni |
| Title | 17/24 | 600 | titoli card |
| Body | 15/22 | 400 | testo |
| Body-strong | 15/22 | 500 | enfasi |
| Label | 13/18 | 500 | etichette/caption |
| Micro | 11/16 | 600 | header tabella (uppercase) |
| KPI number | 30/36 | 700 tabular | numeri dashboard |

*Oggi: Flutter usa Roboto, dashboard usa system stack. Unificare su Inter (font di piattaforma, NON tematizzabile per tenant).*

## Spacing (griglia 8pt)
Scala: **4 · 8 · 12 · 16 · 24 · 32 · 48**. Padding schermo: 16 (mobile) / 24–32 (dashboard). Padding card: 16–20. Gap tra card: 12–16. Touch target minimo: 44px.

## Elevation (introdurre profondità — oggi tutto piatto/bordi)
- **L0** sfondo · **L1** card: `0 1px 2px rgba(0,0,0,.04)` + bordo 1px · **L2** CTA sticky/modale: `0 4px 16px rgba(0,0,0,.10)`.

## Componenti (spec)
- **Button primary**: h48 (app)/h40 (dashboard), radius=medium, bg primary, testo on-primary 600; loading=spinner 20px inline; pressed=brightness .92; disabled=opacity .5.
- **Button secondary**: stesse metriche, bg surface, bordo+testo primary. **Destructive**: bg/testo error.
- **Card**: radius 16 (unificare app/dashboard), bg surface, elevazione L1, padding 16–20. (App oggi radius 24 → ridurre per coerenza.)
- **Input**: filled, bordo 1px, radius=medium, h~48, label sopra (dashboard)/floating (app), focus ring 2px primary, errore con messaggio inline + colore error.
- **Chip (giorno/slot/operatore)**: radius=small, selezionato=accent+on-accent, non selezionato=surface+bordo; min-height 40, touch 44.
- **Avatar operatore/cliente**: cerchio, foto o **iniziali su tinta derivata** (fallback).
- **Badge stato**: pill, tint+colore semantico (mappa: confirmed/completed→ok, requested→warn, cancelled→off, no-show→danger).

## States (alzare la qualità percepita)
- **Loading**: sostituire lo spinner nudo con **skeleton** (shimmer su card/liste) → velocità percepita. Splash con logo animato.
- **Empty**: icona/illustrazione + titolo + sottotitolo + (CTA). Oggi: solo testo grigio.
- **Error**: icona + messaggio + "Riprova" (già presente → standardizzare con il pattern).
- **Success**: `booking_success` esiste → elevare con check animato + brand.

---

# PARTE 5 — Customer Experience

## Cliente finale
Apertura (**splash brand**) → Comprensione valore (**onboarding mancante** = opportunità) → Registrazione → Verifica email → Prima prenotazione → Conferma → Ritorno (storico; **push mancante** = leva retention nota).
- **Fiducia**: logo + foto reali (attività/staff), splash brandizzata, indirizzo+mappa, orari.
- **Conversione**: hero+CTA prominenti, slot evidenti, flusso a 2 step già snello (non allungarlo), avatar operatore.
- **Retention**: storico, "riprenota uguale" (UI futura), profilo curato; i promemoria push restano gap funzionale segnalato.

## Professionista
Login → MFA → Configurazione (servizi, operatori, orari, **brand**) → Pubblicazione (**anteprima app mancante**) → Prime prenotazioni.
- **Aumentare fiducia/attivazione**: **onboarding guidato** (checklist setup), **anteprima live dell'app** nel brand editor, empty-state che guidano ("crea il primo servizio").

---

# PARTE 6 — Roadmap grafica

### P0 — Prima della demo (indispensabile, app + dashboard in parallelo)
| # | Cosa | Perché | Impatto | Tempo |
|---|---|---|---|---|
| 1 | **Design tokens unificati + libreria Figma** (colori/tipografia/spacing/componenti) | Base di tutto il resto | Alto (fondazione) | 3–5g |
| 2 | **Wire upload logo (`BrandAsset`) + logo** in splash/login/home/sidebar | Senza logo non è né premium né white-label credibile | Altissimo | 2–3g (+piccola connessione backend upload) |
| 3 | **Splash brandizzata** (logo+sfondo brand, no spinner nudo) | Prima impressione/fiducia | Alto | 1g |
| 4 | **Skeleton loaders + empty/error/success curati** (app+dashboard) | Velocità e qualità percepite | Alto | 2–3g |
| 5 | **Restyle componenti app** (button/card/input/chip + ombre soft + spacing 8pt) | Sensazione premium | Alto | 3–4g |
| 6 | **Restyle dashboard** (profondità, tipografia, KPI tabellari, badge) | È ciò che vede il compratore | Alto | 2–3g |
| 7 | **Avatar/foto operatori + foto/icone servizi** (con fallback) | Fiducia/conversione | Medio-Alto | 2–3g (UI; upload backend piccolo) |
| 8 | **Home app con hero brand** + CTA prominente | Conversione prenotazione | Medio-Alto | 1–2g |

### P1 — Qualità SaaS premium (non blocca)
Onboarding professionista guidato + **anteprima live app** nel brand editor · onboarding/intro valore nell'app · scheda attività ricca (galleria/orari/social/mappa — richiede estensione config backend già nota) · microinterazioni/transizioni · **vista calendario** dashboard (giorno/settimana a blocchi) · **QR per MFA** · iconografia servizi per categoria.

### P2 — Lusso / differenziazione
Preset tema per verticale (barbiere/medico/estetista) come punto di partenza · illustrazioni custom (empty/success) · UI centro avvisi in-app (gap noto) · UI recupero password (gap noto) · recensioni/feedback post-appuntamento · splash animata bi-fase · dark mode (engine oggi light-only).

---

# Riepilogo finale

## 1. Stato UX attuale
Prodotto **funzionalmente completo e coerente**, ma **visivamente generico**: Material/Blade default, **zero immagini di brand** (logo = testo), stati spinner/testo, dashboard "da gestionale". Le **fondamenta sono solide** (token white-label condivisi app↔dashboard, pattern coerenti, contrasto validato) → elevare a premium è a **basso rischio** e non richiede toccare i flussi.

## 2. Le 10 modifiche a maggior impatto
1. Collegare l'**upload logo** e mostrarlo (splash, login, home, sidebar dashboard).
2. **Tokens + libreria componenti** unificati (Figma) per app e dashboard.
3. **Splash brandizzata** (no spinner nudo).
4. **Skeleton loaders** al posto degli spinner.
5. **Empty/error/success** curati (icona+titolo+sottotitolo+CTA).
6. **Profondità premium**: ombre soft + spacing 8pt + raggi coerenti.
7. **Avatar/foto operatori** nei chip e nelle card appuntamento.
8. **Home app con hero** brand + CTA "Prenota" prominente.
9. **Dashboard più "prodotto"**: profondità, tipografia, KPI tabellari.
10. **Tipografia premium unificata** (Inter) + numeri tabellari KPI.

## 3. Ordine consigliato
Tokens/Figma → pipeline logo/asset brand → shell app (splash/login/home) **+** shell dashboard in parallelo → restyle componenti → stati (skeleton/empty/error) → imagery (operatori/servizi/galleria) → microinterazioni → preset verticali.

## 4. Cosa NON toccare (già solido)
- **Flusso prenotazione a 2 step** (servizi → giorno+operatore+ora): efficiente e allineato al benchmark → restyle grafico sì, struttura/sequenza no.
- **Motore disponibilità/slot** e raggruppamento Mattina/Pomeriggio.
- **Auth, verifica email, cancellazione account** (flussi e posizioni — conformi Apple/GDPR): restyle visivo sì, struttura no.
- **Contratto token white-label** (chiavi colori/raggi) e validazione contrasto: estendere (logo/foto) sì, rinominare/compattare no.
- **Information architecture dashboard** (sidebar, sezioni) e visibilità per ruolo (Titolare/Operatore).

## Verifica
Tutte le schermate elencate derivano da lettura diretta dei file reali (app Flutter, viste Blade, `dashboard.css`, `config/branding.php`, motore white-label). I "mancanti" sono verificati (es. recupero password: grep vuoto). Nessun file applicativo è stato modificato.
