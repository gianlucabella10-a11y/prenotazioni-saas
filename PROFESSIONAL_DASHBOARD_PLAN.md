# PROFESSIONAL_DASHBOARD_PLAN — Dashboard gestionale del professionista (MVP)

**Data**: 12/06/2026 · Obiettivo: chiudere il blocco B1 della [checklist](MVP_CLIENT_RELEASE_CHECKLIST.md) ("il professionista non ha interfaccia") con il **minimo prodotto vendibile**.

## 1. Architettura scelta

**Dashboard server-rendered Laravel (Blade) dentro `platform-backend`**, sessione classica + CSRF.

Motivazioni (vincolanti, dai documenti fonte):
- docs/21 §3.2 e docs/31 §1 prescrivono esattamente questo: dashboard web Laravel, tema per tenant via CSS variables
- docs/26 (review #26): la superficie web usa **sessione server-side, non JWT** — il JWT resta per le app mobile
- Vincolo ambientale: nessun Node/npm sulla macchina → una SPA non sarebbe testabile; Blade è verificabile con la suite PHP esistente
- Anti-duplicazione: i servizi applicativi (booking engine, transizioni, quote, contrasto, cache versioning) sono già nel processo Laravel → riuso diretto, zero chiamate HTTP interne, zero logica duplicata

**Stack frontend**: Blade + un foglio CSS proprietario (`public/css/dashboard.css`, design system minimale con CSS variables brandizzate) + vanilla JS essenziale (conferme distruttive). Niente framework JS, niente build step: il massimo della manutenibilità per un MVP B2B.

## 2. Ruoli

| Ruolo richiesto | Mappatura | Permessi |
|---|---|---|
| **OWNER** | `UserType::TenantAdmin` esistente | Tutto il tenant: servizi, staff, orari, prenotazioni, brand, contatti |
| **ADMIN** | = OWNER in questo MVP | Non esiste oggi un caso d'uso che distingua ADMIN da OWNER per un'attività 1-10 persone; introdurre un terzo tipo ora violerebbe "minimo vendibile". Documentato come decisione, riapribile coi permessi granulari (docs/26 §5) |
| **STAFF** | `UserType::Staff` esistente | Solo prenotazioni proprie (vista + conferma/completa/no-show); nessuna configurazione |

## 3. Inventario API/logica: cosa esiste, cosa manca

### Esistente e riusato direttamente (nessuna duplicazione)
| Capacità | Componente riusato |
|---|---|
| Transizioni appuntamento (conferma/completa/no-show) | `TransitionAppointment` (Application) |
| Cancellazione lato tenant con notifiche | `CancelAppointment::byTenant` |
| Quote piano su creazione servizi/staff | `QuotaService` |
| Invalidazione disponibilità su modifica orari | `AvailabilityCacheVersion` |
| Validazione contrasto brand | `ContrastValidator` |
| Bump config white label + invalidazione registry | logica `ManageBrandController` (riusata nel web controller) |
| MFA TOTP | `Totp` + `MfaCredential` |
| Isolamento tenant | `BelongsToTenant`/`TenantContext` (il middleware web binda il contesto dallo user di sessione) |

### Mancante — da implementare in questa fase
| Gap | Dove |
|---|---|
| Login web a sessione (+ challenge MFA web, setup MFA web) | `WebAuthController` + viste |
| **Accettazione invito** (set password da `invite_token` del provisioning — senza, l'owner non può proprio entrare) | `WebAuthController@invite*` |
| CRUD eccezioni orario (ferie/chiusure) — progettato in docs/25 ma mai implementato | `ExceptionsController` (web) + bump cache |
| Modifica contatti sede (telefono/indirizzo/nome) | `ContactsController` (web) |
| Vista calendario gestionale + pending requests | `BookingsController` (web, riusa la finestra giorno-locale di ManageAgendaController) |
| Editor orari settimanali per operatore (UI mattina/pomeriggio) | dentro `StaffController` web (stessa semantica del PUT API esistente) |

### Esplicitamente FUORI scope (regola "minimo vendibile")
Analytics/grafici · CRM clienti completo · campagne marketing · multi-sede UI (il modello li supporta; l'MVP gestisce la sede unica del provisioning) · upload logo/immagini (è compito della pipeline asset store, [APP_STORE_READINESS](APP_STORE_READINESS.md) #1) · gestione utenti staff con inviti dal pannello (post-MVP; gli staff login si creano via API admin) · ruolo ADMIN distinto.

## 4. Pagine

| # | Pagina | Ruolo minimo |
|---|---|---|
| 1 | Login (+ MFA verify, MFA setup, Accetta invito) | pubblico |
| 2 | **Home**: oggi (lista appuntamenti), in attesa di conferma, prossimi 7 giorni (conteggio), top servizi 30gg, operatori attivi | STAFF (vista ridotta: solo propria agenda) |
| 3 | **Prenotazioni**: giorno navigabile, filtro operatore, azioni stato + cancella; tab "In attesa" | STAFF (solo proprie) |
| 4 | **Servizi**: lista + form crea/modifica (nome, descrizione, prezzo, durata, buffer, attivo), disattiva/elimina (soft) | OWNER |
| 5 | **Operatori**: lista + form (nome, ruolo, prenotabile, servizi abilitati) + **orari settimanali** (fasce mattina/pomeriggio per giorno) + disattiva | OWNER |
| 6 | **Disponibilità**: orari sede + **eccezioni** (ferie/chiusure, sede o operatore, con fascia oraria opzionale) | OWNER |
| 7 | **Personalizzazione app**: nome app, tagline, colori (con validazione contrasto), URL legali; **Contatti**: nome sede, indirizzo, telefono | OWNER |

## 5. Ordine di sviluppo

1. Foundation: route web, middleware (BindDashboardTenant, RequireOwner), auth (login→MFA→sessione), invito, layout+CSS
2. Home → 3. Servizi → 4. Operatori+orari → 5. Disponibilità/eccezioni → 6. Prenotazioni → 7. Brand+contatti
8. Test di sicurezza (isolamento web, permessi) e funzionali → 9. Suite complete + E2E mobile di regressione → 10. Readiness report

## 6. Rischi

| Rischio | Mitigazione |
|---|---|
| Email staff duplicata tra tenant ⇒ login web ambiguo (niente sottodomini in MVP) | Match multiplo ⇒ errore esplicito "contatta il supporto"; documentato; si risolve coi sottodomini in produzione (docs/24 tenant_domains) |
| MFA setup web senza QR renderer | MVP: otpauth URI cliccabile + secret copiabile (le app authenticator accettano entrambi); QR in miglioramenti futuri |
| Duplicazione regole di validazione tra API e web controller | Accettata e circoscritta (~10 righe/risorsa); estrazione FormRequest condivisi quando si toccherà l'API (no refactoring ora) |
| Sessioni + API nello stesso backend | Superfici separate da middleware group; CSRF attivo sul web; nessun cookie sulle rotte api/* |
| Orari sede vs engine (l'engine usa gli orari STAFF) | UI chiarisce: gli orari per operatore governano la prenotabilità; gli orari sede sono informativi/default — coerente con docs/30 |
