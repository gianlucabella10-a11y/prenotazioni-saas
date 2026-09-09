# PROFESSIONAL_DASHBOARD_READINESS — Release report

**Data**: 12/06/2026 · **Stato verificato**: backend **90/90 test (411 assertion)** di cui 13 dedicati alla dashboard (93 assertion) · Flutter **28/28** · zero regressioni · piano seguito: [PROFESSIONAL_DASHBOARD_PLAN.md](PROFESSIONAL_DASHBOARD_PLAN.md).

La dashboard chiude il **bloccante B1** ("il professionista non ha interfaccia") di [MVP_CLIENT_RELEASE_CHECKLIST.md](MVP_CLIENT_RELEASE_CHECKLIST.md).

---

## A) PRONTO PER BETA CLIENTE

Tutto testato con test eseguiti, non dichiarati:

- **Accesso production-grade**: accettazione invito (token monouso 72h dal provisioning) → password → **MFA TOTP obbligatoria per il titolare** (setup guidato + challenge ai login successivi, docs/14 §2) → sessione con regenerate anti-fixation, CSRF, logout
- **Ruoli**: OWNER gestisce tutto; STAFF vede solo la propria agenda e agisce solo sui propri appuntamenti (403 sulla configurazione, 404 sugli appuntamenti altrui); STAFF senza profilo agenda → fail-closed 403 (bug trovato e corretto in review, non degradava più a "vede tutto")
- **Isolamento tenant sul web**: testato — listing scoped, uuid altrui → 404 mai 403, scritture cross-tenant impossibili
- **Home operativa**: oggi, in attesa, prossimi 7 giorni, top servizi 30gg, operatori attivi — niente grafici
- **Servizi**: CRUD con validazioni (nome/prezzo/durata), prezzo in euro → cents, buffer, attivo/disattivo, **eliminazione soft** (storico intatto)
- **Operatori**: CRUD, servizi abilitati, **orari settimanali a fasce mattina/pomeriggio** Lun-Dom con validazione, disattivazione soft
- **Disponibilità**: chiusure/ferie/blocchi (sede o singolo operatore, giorno intero o fascia) — **test d'integrazione col motore reale**: la chiusura fa sparire gli slot dall'API pubblica e la rimozione li ripristina (cache invalidata, zero calcoli duplicati)
- **Prenotazioni**: agenda per giorno (fuso del tenant), filtro operatore, richieste in attesa in evidenza, azioni conferma/rifiuta/completa/no-show/annulla — **riusano TransitionAppointment/CancelAppointment**: il cliente riceve le stesse notifiche del flusso API (verificato sull'outbox)
- **Personalizzazione App**: nome, slogan, colori (validazione contrasto WCAG riusata), URL legali https, contatti sede — **test end-to-end del white label**: il salvataggio cambia `GET /app/config` ("Salone Verdi" → "Dott. Verdi" verificato nel test, senza toccare codice)
- UI: server-rendered, CSS proprietario brandizzato col colore del tenant, responsive, messaggi flash, conferme sulle azioni distruttive

## B) BLOCCANTI PRIMO CLIENTE PAGANTE

1. **Distribuzione dell'app cliente** al pubblico del tenant: build white label + canale (TestFlight/store) sono processi ancora manuali e mai eseguiti (pipeline asset = blocker C1 di APP_STORE_READINESS)
2. **Push sul telefono**: promemoria oggi solo email (FCM client + APNs mancanti — foundation backend pronta)
3. **Contratto + incasso**: 990€/250€ senza contratto, DPA e billing non si fatturano (gestibile manualmente per il n.1, da automatizzare presto — ARCHITECTURE_FINAL_REVIEW §0.2)
4. Crash reporting (Sentry) su app e backend
5. Hosting reale (oggi: locale; Terraform progettato in docs/32, mai applicato) + https

## C) MIGLIORAMENTI FUTURI (non bloccanti)

QR code per il setup MFA (oggi: secret + otpauth-URI) · gestione utenti STAFF dal pannello (oggi: via API admin) · creazione prenotazione manuale walk-in dal pannello · vista settimana del calendario · ricerca clienti · sottodomini per tenant (risolve anche l'ambiguità email multi-tenant del login web) · pagina web "elimina account" (requisito Play, 1 pagina) · note prenotazione e richiesta-senza-slot (P0 gap app, lato pannello già predisposto dalle richieste in attesa)

## D) FEATURE POST-MVP

Multi-sede UI · report/export · campagne marketing · import CSV clienti · waitlist · ruolo ADMIN distinto con permessi granulari · upload logo/galleria (pipeline asset) · billing self-service

---

## Verdetti

| Traguardo | Verdetto | Motivazione |
|---|---|---|
| **Primo cliente pilota** | **GO** | Il loop completo esiste ed è testato: provisioning → invito → MFA → il titolare configura servizi/operatori/orari/brand da solo → i suoi clienti prenotano dall'app → lui conferma/gestisce dal pannello → notifiche email reali. Condizioni operative già note (Sentry, app distribuita da noi al pilota, server raggiungibile) |
| **Primo pagamento** | **GO CONDIZIONATO** | Il prodotto ora si amministra autonomamente — il valore per cui si paga c'è. Condizioni da chiudere PRIMA di incassare: lista B punti 1-3 (distribuzione app ai clienti finali, push, contratto). Senza app distribuita ai clienti del tenant, i 250€/mese non sono difendibili |
| **Scalabilità primi 10 clienti** | **GO (tecnico)** | Provisioning automatizzato in secondi, isolamento dimostrato da test su ogni superficie, carico irrisorio (docs/32: margini 100×). Collo di bottiglia: operations store manuali per 10 app — accettabile a questa scala, da industrializzare oltre (Release Train Orchestrator, trigger ≤50 tenant) |

**Prossimo incremento consigliato** (ordine di sblocco del GO pagamento): push end-to-end (FCM client) → deployment AWS minimo + https → pipeline build white label per il primo tenant → contratto/billing.
