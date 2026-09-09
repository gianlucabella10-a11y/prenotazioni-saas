# AUTOMATION_CATALOG — Automazioni future

> Solo catalogo — nessuna di queste è implementata in questa fase. Ordinate per priorità decrescente all'interno di ogni gruppo.

| # | Automazione | Beneficio | Priorità | Impatto |
|---|---|---|---|---|
| 1 | **Backup schedulato automatico** (cron/systemd timer che esegue `backup-control-center.sh` con cadenza fissa, es. giornaliera, e verifica l'integrità dell'archivio prodotto) | Elimina la dipendenza dalla memoria umana per l'unica rete di sicurezza contro perdita dati | 🔴 Alta | Alto — oggi un giorno di dimenticanza = un giorno di dati non recuperabili |
| 2 | **Alert automatico se il backup non è girato** (notifica al Founder/Platform Admin se l'ultimo backup ha più di 24-48h) | Rende visibile un fallimento silenzioso | 🔴 Alta | Alto — un backup che fallisce silenziosamente è peggio di nessun backup (falso senso di sicurezza) |
| 3 | **Rebuild automatico della flotta "stale"** (il sistema già calcola quali `AppProject` hanno `built_core_version` inferiore alla versione corrente — `BuildFleet`/`app:build-matrix` esistono già, manca solo lo scheduling) | Nessun cliente resta su una versione vecchia per dimenticanza | 🟡 Media | Medio — oggi richiede trigger manuale via CI, il calcolo di "chi è stale" è già automatico |
| 4 | **Versione APK sincronizzata con `app_builds.version`** (passare `--build-name`/`--build-number` reali a `flutter build`, invece del valore statico di `pubspec.yaml`) | Sblocca un vero forced-update in futuro, rende tracciabile quale APK gira su quale device | 🔴 Alta | Alto — precondizione tecnica per diverse altre automazioni di questa lista |
| 5 | **Fail-fast sulla firma release** (la build si rifiuta di procedere se `ANDROID_KEYSTORE_*` non sono impostate, invece di ripiegare silenziosamente su debug) | Elimina il rischio di distribuire per errore una build non firmata correttamente | 🔴 Alta | Alto — oggi il problema si scopre solo verificando manualmente |
| 6 | **Pulizia automatica dei token beta scaduti/revocati** (job schedulato che rimuove/segnala i `beta_download_tokens` non più validi) | Igiene dati, riduce superficie di query inutili | 🟢 Bassa | Basso |
| 7 | **Pulizia automatica dei log/notifiche vecchie** | Evita crescita indefinita delle tabelle ad alto volume (`notification_records`, log file) | 🟡 Media | Medio a scala — oggi non è un problema col volume attuale |
| 8 | **Notifica automatica al Founder per ogni build fallita** (non solo visibile in Control Room se qualcuno la controlla) | Riduce il tempo tra un fallimento e la sua scoperta | 🟡 Media | Medio |
| 9 | **Health check automatico della build machine** (verifica periodica che Flutter/Android SDK siano presenti e funzionanti, prima che una build reale fallisca per questo motivo) | Sposta la scoperta del problema da "durante una build reale" a "prima che serva" | 🟡 Media | Medio |
| 10 | **Promemoria automatico di scadenza segreti** (keystore, chiavi JWT — se hanno una data di scadenza nota) | Evita interruzioni impreviste per un segreto scaduto | 🟢 Bassa | Alto se accade, ma bassa probabilità/frequenza |
| 11 | **Generazione automatica del pacchetto app al salvataggio del brand** (oggi richiede il click esplicito su "Genera") | Riduce un passaggio manuale ripetitivo | 🟢 Bassa | Basso — il passaggio manuale attuale costa ~1 minuto |
| 12 | **Deploy automatizzato con gate di approvazione** (CI che prepara il deploy, un umano approva, poi esegue — invece di uno script eseguito a mano) | Riduce errore umano nel deploy, mantiene comunque il controllo umano finale | 🟡 Media | Medio-alto — tocca produzione, va introdotto con cautela |
| 13 | **Rotazione automatica dei log applicativi** (oggi `storage/logs/laravel.log` cresce senza rotazione esplicita documentata) | Evita che i log riempiano il disco nel tempo | 🟢 Bassa | Basso a breve termine, medio a lungo termine |
| 14 | **Collegamento `AnalyticsService` a un provider reale** (non è "automazione" in senso stretto ma è un prerequisito per qualunque dashboard automatica di utilizzo) | Sblocca KPI di prodotto reali, oggi assenti | 🟡 Media | Alto per il valore di business, oggi a zero |

## Le 5 automazioni a più alto rapporto beneficio/costo

1. Backup schedulato (#1) — beneficio massimo, costo di implementazione minimo (è già uno script funzionante, serve solo uno scheduler).
2. Alert se il backup non gira (#2) — stesso principio, completa il #1.
3. Fail-fast sulla firma release (#5) — previene un errore di distribuzione con una singola verifica condizionale.
4. Versione APK sincronizzata (#4) — sblocca a cascata #3 (rebuild automatico) e ogni futuro forced-update.
5. Rebuild automatico della flotta stale (#3) — il calcolo esiste già (`BuildFleet`), manca solo il trigger schedulato.
