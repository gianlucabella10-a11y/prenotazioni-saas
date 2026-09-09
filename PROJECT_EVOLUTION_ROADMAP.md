# PROJECT_EVOLUTION_ROADMAP — Solo miglioramenti strutturali

> Nessuna nuova funzionalità proposta. Ogni voce qui è un miglioramento di organizzazione, processo, o robustezza strutturale — non di prodotto. Fonti: `REAL_PROJECT_STATE.md`, `PROJECT_FREEZE_STATE.md`, `PROJECT_STRUCTURE.md`, `PROJECT_CLEANUP_PLAN.md`, `PROJECT_DEPENDENCIES.md`, `PROJECT_STANDARD.md` (tutti prodotti in questa linea di audit).

## IMMEDIATE (giorni — rischio nullo, nessuna modifica al codice applicativo)

1. **Eseguire `PROJECT_CLEANUP_PLAN.md`**: 24 MOVE, 8 MERGE→3, ~38 ARCHIVE, 2 DELETE, sui file alla radice. Riduce i file alla radice da 77 a ~6 (i documenti di stato canonici + README).
2. **Creare `README.md` alla radice** — oggi assente. Punto d'ingresso minimo: cos'è il progetto, dov'è la documentazione (`docs/`), come avviarlo in locale (già coperto da `PREVIEW_ACCESS_GUIDE.md`, da linkare).
3. **Risolvere lo stato di lavoro non committato**: 19 file `.md` + 3 script in working tree mai committati, più 2 file di codice (`LocalBuildDispatcher.php`, `BuildPipelineTest.php`) con modifiche pendenti — decidere esplicitamente per ciascuno commit/archiviazione/scarto, non lasciarli a galleggiare nella working tree indefinitamente.
4. **Rimuovere le 17 `IMG_97xx.HEIC`** dal tracking Git (restano solo le derivate JPEG già ignorate in `.screens-analysis/`) — materiale di terze parti, ~3.1 MB di storia Git non necessaria.
5. **Aggiornare `docs/22-struttura-repository.md`** con una nota esplicita in testa che segnala lo scostamento dalla realtà implementata (documentato in `PROJECT_STRUCTURE.md` §0) — non riscriverlo, solo evitare che un lettore lo scambi per lo stato attuale.

## NEXT (settimane — rischio basso, richiede modifiche mirate al codice, non al business)

6. **Test di architettura** (`tests/Architecture/`, oggi assente nonostante un commento nel codice ne dichiari l'esistenza — vedi `REAL_PROJECT_STATE.md` §Fase 8): verifica automatica che (a) nessun modulo importi `Infrastructure` di un altro modulo, (b) ogni modello con `tenant_id` usi `BelongsToTenant` salvo eccezione documentata. Trasforma una convenzione oggi rispettata "per disciplina" in una garanzia strutturale.
7. **`config/cors.php` applicativo**, in sostituzione del default Laravel wide-open (`allowed_origins: ['*']`) oggi ereditato per assenza di configurazione propria (`PROJECT_FREEZE_STATE.md` §6).
8. **Unificare l'envelope di risposta API** a un solo pattern (oggi ne coesistono 3 — `JsonResource`, array grezzo, `{data:...}` fatto a mano — `PROJECT_FREEZE_STATE.md` §6) — migrazione incrementale controller per controller, nessun cambio di comportamento per i consumer esistenti se fatta con versione (vedi punto 10).
9. **Colmare la copertura test mancante** già identificata: 4 schermate Flutter senza test (`booking_services_screen`, `booking_schedule_screen`, `booking_success_screen`, `profile_screen`), il guard di routing (`app/router.dart`) mai testato, la pipeline di build reale (`flutter build apk` end-to-end) mai esercitata in nessun test.
10. **Consolidare i comandi CLI del cluster ambiente** (`ENVIRONMENT_GUIDE/SETUP/FINAL`, `REAL_BETA_ENVIRONMENT`) in un solo documento vivo, come da `PROJECT_CLEANUP_PLAN.md` §6 — prerequisito pratico prima di introdurre un vero ambiente di staging (punto 13).

## FUTURE (mesi — richiede decisioni architetturali esplicite, non solo esecuzione)

11. **Decisione esplicita su mono-repo vs multi-repo**: il piano originale (`docs/22`) prevedeva 3 repository separati per isolare i permessi su `platform-infra/` (segreti, chiavi di firma). Oggi è un mono-repo. Due esiti strutturalmente validi, entrambi da formalizzare (non lasciare implicito): (a) split reale in 3 repository con permessi Git separati, accettando il costo operativo che il piano originale aveva già scartato una volta; oppure (b) confermare il mono-repo come scelta definitiva e riscrivere `docs/22` di conseguenza, introducendo un controllo di accesso equivalente (es. CODEOWNERS restrittivo su `platform-infra/`) come compromesso pratico.
12. **Rendere il driver di build `local` esplicitamente "solo sviluppo"**: l'accoppiamento filesystem (`platform-backend` esegue `flutter build` assumendo `platform-mobile` come cartella sibling — `PROJECT_DEPENDENCIES.md` §3.3/§7) è incompatibile con qualunque scenario multi-macchina o multi-repository. Il driver `github` va promosso a standard per ogni build reale (beta o produzione); `local` resta solo per iterazione rapida su una singola macchina di sviluppo.
13. **Introdurre un ambiente di staging** (`platform-infra/terraform/envs/staging`, come originariamente pianificato in `docs/22` §4) distinto dall'attuale singolo ambiente "pilota" — precondizione per il percorso di promozione locale→staging→produzione descritto in `PROJECT_STANDARD.md` §8.
14. **Wiring reale versione↔APK**: far sì che `--build-name`/`--build-number` passati a `flutter build` corrispondano davvero a `app_builds.version`/`app_versions` (oggi scollegati, `PROJECT_FREEZE_STATE.md` §8) — precondizione tecnica perché il versionamento SemVer descritto in `PROJECT_STANDARD.md` §10 sia più che una regola sulla carta.
15. **Firma di release non fallback-silenziosa**: la build di release deve fallire esplicitamente se le variabili del keystore non sono presenti, non ripiegare sulla firma debug (`PROJECT_FREEZE_STATE.md` §8) — cambiamento strutturale alla robustezza della pipeline, non una feature.
16. **Contratto OpenAPI come fonte di verità dell'API** (previsto in `docs/22` §5, mai realizzato — `REAL_PROJECT_STATE.md` conferma NOT FOUND) — formalizza il contratto oggi implicito nei controller e abilita generazione automatica di client/documentazione.

## LONG TERM (trimestri — cambiamenti fondazionali, da rivalutare quando le condizioni che li giustificano si presentano davvero)

17. **Scalabilità infrastrutturale reale** (già descritta in dettaglio in `REAL_PROJECT_STATE.md` §Fase 9): passaggio da 1 istanza EC2 + 1 RDS micro a una topologia con autoscaling, read replica, coda su Redis/SQS, storage S3 realmente popolato con CDN — da attivare quando il numero di tenant/traffico lo giustifica economicamente, non preventivamente.
18. **Isolamento di build reale a scala** (rilevante solo se/quando si avvicina lo scenario "100 build contemporanee" descritto in `PROJECT_DEPENDENCIES.md` §7): infrastruttura di build dedicata, separata dal server applicativo, con isolamento per-build (non condivisione della stessa cartella `platform-mobile/apps/client_app/build/` tra build concorrenti).
19. **Processo ADR (Architecture Decision Records)** in `platform-infra/docs/` — previsto in `docs/22` §5, mai realizzato. Da introdurre quando il ritmo di decisioni architetturali giustifica un registro formale (oggi le decisioni vivono solo nei ~77 file di sessione oggetto di questo audit — un ADR avrebbe reso molte di quelle sovrapposizioni evitabili fin dall'inizio).
20. **Rivalutazione periodica di questo stesso set di documenti** (`PROJECT_STRUCTURE.md`, `PROJECT_STANDARD.md`, ecc.): senza un proprietario e una cadenza di revisione, questi 5 documenti rischiano di ripetere esattamente il pattern di obsolescenza silenziosa descritto in `PROJECT_CLEANUP_PLAN.md` per i loro predecessori. Raccomandazione: revisione trimestrale, aggiornamento in-place (mai un "PROJECT_STRUCTURE_V2.md").

---

Nessun punto di questa roadmap introduce, rimuove o modifica una funzionalità di prodotto. Ogni punto è organizzazione, processo, robustezza, o debito tecnico strutturale.
