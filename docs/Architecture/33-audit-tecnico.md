# 33 — Audit Tecnico Completo (Fase 2)

Audit della progettazione tecnica (documenti 20-32), condotto dopo la stesura. 56 criticità numerate, per area, ciascuna con gravità, impatto e correzione. Stato: **[CORRETTA]** = il disegno nei documenti 20-32 già incorpora la correzione (l'audit è stato iterativo); **[RECEPITA]** = correzione definita qui e da applicare in progettazione esecutiva; **[MONITORATA]** = rischio accettato con indicatore.

## A. Architettura generale (21)

1. **Scheduler ECS come singleton** — ALTA — due task scheduler concorrenti duplicherebbero i job. → Lock distribuito Redis sul tick dello scheduler (`onOneServer` pattern) + task count = 1 con alarm. [CORRETTA in 21/29]
2. **Deploy rolling con job in esecuzione** — MEDIA — un deploy può uccidere worker a metà job. → Graceful shutdown: i worker terminano il job corrente prima dello stop (timeout drain 60s); job idempotenti per design. [RECEPITA]
3. **Dipendenza da una sola regione AWS** — MEDIA — disastro regionale = downtime ore. → Accettato per fasi 0-2 (costo multi-region ingiustificato); snapshot cross-region + runbook restore ([32](32-qualita-aws.md) §4). [MONITORATA]
4. **WAF può bloccare traffico legittimo mobile** — BASSA — falsi positivi su payload insoliti. → Regole in count-mode prima dell'enforcement; eccezioni documentate. [RECEPITA]
5. **Nessun API gateway / BFF dedicato** — BASSA — un solo backend serve 3 superfici. → Accettato: scope per superficie nel JWT + route group separati; un BFF si valuta solo se le superfici divergono molto. [MONITORATA]
6. **Webhooks billing senza coda di recupero** — ALTA — un webhook perso = stato abbonamento divergente. → Idempotenza su event id + riconciliazione periodica via API del gateway (pull di verifica giornaliero). [CORRETTA in 25 §6 + RECEPITA per il pull]
7. **Config endpoint per tenant sospesi** — MEDIA — se risponde 403, l'app non può mostrare la schermata di cortesia. → Il config endpoint risponde sempre con payload minimo e stato. [CORRETTA in 27 §7]

## B. Database (24)

8. **UNIQUE(tenant_id, staff_member_id, starts_at_utc) non copre gli overlap parziali** — CRITICA — due appuntamenti con start diversi possono sovrapporsi. → Il vincolo è dichiarato esplicitamente come *rete di sicurezza per start identici*; gli overlap sono prevenuti dal `SELECT ... FOR UPDATE` in transazione ([30](30-engine-appuntamenti.md) §4). Test di concorrenza dedicati in CI. [CORRETTA]
9. **UNIQUE su colonne nullable (customers email/phone)** — MEDIA — MySQL permette NULL multipli: ok, ma duplicati con email vuota-stringa sfuggono. → Normalizzazione: stringa vuota → NULL a livello applicativo; vincolo verificato nei test. [RECEPITA]
10. **Il vincolo UNIQUE sugli item include anche appuntamenti cancellati** — ALTA — uno slot cancellato bloccherebbe la riprenotazione allo stesso start. → Colonna `is_blocking` (derivata dallo stato) inclusa nel vincolo: UNIQUE(tenant_id, staff_member_id, starts_at_utc, is_blocking) con is_blocking NULL per i non bloccanti (NULL esce dall'unicità). [CORRETTA in 30 §4, dettaglio qui]
11. **BINARY(16) uuid senza ordinamento temporale** — BASSA — UUIDv4 random degrada gli indici secondari. → UUIDv7 (time-ordered). [RECEPITA]
12. **Soft delete + UNIQUE confliggono** — MEDIA — un servizio cancellato e ricreato con lo stesso nome viola eventuali UNIQUE. → Nessun UNIQUE su campi nome; unicità solo logica in validazione. [RECEPITA]
13. **`no_show_count` denormalizzato può divergere** — BASSA — → Ricalcolo nel job di riconciliazione notturno; la fonte di verità sono gli appointment_events. [RECEPITA]
14. **JSON abusabile (settings, theme, segment)** — MEDIA — JSON non validato = bug silenti. → JSON Schema di validazione applicativa per ogni colonna JSON, versionato. [RECEPITA]
15. **Partizionamento MySQL incompatibile con FK** — MEDIA — MySQL non supporta FK su tabelle partizionate. → audit_logs e notifications (candidate al partizionamento) progettate **senza FK fisiche** (integrità applicativa), dichiarato qui come scelta. [RECEPITA]
16. **Crescita appointment_events non stimata** — BASSA — ~4-6 righe per appuntamento: a 60M appuntamenti/anno ≈ 300M righe. → Stessa politica di partizionamento/archiviazione di audit_logs. [RECEPITA]
17. **Mancanza di indice per la riconciliazione promemoria** — BASSA — → Indice (status, scheduled_for_utc) già previsto su notifications. [CORRETTA in 24]
18. **Charset e collation non fissate per ricerche case-insensitive** — BASSA — → `utf8mb4_0900_ai_ci` esplicita; ricerche nome cliente accent-insensitive. [RECEPITA]

## C. Multi-tenant (28)

19. **Global scope Eloquent bypassabile (withoutGlobalScope)** — ALTA — uno sviluppatore può disattivare lo scope. → Uso di `withoutGlobalScope` vietato fuori da namespace `Platform\`; regola di static analysis in CI che lo fa fallire altrove. [CORRETTA in 28 (test architettura), dettaglio qui]
20. **Comandi artisan/console senza TenantContext** — ALTA — un comando di manutenzione può toccare tutti i tenant per errore. → I comandi cross-tenant devono usare un'API esplicita `forEachTenant()` con logging; accesso diretto ai model tenant-bound fuori da quel blocco solleva la guard. [CORRETTA in 28 §2]
21. **Cache key senza tenant prefix per errore umano** — MEDIA — → Repository di cache unico che impone il prefisso; uso diretto del facade Cache su dati tenant vietato da convenzione + revisione. [CORRETTA in 28 §2]
22. **TTL 5 min sulla sospensione tenant** — BASSA — un tenant sospeso può operare fino a 5 min. → Invalidazione esplicita della cache alla transizione di stato (evento), TTL è solo fallback. [CORRETTA in 28 §3]
23. **Test isolamento non copre le query di reporting/aggregati** — MEDIA — i report sono il punto classico di leak (GROUP BY senza tenant). → La suite TenantIsolation include esplicitamente gli endpoint report/export. [RECEPITA]
24. **Enumerazione cross-tenant via uuid noti** — MEDIA — → 404 (non 403) su risorse di altri tenant, già da convenzione ([28](28-multi-tenant-tecnico.md) §2 livello 5). [CORRETTA]
25. **Backfill/migrazioni dati dimenticano i tenant sospesi** — BASSA — → `forEachTenant()` include per default tutti gli stati salvo `terminated`; opt-out esplicito. [RECEPITA]

## D. Autenticazione/Autorizzazione (26)

26. **Refresh token in cookie per web ma API unica** — MEDIA — mescolare bearer e cookie apre a CSRF sulla dashboard. → Dashboard web usa sessione server-side Laravel classica (cookie + CSRF token), il JWT è solo per le app mobile; il disegno 26 §3 è precisato in questo senso. [RECEPITA — correzione del disegno]
27. **Denylist Redis dei jti non persistente** — MEDIA — un riavvio Redis svuota la denylist. → AOF su Redis + alla failover: finestra residua max 15 min (vita access token), rischio accettato e documentato. [MONITORATA]
28. **Social login Apple richiede gestione revoca token** — BASSA — policy Apple: va gestita la revoca account. → Endpoint di cancellazione account in app (requisito store) collegato al flusso GDPR erasure. [RECEPITA]
29. **OTP SMS = costo e vettore di abuso (SMS pumping)** — ALTA — bot che fanno richieste OTP su numeri premium. → Rate limit aggressivo per numero/IP/tenant, allowlist prefissi paese del mercato attivo, monitoraggio spesa SMS con kill-switch. [RECEPITA]
30. **Impersonificazione: accesso a note cliniche** — ALTA — il supporto non deve vedere dati sanitari. → Le sessioni di impersonificazione hanno scope ridotto: mai accesso a `customer_notes` clinical; deroga solo con consenso esplicito registrato del tenant. [RECEPITA]
31. **MFA obbligatoria può bloccare l'onboarding del barbiere medio** — MEDIA — attrito per utenti non tecnici. → MFA al primo login con setup guidato e recovery codes scaricabili; CS può resettare MFA con verifica identità (procedura runbook). [RECEPITA]
32. **Permessi granulari staff in JSON senza schema** — BASSA — → Set chiuso di capability versionate (enum), non JSON libero. [RECEPITA]

## E. Engine appuntamenti (30)

33. **`SELECT ... FOR UPDATE` su range può lockare troppo (gap lock)** — ALTA — REPEATABLE READ + range scan = gap lock e deadlock sotto carico. → Lock mirato: query sull'indice (tenant, staff, starts_at_utc) ristretta alla giornata; ordine di lock deterministico; retry automatico su deadlock (1 retry); test di carico concorrente in CI. Valutare `READ COMMITTED` per la transazione di booking. [RECEPITA — dettaglio esecutivo]
34. **Cache availability con invalidazione per prefisso è O(N) su Redis** — MEDIA — `KEYS`/`SCAN` per pattern delete è costoso. → Versione per scope: chiave `t:{tid}:avail:ver:{staff}:{date}` incrementata all'evento; le chiavi cache includono la versione (invalidazione O(1), le vecchie scadono da sole). [RECEPITA — sostituisce il pattern delete]
35. **Soft-hold Redis non considerato nel calcolo slot** — BASSA — due utenti vedono lo stesso slot, uno ha l'hold. → Gli hold attivi vengono sottratti dalla disponibilità mostrata (best effort). [RECEPITA]
36. **DST: appuntamento prenotato a cavallo del cambio ora** — MEDIA — slot 02:30 nel giorno in cui le 02:30 non esistono. → L'espansione per-giorno in timezone locale genera solo orari esistenti; test dedicati sui 4 giorni DST europei. [CORRETTA in 30 §2 + test da scrivere]
37. **Auto-release delle richieste `requested` scadute non disegnato** — BASSA — → Job ritardato alla creazione della richiesta (stesso pattern dei promemoria). [RECEPITA]
38. **Riprogrammazione: race tra cancellazione vecchio slot e booking nuovo** — MEDIA — → La reschedule è un'unica transazione (lock su entrambi gli intervalli, ordine deterministico per evitare deadlock). [RECEPITA]
39. **Walk-in/manuali dello staff senza customer registrato** — BASSA — lo staff prenota per "Mario" senza scheda. → `customers.source=staff` con soli nome/telefono: già supportato dal modello dati. [CORRETTA in 24]
40. **Granularità 15 min hardcoded nella cache key (duration_bucket)** — BASSA — → bucket = durata composta arrotondata alla granularità tenant: documentato come parte della chiave. [CORRETTA in 30 §3]

## F. White label & build pipeline (27)

41. **Policy Apple 4.2.6: build per-tenant dall'account piattaforma a rischio rigetto sistemico** — CRITICA — è il rischio singolo più alto del progetto. → Già rivista la strategia: iOS su account del tenant con onboarding assistito ([27](27-white-label-tecnico.md) §6); validazione con 2-3 tenant pilota su App Store **prima** di promettere iOS nel contratto standard (gate di Fase 0); fallback commerciale: Android nativo + web di cortesia. [CORRETTA + MONITORATA]
42. **Gestione di centinaia di keystore Android / certificati** — ALTA — perdita keystore = impossibile aggiornare l'app. → Play App Signing (Google custodisce la chiave di firma; la piattaforma tiene solo upload key, rigenerabile); per iOS: API App Store Connect con chiavi per-tenant in Secrets Manager, inventario e rotazione automatizzati. [RECEPITA]
43. **Limite app per progetto Firebase** — MEDIA — già indirizzato con pool di progetti ([29](29-notifiche-tecnico.md) §4); aggiungere provisioning automatizzato dei progetti con quota monitoring. [CORRETTA]
44. **Treno di rilascio: 10.000 rebuild per aggiornamento Flutter** — ALTA — → Thin shell riduce la frequenza necessaria (3-4/anno); rollout per fasce con halt automatico su crash rate; budget runner stimato in [32](32-qualita-aws.md) §3. Resta il costo: valutare in Fase 3 il limite "aggiorniamo solo le app di tenant attivi" (le app di tenant churned non si aggiornano). [RECEPITA]
45. **Snapshot config compilato nel binario può divergere a lungo termine** — BASSA — → Lo snapshot è solo fallback primo-avvio; un banner "aggiornamento contenuti" forza il refresh; scadenza dello snapshot dopo 30 giorni (richiede rete). [RECEPITA]
46. **Versione minima API per app vecchie mai aggiornate** — MEDIA — tenant churned-rientrati o utenti con app di 2 anni. → `min-version` endpoint con blocking update; le API v1 garantite 12 mesi dopo deprecazione ([25](25-api-rest.md) §2). [CORRETTA]
47. **Generazione icone da logo: qualità non garantita automaticamente** — MEDIA — un logo a bassa risoluzione produce icone scadenti su tutto il parco. → Validazione bloccante risoluzione minima + anteprima obbligatoria + approvazione umana del CS al primo build (gate qualità, collegato al KPI branding di [01-prd.md](01-prd.md)). [RECEPITA]
48. **Nome app duplicato tra tenant sugli store** — BASSA — due "Barber Style" → rigetto o confusione. → Verifica di unicità interna + suffisso località suggerito in wizard. [RECEPITA]

## G. Notifiche (29)

49. **Code Redis: perdita job su failover ElastiCache** — ALTA — i job ritardati (promemoria) vivono solo in Redis per ore/giorni. → Doppia protezione: outbox/DB come fonte di verità (notifications.scheduled_for_utc) + job di riconciliazione che ri-accoda i mancanti ([29](29-notifiche-tecnico.md) §3,7). Con questa rete, Redis può perdere dati senza perdere promemoria. [CORRETTA]
50. **Promemoria schedulati oltre l'orizzonte pratico delle code** — MEDIA — appuntamento prenotato con 60 giorni di anticipo = job delayed 59 giorni in Redis (fragile). → I job ritardati si usano solo entro 48h; oltre, la riga `notifications(scheduled)` viene promossa a job da uno sweep orario. Disegno aggiornato: **ibrido DB-scheduled + delayed jobs**. [RECEPITA — correzione del disegno]
51. **Quiet hours non previste** — BASSA — promemoria delle 2:00 per appuntamento delle 8:00 in tenant con soglia 6h. → Finestra di rispetto (default 21:00-08:00 locale customer): l'invio slitta al mattino, mai oltre l'orario dell'appuntamento. [RECEPITA]
52. **Campagne: invio durante sospensione tenant** — BASSA — → Gli worker verificano lo stato tenant all'esecuzione (guard standard dei job). [CORRETTA in 28]

## H. GDPR/Compliance (26/24)

53. **Erasure GDPR vs idempotency key e audit** — MEDIA — l'anonimizzazione non deve rompere vincoli né cancellare l'audit (obbligo di accountability). → Pseudonimizzazione: i campi personali sono sostituiti, le righe restano; audit_logs conservano riferimenti a id pseudonimizzati; export S3 con scadenza automatica 7 giorni. [RECEPITA]
54. **Backup contengono dati cancellati** — MEDIA — il diritto all'oblio non si applica retroattivamente ai backup, ma serve policy. → Documentare: i backup scadono in 30 giorni (retention); in caso di restore, ri-applicazione del log delle erasure post-snapshot (tabella `gdpr_requests` come registro). [RECEPITA]
55. **Data residency UE** — BASSA — → Regione AWS UE, SES UE, S3 UE; Firebase FCM trasferisce metadati extra-UE: va citato nell'informativa (i payload push non contengono dati sensibili — solo riferimenti, il contenuto si carica via API). Push "data minimization by design". [RECEPITA]

## I. Qualità/Costi (32)

56. **Stime di costo non includono ambiente staging né CI** — BASSA — → Aggiungere ~25-30% alle stime di [32](32-qualita-aws.md) §3 per staging ridotto + CI; nota recepita qui come correzione alle tabelle. [RECEPITA]

---

## Riesame finale delle scelte (sintesi)

| Scelta | Confermata? | Note dal riesame |
|---|---|---|
| Monolite modulare Laravel | ✅ | Nessuna criticità emersa richiede microservizi; i confini DDD preservano l'opzione |
| Singolo MySQL + tenant_id | ✅ | I numeri (§2 di [32](32-qualita-aws.md)) confermano ampio margine; lo sharding resta opzione remota |
| JWT 15min + refresh rotation | ✅ con correzione | Dashboard web passa a sessione classica (criticità 26) |
| Thin shell + config runtime | ✅ | È la decisione che rende il modello sostenibile a 10k tenant |
| iOS su account tenant | ✅ | Unica strategia conforme alle policy Apple per template apps; da validare in Fase 0 (criticità 41) |
| Job ritardati per promemoria | ✅ con correzione | Ibrido DB-scheduled + delayed entro 48h (criticità 50) |
| Cache availability con invalidazione | ✅ con correzione | Versioned keys invece di pattern delete (criticità 34) |
| Lock pessimistico booking | ✅ con attenzione | Gap lock da gestire in esecutivo con test di carico (criticità 33) |

**Esito**: la progettazione tecnica (documenti 20-32), integrata con le 56 correzioni di questo audit, è pronta per la fase esecutiva (setup repository, definizione contratto OpenAPI di dettaglio, primi spike su: transazione di booking sotto carico, pipeline build iOS con account tenant pilota, pool progetti Firebase). Le criticità 33, 41 e 50 sono i tre rischi da validare con spike **prima** di impegni contrattuali su larga scala.

**Nessun codice applicativo è stato prodotto: tutti i deliverable sono progettazione tecnica e architetturale, in conformità al mandato.**
