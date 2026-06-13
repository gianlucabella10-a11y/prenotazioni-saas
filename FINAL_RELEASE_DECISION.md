# FINAL_RELEASE_DECISION

**Data**: 12/06/2026 · Basata su [MVP_PRODUCTION_READINESS_REPORT.md](MVP_PRODUCTION_READINESS_REPORT.md) e [BACKEND_RUNTIME_STATUS.md](BACKEND_RUNTIME_STATUS.md). Riferimenti rilievi: S=security, F=Flutter, B/C=classificazione §10.

> **⚡ AGGIORNAMENTO post-stabilizzazione (stesso giorno)**: S1/S2/S3/F1/F2 risolti e testati (vedi addendum nel report e [MVP_CLIENT_RELEASE_CHECKLIST.md](MVP_CLIENT_RELEASE_CHECKLIST.md)). La decisione 1 passa da **GO condizionato** a **GO** (condizioni residue solo operative: Sentry al giorno 1, lato negozio operato da noi — la condizione "dati di test per S1" è decaduta). Le decisioni 2 e 3 restano NO-GO con liste accorciate: vendita bloccata dall'interfaccia professionista + push client; store bloccato da pipeline asset + Data Safety + pagina web deletion Play (account deletion e privacy in-app sono ✅).

---

## 1. Beta privata → **GO (condizionato)**

Il flusso del cliente finale è reale, stabile (E2E 5/5 consecutive) e degno di essere messo in mano a utenti veri su un tenant pilota.

**Condizioni vincolanti per l'avvio:**
1. Clienti finali di test o informati — finché **S1** (link CRM senza verifica email) resta aperto, nessuna anagrafica reale pre-caricata nel tenant pilota
2. Crash reporting (Sentry o equivalente) installato il giorno 1 — oggi la beta sarebbe cieca (F8)
3. Il lato negozio lo operiamo noi via API (nessuna autonomia del professionista in questa fase, dichiarato esplicitamente al pilota)
4. Promemoria dichiarati "via email" finché il canale push non esiste

**Razionale**: una beta serve a imparare dall'uso reale del flusso di prenotazione — quello c'è ed è solido. Tutto il resto è onestamente dichiarabile come "in arrivo".

## 2. Primo cliente pagante → **NO-GO**

**Motivi bloccanti (lista B del report):**
1. **Il professionista non ha interfaccia**: paga 250€/mese e non può né vedere l'agenda né confermare una richiesta. È il blocco assoluto.
2. **S1** è una violazione privacy in attesa del primo caso reale: invendibile con anagrafiche vere.
3. La promessa commerciale n.1 (promemoria automatici che riducono i no-show) **non arriva sul telefono** del cliente finale.
4. P0 funzionali visibili a occhio nudo confrontando col riferimento: note, richiesta-senza-slot, foto, orari/contatti in app.
5. Nessun pacchetto legale (privacy/DPA/contratto) né billing di attivazione.

**Percorso di sblocco stimato** (ordine dall'handover §15, incremento 2-4): backend P0+P1 → push end-to-end → gestionale minimo (agenda+conferme) → legale. Al completamento, la decisione torna in revisione.

## 3. Pubblicazione App Store → **NO-GO**

**Motivi bloccanti (lista C del report):**
1. **Rigetto certo** Apple 5.1.1(v): manca la cancellazione account in-app (S2); Google Play: idem + link web
2. Privacy policy assente in app e nei metadata
3. Icona/splash ancora default Flutter: la pipeline di branding nativo per tenant non è costruita
4. Build store deve essere https-only (ATS) — pipeline non esistente
5. Il rischio strutturale 4.2.6 (app template) va validato con 2-3 tenant pilota su account Apple del tenant **prima** di qualsiasi rollout — gate già fissato in ARCHITECTURE_FINAL_REVIEW

**Nota di percorso**: per la beta privata non serve lo store — TestFlight interno (quando ci sarà un Mac con Xcode) o build dirette su device bastano e sono coerenti con la strategia di distribuzione progettata.

---

## Sintesi

| Traguardo | Decisione | Primo passo di sblocco |
|---|---|---|
| Beta privata | **GO condizionato** | Sentry + tenant pilota con dati di test |
| Primo cliente pagante | **NO-GO** | Incremento 2 (backend P0+P1, S1 incluso) poi gestionale minimo |
| App Store / Play Store | **NO-GO** | Account deletion + privacy policy + pipeline branding |

La fotografia onesta: **il motore c'è e gira; mancano metà della carrozzeria e i documenti per circolare.** Nessuno dei NO-GO dipende da incognite di ricerca: sono tutte lavorazioni già progettate, stimate e ordinate nei documenti di handover.
