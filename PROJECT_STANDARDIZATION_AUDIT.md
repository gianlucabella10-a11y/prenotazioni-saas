# PROJECT_STANDARDIZATION_AUDIT — Duplicati, file morti, residui

> Audit a grana più fine di `PROJECT_STRUCTURE_AUDIT.md` (che valuta aree intere) — qui si scende a livello di singolo file/classe/migration/config. Verificato con lettura diretta e controlli incrociati (grep di ogni riferimento nel codice), non per assunzione. Nessun file eliminato o modificato.

**Legenda**: 🟢 GREEN = usato, verificato. 🟡 YELLOW = da verificare con un umano (ambiguo o dipendente da contesto che non posso confermare da solo). 🔴 RED = probabilmente eliminabile, motivato.

## Cartelle duplicate

🟢 **Nessuna trovata.** Struttura a modulo (`app/Modules/<Nome>/{Domain,Application,Infrastructure,Http}`) senza sovrapposizioni; `lib/features/<nome>/{data,domain,presentation}` idem. La sola area che aveva duplicazione reale era la documentazione alla radice (82 file), già risolta nella sessione di consolidamento precedente (`PROJECT_CLEANUP_PLAN.md`).

## File inutilizzati / obsoleti (codice)

| File | Stato | Verifica |
|---|---|---|
| `resources/views/welcome.blade.php` | 🔴 | Vista di default di Laravel (scaffold), **nessun controller la richiama** (`grep -r "view('welcome'" app/` → zero risultati). Boilerplate mai personalizzato né rimosso. |
| `lib/core/analytics/analytics_service.dart` (Flutter) | 🔴 | Classe completa e testata, ma **mai istanziata** in `app/providers.dart` o in nessuna schermata (verificato in `PROJECT_FREEZE_STATE.md` §3/§7) — irraggiungibile a runtime, esercitata solo dal proprio test unitario. |
| `app/Console/Commands/{BuildMatrix,DispatchDueNotifications,GenerateJwtKeys,CreateControlRoomAdmin}.php` | 🟢 | Un primo grep "nessun riferimento al nome classe altrove" li segnalava come sospetti — **falso positivo**: sono comandi Artisan, invocati per **signature stringa** (`app:build-matrix`, `notifications:dispatch-due`, ecc.), non per nome di classe. Verificati singolarmente: `DispatchDueNotifications` è nello scheduler (`routes/console.php`), `BuildMatrix` è chiamato dalla CI via SSH, `GenerateJwtKeys` da `deploy.sh`, `CreateControlRoomAdmin` è il comando di bootstrap documentato. Tutti realmente in uso. |
| `GithubBuildDispatcher.php` / `LogBuildDispatcher.php` | 🟡 | Referenziati e istanziati correttamente in `RunAppBuildJob` (risoluzione dinamica da `config('app_factory.build_driver')`) — non morti. **Da verificare**: nessun test nel repository esercita realmente il driver `github` end-to-end (solo `manual`/`local` sono coperti dai test letti finora) — non è detto sia rotto, ma non è verificato quanto `local`. |

## Documentazione duplicata / markdown ripetuti

🟢 **Già risolto nella sessione precedente.** `PROJECT_CLEANUP_PLAN.md` documenta ogni caso trovato: 1 duplicato letterale (`FINAL_TECHNICAL_FREEZE_REPORT.md`, auto-dichiarato identico a `FINAL_PRODUCT_READINESS_AUDIT.md`), 3 cluster quasi-duplicati consolidati (ambiente, runbook provisioning, test su device). Nessun nuovo duplicato trovato in questa seconda passata.

## Vecchie prove / file temporanei

| Elemento | Stato | Verifica |
|---|---|---|
| 27 file `.DS_Store` sparsi in tutto il repository (radice, `docs/`, dentro `platform-backend/app/...`, dentro `platform-mobile/.../ios`/`android`) | 🟡 | Cruft macOS, **nessuno tracciato in git** (confermato — `.gitignore` li esclude globalmente), quindi non è un rischio di repository, solo disordine sul filesystem locale. Non richiede azione urgente; pulizia locale con `find . -name .DS_Store -delete` è sicura ma non eseguita in questa fase (vincolo: nessuna eliminazione). |
| `platform-backend/storage/logs/{laravel,scheduler,worker}.log` | 🟢 | Log di sviluppo reali, correttamente `.gitignore`d, non tracciati — comportamento atteso, non un problema. |
| 17 immagini `IMG_97xx.HEIC` (prove di riferimento per `SCREEN_ANALYSIS.md`) | 🟢 | Già spostate in `docs/archive/reference-assets/` nella sessione precedente. |

## Migration inutilizzate

🟢 **Nessuna trovata.** 23 file, ognuno crea una tabella nuova o altera una tabella esistente in modo incrementale e coerente con lo storico (verificato in `REAL_PROJECT_STATE.md` §Fase 4) — nessuna coppia di migration che crea la stessa tabella, nessuna migration "orfana" che altera una tabella mai creata.

## Classi mai utilizzate / helper morti

| Classe/helper | Stato |
|---|---|
| `AnalyticsService` (Flutter) | 🔴 — vedi sopra |
| Tutte le classi `Application/`, `Domain/`, `Infrastructure/Models/` nei 10 moduli backend | 🟢 — ognuna raggiunta da almeno una route, un comando, o un altro use case, verificato nelle sessioni di audit precedenti (route table completa in `REAL_PROJECT_STATE.md`) |
| Helper Blade (`_status.blade.php` partial in Control Room) | 🟢 — richiamato da `tenants/index.blade.php`/`show.blade.php` (`@include`) |

## Configurazioni duplicate

🟢 **Nessuna trovata.** 17 file in `config/`, uno per dominio (`app_factory.php`, `booking.php`, `branding.php`, `jwt.php`, ecc.), nessuna chiave ripetuta tra file diversi nei file letti finora. **Gap diverso, non duplicazione**: `config/cors.php` **non esiste affatto** (eredita il default Laravel wide-open) — assenza, non duplicazione, già tracciata in `PROJECT_EVOLUTION_ROADMAP.md` punto 7.

## Riepilogo

| Verdetto | Conteggio |
|---|---|
| 🟢 GREEN | Struttura moduli, migration, config, comandi artisan, view Blade, dispatcher build — la stragrande maggioranza del repository |
| 🟡 YELLOW | `.DS_Store` sparsi (cosmetico, non tracciato), driver `github` non esercitato da test quanto `local` |
| 🔴 RED | `welcome.blade.php` (scaffold mai personalizzato/rimosso), `AnalyticsService` Flutter (codice morto, mai wired) |

Solo 2 elementi RED in tutto il repository, entrambi minori e a basso rischio (non toccano dati, non toccano sicurezza). Non è stata eseguita alcuna eliminazione, come da vincolo.
