# 31 — Dashboard: Super Admin, Tenant, App Cliente (progettazione funzionale-tecnica)

## 1. Mappa delle superfici

| Superficie | Utenti | Tecnologia | Accesso |
|---|---|---|---|
| Dashboard Super Admin | Software house | Laravel web (dominio dedicato, allowlist rete, MFA) | `admin.<dominio-piattaforma>` |
| Dashboard Tenant (web) | Tenant Admin | Laravel web, tema per tenant | `<tenant>.<dominio-piattaforma>` |
| App Gestionale (mobile) | Tenant Admin + Staff | Flutter (unica) | store |
| App Cliente (mobile) | Customer | Flutter white label | store per tenant |

Ripartizione deliberata: la **configurazione complessa** (wizard onboarding, branding, catalogo, report) vive sul web; l'**operatività quotidiana** (agenda, walk-in, no-show) vive nell'app gestionale. Le due superfici condividono la stessa API `/manage`.

## 2. Dashboard Super Admin (Modulo A1-A5)

### Sezioni
1. **Tenant**: lista con stato, piano, adozione (app installate, prenotazioni/mese), filtri "at risk"; scheda tenant con onboarding_state, build, fatturazione, audit
2. **Provisioning**: wizard creazione tenant (dati, settore → preset servizi/orari, piano, flag salute), invio invito
3. **Build & Release**: coda build per stato (queued/building/in_review/rejected/published), retry, log; vista "treno di rilascio" per i rebuild massivi
4. **Billing**: subscriptions per stato, past_due, azioni di sospensione/riattivazione (con conferma e motivazione → audit)
5. **Piattaforma**: metriche aggregate (prenotazioni/giorno, p95 availability, error rate, coda job), feature flag globali, gestione piani
6. **Supporto**: impersonificazione a tempo con banner e audit ([26](26-autenticazione-autorizzazione.md) §6)

## 3. Dashboard Tenant — wizard di onboarding

Step guidati persistiti in `tenants.onboarding_state` (riprendibile, KPI di completamento):

1. Attività e sedi (indirizzo → timezone autodeterminata)
2. **Brand Studio**: upload logo master → generazione automatica icona/splash con anteprima live ([27](27-white-label-tecnico.md) §8); palette da template curati o colori propri con validazione contrasto WCAG in tempo reale; nome app con verifica vincoli store (lunghezza)
3. Catalogo: preset di settore modificabili (es. barbiere: taglio/barba/taglio+barba con durate tipiche)
4. Staff: profili, foto, servizi abilitati, orari individuali (default: orari sede)
5. Orari sede + eccezioni iniziali
6. Notifiche: soglie promemoria, testi
7. (condizionale settore sanitario) attivazione modulo dati salute + checklist DPIA
8. Import clienti CSV (mapping colonne guidato, anteprima, report errori riga per riga)
9. Riepilogo → "Pubblica": stato `ready_for_build`, trigger pipeline

## 4. Dashboard Tenant — esercizio

- **Home**: KPI del periodo (prenotazioni, occupazione %, no-show %, fatturato stimato), prossimi appuntamenti, alert (richieste pending, build respinta, pagamento fallito)
- **Agenda**: vista giorno/settimana per staff e sede; creazione appuntamento manuale; drag per riprogrammare (con notifica al cliente)
- **Clienti**: ricerca, scheda con storico/no-show/note (visibilità per ruolo), segmenti (inattivi da N giorni)
- **Marketing**: campagne (editor, segmento, anteprima, programmazione), contatori quota piano
- **Brand**: modifica tema/logo (effetto runtime), richiesta cambio icona/nome (spiega che richiede nuova pubblicazione store)
- **Report**: andamento, per staff, per servizio; export CSV asincroni con notifica
- **Impostazioni**: parametri prenotazione ([30](30-engine-appuntamenti.md) §9), utenti staff e permessi, abbonamento e fatture, privacy (registro consensi, richieste GDPR)

## 5. App Gestionale (Flutter) — perimetro funzionale

- Login (MFA per admin), selezione tenant implicita dall'account
- Agenda personale (staff) o completa (admin/permessi); azioni rapide: conferma richiesta, no-show, completa, walk-in
- Notifiche push operative: nuova prenotazione, cancellazione, richiesta pending, promemoria turno
- Dichiarazione indisponibilità (Flusso 5)
- Scheda cliente in sola lettura + note (secondo permessi)
- Niente configurazione branding/catalogo da mobile (rimanda al web): mantiene l'app semplice

## 6. App Cliente (Flutter white label) — perimetro funzionale

- Onboarding: splash brand → benvenuto → login/registrazione (social prioritario) → permesso notifiche (richiesto **dopo** la prima prenotazione, momento di massima motivazione)
- Home: CTA "Prenota", prossimo appuntamento, promozioni attive
- Flusso prenotazione: servizio/i → operatore (o "nessuna preferenza") → calendario slot (per giorno, lazy load) → riepilogo → conferma
- I miei appuntamenti: futuri/passati, riprogramma/cancella (entro cutoff), aggiungi al calendario di sistema
- Profilo: dati, preferenze notifiche granulari, consensi, privacy (export/cancellazione), lingua
- Stati speciali: tenant sospeso/cessato (schermata di cortesia), versione minima non soddisfatta (blocking update)

## 7. Accessibilità e qualità UI

- Design system con token ([27](27-white-label-tecnico.md)): contrasto AA garantito dalla validazione in fase di configurazione brand
- Touch target ≥ 44pt, dynamic type/scalable text, screen reader label sui flussi critici
- Localizzazione IT/EN da subito; struttura pronta per altre lingue (criticità Fase 1 n.13)
