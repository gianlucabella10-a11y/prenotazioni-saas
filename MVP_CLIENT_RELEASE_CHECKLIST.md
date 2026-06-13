# MVP_CLIENT_RELEASE_CHECKLIST

**Data**: 12/06/2026, post-stabilizzazione. Stato verificato: **backend 77/77 · app 28/28 · analyzer 0 · E2E con verifica email reale 2/2** (catena: SMTP→Mailpit→codice→verify→link CRM→booking→storico→cancellazione).

## A) GO BETA PRIVATA — cosa può provare un cliente selezionato OGGI

- ✅ Flusso completo del cliente finale: registrazione con consenso privacy → **verifica email a 6 cifre** (resend con cooldown) → catalogo → multi-servizio → operatore → slot reali → prenotazione → storico → cancellazione
- ✅ Sicurezza identità (ex S1 CRITICO, **risolto e testato**): nessun accesso allo storico altrui; il link al CRM avviene solo a proprietà dell'email dimostrata; codici hash-ati, scadenza 15', max 5 tentativi, monouso, cross-user impossibile
- ✅ Account self-service: profilo reale (GET/PATCH /me), consensi marketing versionati, **eliminazione account Apple-compliant** (immediata, revoca sessioni, pseudonimizzazione, audit)
- ✅ White label runtime con link legali per tenant
- ✅ Identità persistente tra riavvii (idratazione /me — ex F2) e selezione slot senza refetch (ex F1)
- **Condizioni operative della beta** (invariata da FINAL_RELEASE_DECISION): lato negozio operato da noi via API; crash reporting da installare al giorno 1; promemoria dichiarati "via email"

## B) BLOCCANTI — prima del primo cliente PAGANTE

1. ~~**Interfaccia professionista**~~ ✅ **RISOLTO** — Professional Management Dashboard consegnata e testata (13 test, 93 assertion): vedi [PROFESSIONAL_DASHBOARD_READINESS.md](PROFESSIONAL_DASHBOARD_READINESS.md)
2. **Push end-to-end**: FCM client + APNs (la foundation backend è pronta: devices ✅, canale ✅, outbox ✅)
3. P0 funzionali residui: note prenotazione, richiesta-senza-slot, foto staff, orari/contatti nella scheda attività
4. Crash reporting/analytics (Sentry) — ora è l'unico residuo "infrastrutturale" della lista
5. Pacchetto commerciale: contratto, DPA, billing attivazione

## C) STORE BLOCKERS — prima della pubblicazione

(dettaglio completo in [APP_STORE_READINESS.md](APP_STORE_READINESS.md))
1. Pipeline icone/splash brandizzate per tenant (unico blocker tecnico rimasto: deletion ✅, privacy ✅, consenso ✅ sono stati chiusi)
2. Pagina web "elimina account" (requisito Play)
3. Data Safety / Privacy Nutrition Label (dati già censiti)
4. Enrollment Apple tenant pilota + validazione 4.2.6; sharding account Play
5. Build https-only (ATS) dalla pipeline
6. `GET /app/min-version` + forced update

## D) POST MVP

Waitlist automatica · galleria sede/avatar · "nessuna preferenza" operatore · export GDPR (l'erasure c'è; l'export ha 30gg di finestra legale) · varianti multiple dalla card · motivo cancellazione · load-more storico · EN completo · campagne marketing · pagamenti/acconti · fuso sede negli orari (F4) · accessibilità pass completo (F7).

---

### Comandi di verifica beta (ambiente locale)

```bash
export PATH="$HOME/.local/php-toolchain/bin:$HOME/.local/flutter/bin:$PATH"
# servizi (entrambi attualmente in esecuzione):
~/.local/mailpit/mailpit --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025 &
(cd platform-backend && php artisan serve --port=8000) &
# suite:
(cd platform-backend && php artisan test)                       # 77/77
(cd platform-mobile/apps/client_app && flutter test)            # 28/28
# E2E completo con email reale:
(cd platform-mobile/apps/client_app && flutter test test/e2e --tags e2e \
  --dart-define=TENANT_KEY=<api_key> \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1)
# inbox di test: http://127.0.0.1:8025
```
