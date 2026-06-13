# 34 — Code Review Backend (Incremento 1)

Review del codice di `platform-backend` eseguita al termine dell'implementazione. 24 rilievi; stato: **[APPLICATO]** corretto nel codice, **[PIANIFICATO]** da fare in un incremento successivo con motivazione.

## Correttezza

1. **[APPLICATO]** `RefreshTokenService::rotate`: la revoca-famiglia su riuso avveniva dentro la transazione e veniva rollbackata dal throw — il token rubato restava valido. Scoperto dal feature test, revoca spostata fuori dalla transazione con gestione della race concorrente (`punishReuse`).
2. **[APPLICATO]** Cast Eloquent `date` su colonne calendario (`schedule_exceptions`, `*_schedules.valid_*`): serializzava `Y-m-d 00:00:00` rompendo i confronti su SQLite e forzando `DATE()` index-hostile su MySQL. Date pure trattate come stringhe `Y-m-d`.
3. **[APPLICATO]** `ManageAgendaController::index` filtrava per giorno UTC anziché giorno locale del tenant (±1-2h di errore ai confini giornata): ora converte la mezzanotte locale in range UTC.
4. **[APPLICATO]** `TransitionAppointment`: il calcolo della finestra di correzione no-show usava `diffInDays` con direzione ambigua; riscritto come `markedAt + 7gg isPast()`.
5. **[APPLICATO]** `JwtGuard` catturava la Request nel costruttore e memoizzava l'utente per istanza: identity leak tra richieste in runtime long-lived (worker/Octane/test). Ora request lazy + memoizzazione per-token.
6. **[APPLICATO]** Funzione globale `apiError()` in bootstrap: ridefinita al secondo boot (fatal in test, rischio in Octane). Sostituita da `ApiErrorResponse::make`.
7. **[APPLICATO]** `AuthenticationService::registerCustomer`: race tra check-exists e insert; il vincolo UNIQUE è la vera guardia, ora intercettato con risposta `email_taken` coerente.

## Sicurezza

8. **[APPLICATO]** `TenantRegistry::findByApiKey` usava l'header attacker-controlled come chiave cache in chiaro (memory-flooding di Redis con probe casuali): ora hashata SHA-256, con caching anche dei miss.
9. **[APPLICATO]** `EnsureUserType` con `UserType::from`: un typo nella route definition produceva 500 al primo hit; ora `tryFrom` con eccezione esplicativa.
10. **[PIANIFICATO]** Audit log sincrono su ogni login: a volume va spostato su coda (`bulk`); insert singolo accettabile fino a ~1000 tenant.
11. **[PIANIFICATO]** Endpoint cancellazione account in-app (obbligo store Apple) collegato al flusso GDPR erasure — modulo Compliance, incremento 2.
12. **[PIANIFICATO]** Restrizione di rete (allowlist/WAF) sulle route `/admin/*`: a livello infrastruttura (Terraform), non applicativo.

## Architettura / qualità

13. **[APPLICATO]** `AuthController::refresh` risolveva `JwtService` via service locator (`app()`): ora method injection.
14. **[PIANIFICATO]** `SendNotificationJob::channelChain` usa `app()` per i canali: estrarre un `ChannelResolver` iniettabile quando si aggiunge il canale SMS.
15. **[PIANIFICATO]** `ScheduleAppointmentNotifications` legge `BrandProfile` per ogni evento: cache del nome app nel TenantContext quando si misurerà il costo reale.
16. **[PIANIFICATO]** Test architetturali automatici (model tenant-bound ⇒ trait `BelongsToTenant`; vietato `withoutGlobalScope` fuori da `Platform\`): da aggiungere come suite `tests/Architecture` con regole statiche.
17. **[PIANIFICATO]** `GetAvailability::staffCandidates` genera un EXISTS per servizio della catena: fine fino a 5 servizi; rivisitare con il multi-staff sequenziale.
18. **[PIANIFICATO]** Contratto OpenAPI generato dalle route + FormRequest, come fonte per il client Dart (docs/22 §5).

## Performance / scalabilità

19. **[APPLICATO]** Rate limit hardcoded nel provider → spostati in `config/api.php` (override per ambiente, niente magic numbers).
20. **[PIANIFICATO]** `JwtService` rilegge il PEM dal filesystem a ogni richiesta: memoizzare per processo (micro-ottimizzazione, misurare prima).
21. **[PIANIFICATO]** `ManageStaffController::setSchedules` bumpa la cache per ogni giorno della booking window (60 iterazioni): sostituire con versione per-staff globale quando si osserverà pressione su Redis.
22. **[PIANIFICATO]** Indice parziale/copertura su `appointment_items (tenant, staff, starts_at) WHERE is_blocking=1`: MySQL non ha indici parziali — valutare indice composito con `is_blocking` in testa in fase di tuning su dati reali.

## Operatività

23. **[PIANIFICATO]** Middleware `X-Request-Id` + log strutturati JSON con `tenant_id` (docs/32 §7): incremento osservabilità.
24. **[PIANIFICATO]** `notifications:dispatch-due` gira ogni 15 min: portare a orario (docs/29) quando i job ritardati Redis saranno in produzione con Horizon; in sync/dev i 15 min sono la rete di sicurezza primaria.

## Esito

Dopo l'applicazione dei fix 1-9, 13, 19: **64 test, 216 assertion, tutti verdi**. I PIANIFICATI sono tracciati e non bloccanti per l'incremento 1.
