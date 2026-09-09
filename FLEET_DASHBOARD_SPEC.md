# FLEET_DASHBOARD_SPEC — Cosa mostrare, verificato dato per dato

> Non implementata in questa fase (solo specifica). Ogni riga è classificata **✅ ottenibile oggi** (con la fonte dati reale già esistente), **🟡 parzialmente ottenibile** (il dato esiste ma è un'approssimazione/euristica, non una certezza), o **❌ non ottenibile senza nuovo codice/infrastruttura** (con il motivo). Nessuna riga è inventata — ognuna è stata verificata contro schema DB e codice reali.

| Metrica (dall'elenco richiesto) | Stato | Fonte reale / motivo |
|---|---|---|
| Numero clienti | ✅ | `Tenant::count()`, già disponibile |
| App attive | ✅ | `AppProject::where('build_status', 'published')->count()`, o `Tenant::where('status', 'active')` |
| App inattive | ✅ | Tenant `suspended`/`terminated`, o AppProject non pubblicate |
| Versioni installate (sui device reali) | ❌ | Nessuna telemetria arriva dai device — `AnalyticsService` è codice morto (`TECHNICAL_DEBT.md` #3); anche se fosse collegato, l'APK non porta un numero di versione realmente distintivo per build (#2) |
| Versioni obsolete | 🟡 | Ottenibile solo come "core version stale" (`BuildFleet::summary()['stale']`, già esistente) — non "quanti device reali girano su una versione vecchia", che richiederebbe il dato sopra |
| Build oggi | ✅ | `AppBuild::whereDate('created_at', today())->count()` |
| Build fallite | ✅ | Già esposto (`BuildFleet::summary()['builds']['failed']`) |
| Build in coda | ✅ | Già esposto nel cruscotto (`DB::table('jobs')->count()`) |
| Tempo medio build | ✅ | `AppBuild::avg('duration_ms')` — colonna già tracciata (solo per driver `local`, che la popola realmente) |
| Crash aperti | ❌ | Sentry è un dashboard esterno separato — nessuna integrazione API nel codice per leggere il conteggio crash dal backend Laravel |
| Feedback ricevuti | ✅ | `BetaFeedback::count()`, oggi mostrato solo per singola app — aggregabile a livello flotta senza nuova tabella |
| Backup (stato/età) | ✅ | Già nel cruscotto (`HomeController::latestBackup()`) |
| Worker (coda attiva?) | 🟡 | Euristica già nel cruscotto: job più vecchio di 10 minuti → "potrebbe non essere attivo" — non una certezza, nessun heartbeat diretto del processo |
| Queue (pending/failed) | ✅ | Già nel cruscotto |
| Storage (disco) | ✅ | Già nel cruscotto (`disk_free_space`/`disk_total_space`) |
| CPU | ✅ | **Ottenibile senza shell**: `sys_getloadavg()` è una funzione PHP nativa su sistemi POSIX (Linux/macOS) — restituisce il load average 1/5/15 minuti, nessun comando esterno necessario. Non ancora implementata, ma realmente disponibile |
| RAM (di sistema, non di PHP) | ❌ | Nessuna funzione PHP nativa e portabile per la RAM totale del sistema — richiederebbe shell (`free`/`vm_stat`, platform-specific) o un'estensione non presente. `memory_get_usage()` esiste ma misura solo il processo PHP corrente, non il sistema |
| Database (raggiungibilità) | ✅ | Una query banale (`DB::select('select 1')`) prova la connessione; per sqlite anche `filesize()` del file |
| Email (configurato?) | 🟡 | Verificabile solo come "è configurato" (`config('mail.default')`/credenziali non vuote), non come tasso di consegna reale — nessun log/webhook di delivery nel codice |
| Push/Firebase (configurato?) | 🟡 | Stesso principio: `config('services.fcm.project_id')` non vuoto è verificabile; il tasso di consegna reale no |
| API (raggiungibile) | ✅ | Implicito — se il cruscotto stesso si carica, il backend risponde. Un conteggio richieste/minuto richiederebbe instrumentazione aggiuntiva, non presente |
| SSL / Certificati | ❌ | Gestito a livello infrastruttura (Caddy/reverse proxy), non un dato che Laravel conosce nativamente; richiederebbe configurare esplicitamente il dominio pubblico e una chiamata di verifica, non presente oggi |
| Scadenze (keystore, chiavi JWT) | ❌ | Non tracciate — nessuna data di scadenza persistita per questi segreti |
| Spazio disco | ✅ | Già nel cruscotto |
| Ultima build | ✅ | Già nel cruscotto/flotta |
| Ultimo login Founder | 🟡 | Il dato **esiste già** (`users.last_login_at`, aggiornato a ogni login Control Room) ma **non è mostrato in nessuna vista** — banale da esporre, non ancora fatto |

## Metriche aggiuntive realmente ottenibili, non nell'elenco originale

| Metrica | Fonte |
|---|---|
| Tester per stato (invited/active/blocked) | `BetaTester::selectRaw('status, count(*)')->groupBy('status')` |
| Download beta totali | `BetaDownloadToken::sum('download_count')` |
| Link beta in scadenza nelle prossime 24-48h | `BetaDownloadToken::where('expires_at', '<=', now()->addDay())->where('revoked_at', null)` |
| Attività recente (eventi audit nelle ultime 24h) | `DB::table('audit_logs')->where('created_at', '>=', now()->subDay())->count()` |
| Notifiche fallite/in ritardo | `NotificationRecord::where('status', 'failed')` o `scheduled_for < now()` ancora `scheduled` |

## Principio di specifica

Ogni riga ❌ di questa tabella è ❌ per un motivo tecnico verificato (nessuna telemetria, nessuna API esterna integrata, nessuna funzione PHP portabile) — non per pigrizia. Le righe 🟡 sono deliberatamente segnalate come approssimazioni, per evitare che un futuro sviluppatore le implementi presentandole come dati certi quando sono euristiche.
