# TECHNICAL_DEBT — Registro completo, senza omissioni

Ogni voce è verificata sul codice (non ipotizzata), con priorità e stima di tempo. Le stime sono ordini di grandezza per uno sviluppatore che già conosce quest'area del codice — non un impegno vincolante. Nessuna di queste voci è stata corretta in questa sessione (vincolo esplicito: solo documentazione).

**Priorità**: 🔴 Alta (rischio concreto o blocco per la crescita) · 🟡 Media (limita funzionalità o affidabilità) · 🟢 Bassa (migliorabile, non urgente).

## Bug noti

| # | Descrizione | Evidenza | Priorità | Stima |
|---|---|---|---|---|
| 1 | La build Android col driver `local` firma in **debug**, non release, se le variabili d'ambiente del keystore non sono impostate — nessun errore esplicito, fallback silenzioso | `PROJECT_FREEZE_STATE.md` §8, `build.gradle.kts` | 🔴 | 1-2 giorni (aggiungere controllo esplicito che fallisce la build invece di ripiegare) |
| 2 | `versionCode`/`versionName` dell'APK sono statici (`pubspec.yaml`), identici per ogni tenant e ogni build, nonostante due tabelle DB (`app_builds.version`, `app_versions`) li tracciassero separatamente | `PROJECT_FREEZE_STATE.md` §8 | 🔴 | 2-3 giorni (passare `--build-name`/`--build-number` reali a `flutter build`) |
| 3 | `AnalyticsService` (Flutter) è completo e testato ma **mai istanziato** — nessun evento di prodotto viene raccolto oggi | `PROJECT_STANDARDIZATION_AUDIT.md` | 🟡 | 1 giorno (wiring a un provider reale, o rimozione se non serve più) |

## Debito tecnico architetturale

| # | Descrizione | Evidenza | Priorità | Stima |
|---|---|---|---|---|
| 4 | Nessun test di architettura impone i confini tra moduli né l'uso obbligatorio di `BelongsToTenant` — il codice **dichiara** un test che non esiste | `REAL_PROJECT_STATE.md` §Fase 8 | 🟡 | 2-3 giorni |
| 5 | `User` (autenticazione) non ha lo scope di tenancy automatico — isolamento a convenzione manuale in ogni punto di chiamata | `REAL_PROJECT_STATE.md` §Fase 8 | 🟡 | 1 giorno per un test di regressione mirato; non risolvibile con lo scope automatico senza refactoring (fuori scope per una semplice correzione) |
| 6 | `LocalBuildDispatcher` assume che `platform-mobile` sia una cartella sibling sullo stesso filesystem del backend — rompe l'assunzione di repository separati | `PROJECT_DEPENDENCIES.md` §3.3 | 🟢 | Non risolvibile a basso costo — richiede decisione su mono-repo vs multi-repo (vedi `PROJECT_EVOLUTION_ROADMAP.md` #11) |
| 7 | 3 pattern di risposta API coesistono (`JsonResource`, array grezzo, `{data:...}` a mano) — nessun envelope unificato | `PROJECT_FREEZE_STATE.md` §6 | 🟡 | 1-2 settimane (migrazione incrementale, controller per controller) |
| 8 | Nessun layer `Policies` — l'autorizzazione è tutta a guard/middleware, non granulare per risorsa | `PROJECT_FREEZE_STATE.md` §6 | 🟢 | ~1 settimana se si decide di introdurlo — oggi funziona, è un miglioramento di granularità non un bug |

## Sicurezza (debito, non vulnerabilità attiva sfruttabile nota)

| # | Descrizione | Evidenza | Priorità | Stima |
|---|---|---|---|---|
| 9 | Nessun `config/cors.php` applicativo — eredita il default Laravel wide-open (`allowed_origins: ['*']`) | `PROJECT_FREEZE_STATE.md` §6 | 🔴 | 0.5 giorni |
| 10 | MFA per il Control Room è opzionale per riga (`users.mfa_enforced`), non invariante di sistema — un super-admin creato fuori dal comando ufficiale potrebbe non averla mai | `PROJECT_FREEZE_STATE.md` §5 | 🟡 | 1 giorno (validazione a livello di guard/middleware) |

## Test mancanti

| # | Descrizione | Evidenza | Priorità | Stima |
|---|---|---|---|---|
| 11 | 4 schermate Flutter (`booking_services`, `booking_schedule`, `booking_success`, `profile`) e il guard di routing (`app/router.dart`) senza alcun test | `PROJECT_FREEZE_STATE.md` §7.8 | 🟡 | 3-5 giorni |
| 12 | Nessun test esercita l'intera pipeline di build reale (`flutter build apk` end-to-end) | `PROJECT_FREEZE_STATE.md` §8 | 🟡 | 2-3 giorni (richiede un ambiente CI con Android SDK) |
| 13 | Il driver di build `github` non è esercitato da alcun test quanto `local`/`manual` | `PROJECT_STANDARDIZATION_AUDIT.md` | 🟢 | 1-2 giorni |

## Infrastruttura (rimandato per scelta, non per dimenticanza)

| # | Descrizione | Evidenza | Priorità | Stima |
|---|---|---|---|---|
| 14 | Nessun ambiente di staging — solo "locale" e "pilota" | `REAL_PROJECT_STATE.md` §Fase 9 | 🟢 (finché il volume di clienti non lo richiede) | ~1 settimana |
| 15 | Topologia a singola istanza EC2 + singolo RDS `t4g.micro`, nessun autoscaling | `REAL_PROJECT_STATE.md` §Fase 9 | 🟢 (finché il volume di clienti non lo richiede) | Settimane, scala con l'obiettivo |
| 16 | S3 configurato nel codice ma credenziali vuote — storage oggi solo locale | `REAL_PROJECT_STATE.md` §Fase 3 | 🟡 | 0.5 giorni (solo popolare credenziali) |
| 17 | Config Firebase native (`google-services.json`/`GoogleService-Info.plist`) assenti dal repo — le push sono inerti su build reali finché non aggiunte | `PROJECT_FREEZE_STATE.md` §2 | 🟡 | Dipende da accesso alla console Firebase, non dal codice |

## Cose rimandate per decisione esplicita

| # | Descrizione | Motivazione del rinvio |
|---|---|---|
| 18 | Contratto OpenAPI/Swagger | Nessuna richiesta di integrazione esterna lo ha ancora reso necessario — il codice dei controller è oggi l'unica fonte di verità, funzionante ma non generabile automaticamente |
| 19 | Split mono-repo → 3 repository (piano originale `docs/22`) | Costo operativo non ancora giustificato dalla dimensione del team — vedi `PROJECT_EVOLUTION_ROADMAP.md` #11, decisione esplicita richiesta, non un default |
| 20 | Forced-update app (`app_versions` non consumato a runtime) | Tabella preparata ma nessun cliente ha ancora richiesto la funzione |
| 21 | Pagamenti | Fuori scope del prodotto attuale — nessuna integrazione esiste, per scelta di prodotto non per lacuna tecnica |

## Pulizia minore

| # | Descrizione | Priorità | Stima |
|---|---|---|---|
| 22 | `resources/views/welcome.blade.php` — scaffold Laravel mai personalizzato né rimosso | 🟢 | 5 minuti |
| 23 | 27 file `.DS_Store` sparsi (non tracciati, cosmetici) | 🟢 | 1 minuto (`find . -name .DS_Store -delete`, sicuro perché non tracciati) |

## Sintesi per priorità

- 🔴 **Alta (3 voci)**: firma release silenziosa, versioning APK scollegato, CORS aperto — tutte risolvibili in **meno di una settimana totale**, nessuna richiede refactoring ampio.
- 🟡 **Media (9 voci)**: la maggior parte è test mancanti o hardening incrementale — nessuna blocca l'uso attuale del sistema.
- 🟢 **Bassa (8 voci)**: ottimizzazioni, scala futura, pulizia cosmetica — corrette quando il contesto (crescita, team) le renderà necessarie, non prima.
