# TECHNICAL_ROADMAP — Solo tecnica, verso v2.0

> "v2.0" qui definito come: chiusura del debito tecnico 🔴/🟡 esistente + stadio "Production Ready" completo (`PROJECT_MATURITY.md`) — non un insieme di nuove funzionalità di prodotto. Nessuna voce di roadmap commerciale.

## Done

- ✅ Multi-tenancy isolata a 4 livelli di difesa, testata (`TenantIsolationTest`, `WhiteLabelIsolationTest`, `DashboardSecurityTest`).
- ✅ Booking engine con lock di concorrenza, idempotenza, gestione DST.
- ✅ Autenticazione JWT custom + MFA TOTP + verifica email, su 3 guard distinti.
- ✅ White Label Engine (asset pipeline, manifest, config runtime) completo e testato.
- ✅ App Factory: identità app, generazione pacchetto, build reale (driver `local`), distribuzione beta.
- ✅ CI funzionante (test automatici backend+mobile su ogni push/PR).
- ✅ Documentazione riorganizzata da 82 file sparsi a un sistema categorizzato con entry point unico (questa linea di sessioni).
- ✅ Audit completo di naming, dipendenze, codice morto, responsabilità per cartella (questa sessione).
- ✅ Quality gate verificato: **207/207 test backend verdi** (`php artisan test`, eseguito in questa sessione).

## In Progress

- 🔶 Standard di naming dichiarati (`NAMING_GUIDELINES.md`) ma non ancora applicati alle 2 incoerenze note (`Http/` vs `Presentation/Controllers`, suffisso `Command` su un solo comando) — corretti "alla prossima modifica di quel file", non retroattivamente.
- 🔶 Registro di debito tecnico completo e prioritizzato (`TECHNICAL_DEBT.md`) — la documentazione del debito è completa, la sua risoluzione no.
- 🔶 Standard operativi scritti (`OPERATING_PROCEDURES.md`) ma non ancora esercitati in un incidente reale (in particolare SOP-08 Ripristino, mai testata end-to-end).

## Next (settimane, non richiede decisioni architetturali)

1. Fail-fast sulla firma release invece del fallback silenzioso a debug (`TECHNICAL_DEBT.md` #1).
2. `config/cors.php` esplicito (#9).
3. Versioning APK reale, sincronizzato con `app_builds.version` (#2).
4. Test di architettura per i confini tra moduli e l'uso obbligatorio di `BelongsToTenant` (#4).
5. Backup schedulato automatico + alert se non gira (`AUTOMATION_CATALOG.md` #1-2).
6. Copertura test mancante: 4 schermate Flutter, guard di routing, pipeline di build end-to-end (#11-12).
7. Esecuzione di un ripristino di prova documentato (chiude il gap "In Progress" su SOP-08).

## Future (richiede una decisione, non solo esecuzione)

1. Ambiente di staging distinto dalla produzione (`PROJECT_EVOLUTION_ROADMAP.md` #13).
2. Decisione esplicita mono-repo vs. multi-repo (#11) — precondizione per rendere il driver `local` non più necessario in nessun contesto.
3. Contratto OpenAPI come fonte di verità dell'API (#16).
4. Layer `Policies` per autorizzazione granulare, se il numero di ruoli/permessi cresce oltre i 4 attuali.
5. Introduzione di Redis (cache + coda) in vista della scala 10.000 clienti (`SCALABILITY_REPORT.md`).
6. Vista Control Room per i 3 gap ESSENZIALI (`CONTROL_ROOM_ROADMAP.md`): terminazione/archiviazione cliente, audit log, backup da UI.

## Blocked (non eseguibile senza una risorsa/decisione esterna)

| Voce | Bloccata da |
|---|---|
| Push notification realmente funzionanti su build reali | File di configurazione Firebase nativi (`google-services.json`/`GoogleService-Info.plist`) — richiedono accesso alla console Firebase, non è un blocco di codice |
| Collegamento `AnalyticsService` a un provider reale | Decisione di prodotto su quale provider (Firebase Analytics, altro) — non tecnica |
| Fatturazione | Decisione di business se/come integrarla — fuori scope tecnico puro |
| Split mono-repo → 3 repository | Decisione organizzativa sulla dimensione del team e sul modello di permessi — non eseguibile unilateralmente da un solo intervento tecnico |
| Scalabilità a 10.000+ clienti (Redis, read replica, CDN, sharding) | Investimento infrastrutturale che richiede budget/decisione, non solo tempo di sviluppo |

## Verso v2.0 — criterio di completamento

v2.0 è raggiunta quando: (a) tutte le voci "Next" sono chiuse, (b) almeno 2 delle 6 voci "Future" sono state affrontate con una decisione esplicita (anche "no, non ora" è una decisione valida — l'importante è che non resti implicita), (c) nessuna voce "Blocked" è rimasta bloccata per più di un ciclo di pianificazione senza un piano per sbloccarla. Corrisponde, in `PROJECT_MATURITY.md`, al completamento pieno dello stadio "Release Candidate" e all'ingresso in "Production Ready".
