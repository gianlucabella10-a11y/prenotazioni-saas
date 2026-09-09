# FINAL PRODUCT READINESS AUDIT

> Audit di produzione **read-only** — nessun file del prodotto è stato modificato.
> Data: 2026-06-14 · Commit: `6f6ca0c` (`origin/main` allineato).
> Valutato come se domani arrivasse un cliente pagante (non come demo).

Identico nel contenuto a [`FINAL_TECHNICAL_FREEZE_REPORT.md`](FINAL_TECHNICAL_FREEZE_REPORT.md).

---

## VERDETTO GENERALE (GO / NO-GO)

| Asse | Verdetto | Motivazione sintetica |
|---|---|---|
| **A) Continuare solo con la grafica** | **🟢 GO** | Schermate stabili e testate; il theming white-label su cui poggia la grafica è solido. Il lavoro grafico non sarà sprecato |
| **B) Beta reale** | **🟡 GO condizionato** | Demo controllata pronta; una beta con pilota vero richiede deploy+HTTPS (oggi localhost) e push |
| **C) Primo cliente pagante** | **🔴 NO-GO** | Mancano deployment produzione, push sul telefono, billing, 2 gap funzionali P0 |
| **D) Pubblicazione store** | **🔴 NO-GO** | Mancano icone/splash brandizzate, account Apple, min-version, Data Safety/Nutrition Label |

**Sintesi: 🟡 GIALLO.** Il nucleo tecnico è solido, testato e da non riscrivere; la grafica può partire subito. Restano però lavori tecnici **non grafici** prima di incassare: non è un "freeze totale".

---

## Evidenza raccolta (comandi reali eseguiti, non memoria)
- `php artisan test` → **90 passed, 411 assertions**
- `flutter analyze` → **No issues found** · `flutter test` → **28 passed** (1 e2e skip)
- 8 middleware di sicurezza registrati: `tenant.key, tenant.operating, tenant.dashboard, user.type, auth.full, verified, owner, feature`
- Test sicurezza dedicati: `TenantIsolationTest`(5), `DashboardSecurityTest`(7), `EmailVerificationTest`(8), `AccountManagementTest`(5)
- `git`: origin = `github.com/gianlucabella10-a11y/prenotazioni-saas`, `origin/main` allineato a HEAD (4 commit) → **push avvenuto**
- `.env` attivo = **locale** (`APP_ENV=local`, `APP_DEBUG=true`, DB sqlite, MAIL smtp/Mailpit, cache file, queue database)
- Pubspec Flutter: `sentry_flutter ^9.22.0` presente, **`firebase_messaging` ASSENTE** (verificato)
- Gap confermati assenti: FCM client, Cashier/Stripe, migrazione `booking_requests`, campo note-cliente in `BookAppointment`, endpoint `min-version`, estensione coverage (pcov/xdebug)

---

## Stato reale per componente

### BACKEND — 🟢 funzionante e verificato
- **Funzionante/verificato**: multi-tenant 5 livelli con suite di isolamento; auth JWT + refresh-rotation + MFA TOTP; verifica email (fix S1, 8 test incl. scenario attaccante); GDPR (consensi versionati, cancellazione account Apple-compliant); booking engine atomico timezone-aware; outbox notifiche; RBAC (8 middleware); endpoint devices. **90/90 test.**
- **Mancante**: configurazione di **produzione attiva** (gira in locale: `APP_DEBUG=true`, sqlite); billing; coverage misurata.

### FLUTTER (app cliente) — 🟢 codice solido
- **Funzionante/verificato**: analyze 0 issue, 28 test; flusso completo config→registrazione→verifica email→login→servizio→operatore→slot→prenotazione→storico→profilo+cancellazione; white-label runtime; gestione errori/loading/empty; persistenza sessione (secure storage); idratazione `/me` al bootstrap.
- **Mancante**: integrazione **FCM client** (push non arrivano al telefono — backend pronto); 2 gap P0 (nota prenotazione, richiesta-senza-slot); build iOS su simulatore non renderizza (stack Xcode26/iOS26/Flutter3.44 + xattr iCloud — **l'app compila**, e il web rende lo stesso codice).

### DASHBOARD PROFESSIONISTA — 🟢 vendibile come funzione
- **Funzionante/verificato**: login+MFA+invito; home; CRUD servizi; CRUD operatori+orari a fasce; disponibilità+ferie (test d'integrazione col motore reale); calendario prenotazioni con azioni di stato; personalizzazione brand+contatti. 13 test dedicati. **Un professionista la usa senza assistenza** (verificato anche via preview reale).
- **Mancante**: nulla di bloccante per l'uso autonomo; il QR per MFA è testuale (minore).

### INFRA — 🟡 progettata, non operativa
- **Funzionante/verificato**: IaC Terraform pilota (EC2+RDS+Caddy/ACME+S3+SES, ~417 righe); `deploy.sh`; `.env.production.example`; runbook `DEPLOY_PILOT.md`; repo GitHub privato pushato.
- **Mancante**: **mai applicato** — nessun server live, nessun HTTPS, nessun dominio; backup/monitoring non attivi (esistono nel design RDS).

### STORE — 🔴 non pronto
- **Mancante**: icone/splash brandizzate (default Flutter); account Apple Developer del tenant (enrollment mai avviato); `min-version`+forced update; pagina web delete-account (Play); Data Safety/Nutrition Label; build https-only.
- **Già conforme**: cancellazione account in-app, privacy policy configurabile, consenso GDPR, verifica email, Sign-in-with-Apple non richiesto.

---

## Blocker assoluti (impediscono il PRODOTTO a un cliente pagante)
> Nessuno di questi è grafico. Nessuno richiede di riscrivere il core.

| # | Blocker | Gravità | Perché / Rischio reale | Soluzione necessaria |
|---|---|---|---|---|
| B1 | **Nessun deployment produzione** (tutto su localhost) | ALTA | Senza HTTPS/dominio nessun utente reale accede; `APP_DEBUG=true` espone stack trace | Applicare Terraform pilota (richiede account AWS + dominio dell'utente) |
| B2 | **Push notifiche non integrate nel client** (FCM) | ALTA | I promemoria — promessa di valore n.1 per ridurre i no-show — non arrivano sul telefono | `firebase_messaging` + registrazione token su `PUT /me/devices` (backend già pronto) |
| B3 | **Billing assente** | ALTA | Non si può incassare 990€ + 250€ in automatico | Gateway (Stripe Cashier); per il pilota n.1 fatturabile a mano |
| B4 | **App non distribuibile ai clienti finali del tenant** | ALTA | Senza store/TestFlight o PWA pubblicata, i clienti del professionista non hanno l'app | Build web/PWA su dominio (post-deploy) o pipeline build nativa |

## Problemi NON bloccanti (possono convivere con la grafica)
- 2 gap funzionali P0 (nota su prenotazione, richiesta-senza-slot): visibili ma non impediscono il loop base.
- `min-version` endpoint (serve prima della 1ª app pubblicata, non prima della grafica).
- Coverage non misurata (90 test esistono; manca il numero % per assenza pcov).
- Simulatore iOS non renderizza (l'app compila; la via affidabile è device firmato/TestFlight).
- Icone/splash brandizzate (servono per lo store, non per disegnare le schermate).
- Pagina web delete-account per Google Play.

## Cose da NON toccare più (stabili e testate — la grafica le CONSUMA, non le riscrive)
- **Multi-tenant isolation** (`Foundation/Tenancy/*`, trait `BelongsToTenant`, `TenantScope`) — suite isolamento verde.
- **Auth**: JWT + refresh rotation con anti-furto, MFA TOTP, verifica email (fix S1) — 21 test fra Auth/Email/MFA.
- **Booking engine** (`Scheduling/Application/BookAppointment`, `AvailabilityCalculator`) — atomicità + fusi orari testati.
- **White Label engine**: `BuildWhiteLabelConfig` (backend) + `AppThemeBuilder`/`WhiteLabelConfig` (Flutter) — è l'interfaccia su cui la grafica lavora.
- **Dashboard controllers** (`Modules/Dashboard/*`) — RBAC e isolamento testati.
- Modello dati / migrazioni (additive expand-only) — non riscrivere.

## Checklist "Technical Freeze"
- [x] Backend test verdi (90/411)
- [x] Flutter analyze pulito + test verdi (28)
- [x] Isolamento tenant testato (utente A non vede dati B → 404)
- [x] Auth/MFA/email-verification testati
- [x] Cancellazione account GDPR/Apple presente e testata
- [x] White-label engine funzionante (dimostrato: cambio colore → app)
- [x] Codice su GitHub (privato)
- [ ] Deployment produzione attivo (HTTPS) — **manca**
- [ ] Push end-to-end — **manca**
- [ ] Billing — **manca**
- [ ] Coverage misurata — **manca (tooling)**

## Roadmap finale
**Prima della grafica (obbligatorio): NESSUNO.** Il core è stabile; puoi iniziare il design ora.

**Durante la grafica (non blocca, in parallelo):**
- Deployment pilota (B1) — dipende da account AWS + dominio dell'utente
- Integrazione FCM client (B2)
- Gap P0: nota prenotazione + richiesta-senza-slot
- Misurare coverage in CI (pcov)

**Dopo la grafica (pre-release):**
- Icone/splash brandizzate dal logo del tenant
- Billing + contratto/DPA (B3)
- Conformità store: min-version, delete-account web (Play), Data Safety/Nutrition Label
- Enrollment Apple + build firmata/TestFlight (B4)

---

## Domanda finale
**"Possiamo congelare la parte tecnica e lavorare sul design?"**

> **SÌ per il design** — il core è congelabile e non va toccato — **con la consapevolezza che esiste un backlog tecnico NON grafico** (deploy, push, billing, 2 gap) da completare prima del primo incasso, eseguibile **in parallelo** alla grafica.

## Verifica (come è stato accertato)
Tutto deriva da esecuzioni reali in sessione: `php artisan test`, `flutter analyze`, `flutter test`, `git` status/log, grep su pubspec/composer/migrazioni/routes, lettura `.env`. **Nessun file del prodotto è stato modificato.**
