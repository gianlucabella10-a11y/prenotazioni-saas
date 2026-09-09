# SYSTEM_BOUNDARIES — Confini del sistema

Verificato sul codice, non su intenzioni di prodotto. Fonte primaria: `REAL_PROJECT_STATE.md`, `PROJECT_FREEZE_STATE.md`.

## Cosa il sistema PUÒ fare (verificato, reale)

- Gestire un numero arbitrario di clienti (tenant) su un unico backend/database, con isolamento dati riga-per-riga testato.
- Generare, per ogni cliente, un'identità app univoca (bundle id) e asset brandizzati (icone, splash, colori) senza intervento manuale sul codice.
- Compilare realmente un APK Android (driver `local`, processo `flutter build apk` reale, non simulato) o delegare la compilazione a GitHub Actions (driver `github`).
- Gestire prenotazioni con calcolo di disponibilità reale (fusi orari, buffer, eccezioni di orario, DST), prevenzione di doppie prenotazioni tramite lock e vincolo univoco a livello DB.
- Autenticare utenti con JWT, MFA (TOTP), verifica email — su tre superfici distinte (app cliente, dashboard titolare/staff, Control Room super-admin).
- Inviare notifiche push reali (Firebase Cloud Messaging) con fallback email se il push fallisce.
- Distribuire build ai tester tramite link revocabili, a tempo, con limite di download.
- Riportare crash reali a Sentry (backend e app), quando configurato.

## Cosa il sistema NON PUÒ fare (verificato, non un'omissione di questo documento)

- **Incassare pagamenti**: nessun'integrazione di pagamento esiste nel codice (zero occorrenze di Stripe o simili in tutto il repository).
- **Aggiornare forzatamente le app installate**: la tabella pensata per questo (`app_versions`) esiste ma non è confrontata con nulla a runtime.
- **Firmare in modo affidabile una build di release col driver `local`**: ricade silenziosamente sulla firma debug se le variabili del keystore non sono impostate.
- **Raccogliere analytics di prodotto**: l'infrastruttura lato app esiste ma non è mai collegata — nessun evento viene raccolto oggi.
- **Gestire più di un ambiente reale**: solo "locale" e un ambiente "pilota" esistono; nessuno staging.
- **Scalare orizzontalmente senza intervento infrastrutturale**: topologia a singola istanza EC2 + singolo RDS micro, nessun autoscaling nel Terraform esistente.
- **Terminare/archiviare un cliente da interfaccia**: lo stato esiste nel modello dati ma nessuna azione Control Room lo raggiunge.
- **Gestire un contratto API versionato formalmente**: nessun OpenAPI/Swagger pubblicato — il contratto è implicito nel codice dei controller.

## Dipendenze esterne

| Dipendenza | Per cosa | Cosa succede se manca/è giù |
|---|---|---|
| Firebase Cloud Messaging | Notifiche push | Fallback automatico a email (già implementato) |
| Sentry | Crash reporting | Nessun crash reporting, ma l'app/backend continuano a funzionare (disattivazione silenziosa per design se il DSN è vuoto) |
| GitHub Actions | Build CI/CD, distribuzione beta/store | Le build via driver `github` si fermano; il driver `local` resta un'alternativa solo per sviluppo |
| Un backend di produzione raggiungibile via SSH | La pipeline App Factory in CI (`app-factory-build.yml`) richiama `php artisan app:generate`/`app:build-record` sul backend remoto | L'intera pipeline di build automatizzata si blocca — non esiste un percorso alternativo che non passi da quell'host |
| AWS (RDS, S3, SES, EC2) | Infrastruttura di produzione pianificata | Oggi S3 è configurato ma non popolato (credenziali vuote) — il sistema gira su disco locale, funzionante ma non distribuito |
| Google Play / Apple App Store | Pubblicazione store | Condizionale alla presenza dei segreti relativi nel workflow CI — la distribuzione beta interna non dipende da questo |

## Componenti critici (un guasto qui ha raggio d'impatto massimo)

1. **`Foundation/Tenancy`** — se lo scope di isolamento tenant si rompe, il rischio è una fuga di dati cross-cliente. È anche l'area più testata del repository.
2. **Il database condiviso** — un solo punto di guasto per tutti i tenant (nessun database-per-tenant, nessuna replica oggi).
3. **Il backend di produzione raggiungibile via SSH** — singolo punto di dipendenza per l'intera pipeline di build automatizzata (vedi tabella sopra).
4. **`LocalBuildDispatcher`** — se usato in produzione (sconsigliato, vedi `TECHNICAL_DEBT.md`), esegue processi reali sulla stessa macchina che serve traffico web.

## Componenti sostituibili (a basso rischio se cambiati)

- **`AnalyticsService`** (Flutter) — non è mai stato collegato a nulla; sostituirlo con un provider reale (Firebase Analytics, ecc.) non rompe niente perché oggi non lo usa nessuno.
- **Canale di notifica FCM** — l'astrazione `NotificationChannel` già separa il canale concreto dalla logica di invio; sostituire FCM con un altro provider push tocca un solo file (`FcmPushChannel`).
- **Driver di build** — già astratto dietro un'interfaccia (`BuildDispatcher`); aggiungere un quarto driver non tocca `BuildService`/`RunAppBuildJob`.
- **Disco di storage** (`local`/`public`/`s3`) — già configurabile via `config/filesystems.php`, popolare le credenziali S3 non richiede modifiche al codice applicativo.
- **`welcome.blade.php`** — scaffold Laravel mai personalizzato, irrilevante per il prodotto.
