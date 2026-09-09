# Piattaforma White Label per Prenotazioni

SaaS multi-tenant per la gestione di appuntamenti (barbieri, saloni, centri estetici, studi dentistici/medici, fisioterapisti, consulenti). Ogni cliente ("tenant") ottiene una dashboard di gestione via web e un'app mobile Android brandizzata con il proprio nome, logo e colori — generata automaticamente da un motore interno ("App Factory"), senza scrivere codice per ogni cliente.

> **Punto d'ingresso completo del repository**: [`PROJECT_INDEX.md`](PROJECT_INDEX.md). Questo README è la homepage tecnica — per l'elenco esaustivo di documentazione, moduli, stato del progetto e roadmap, parti da lì.

## Visione

Permettere a un professionista che lavora su appuntamento di avere la propria app di prenotazione brandizzata, senza commissionare un'app da zero — l'intera piattaforma (backend + motore di generazione app) è condivisa tra tutti i clienti, il costo marginale per aggiungerne uno nuovo è di configurazione, non di sviluppo. Dettaglio strategico: [`docs/Business/01-prd.md`](docs/Business/01-prd.md).

## Cos'è, in una frase

Un backend Laravel condiviso da tutti i clienti (multi-tenant, isolamento a livello di riga) + un'unica app Flutter il cui branding viene iniettato a build-time e letto a runtime, invece di essere una app diversa per ogni cliente.

## Architettura, in breve

```
Flutter (client_app, unico)  ⇄  HTTP  ⇄  Laravel (API + Dashboard + Control Room + App Factory)
                                                    │
                                          Database unico condiviso
                                     (isolamento riga-per-riga via tenant_id)
```
Un solo repository (non tre come originariamente pianificato — vedi `PROJECT_STRUCTURE.md` §0), un solo database, un solo codice sorgente Flutter. Nessun microservizio: monolite modulare (`app/Modules/<Nome>`, un modulo per bounded context). Dettaglio: `docs/Architecture/`, `PROJECT_MAP.md`, `SYSTEM_BOUNDARIES.md`.

## Come funziona, ad alto livello

```
Cliente compra il servizio
  → Control Room (pannello interno): l'operatore crea il tenant
  → ProvisionTenant: transazione unica → tenant + abbonamento + brand di default +
    sede/orari + catalogo servizi di partenza + primo utente titolare + invito
  → Il titolare carica logo/colori dalla propria Dashboard (o l'operatore lo fa per lui)
  → Control Room: "Genera" → identità app allocata (bundle id univoco), asset
    brandizzati generati (icone/splash/store), manifest JSON scritto su disco
  → Control Room: "Build" → un job in coda compila realmente `flutter build apk`
    (stesso sorgente Flutter per tutti i tenant, branding iniettato via flag di build)
  → APK prodotto, checksum calcolato, salvato per tenant/versione
  → Link beta (token revocabile, scadenza 7gg) o pubblicazione store
  → L'utente finale installa l'app, si registra, verifica l'email, prenota
  → Il backend gestisce disponibilità/conflitti/notifiche/cancellazioni
```

Dettaglio verificato passo-passo (con riferimenti a file/classi reali): [`BUSINESS_FLOW.md`](BUSINESS_FLOW.md).

## Come nasce una nuova app (white label)

Il Flutter **non viene mai clonato o modificato per cliente**. Un solo codice sorgente (`platform-mobile/apps/client_app`) riceve, a ogni build:
- un `applicationId` Android univoco e immutabile (`com.platform.t{shortcode}`), passato come proprietà Gradle
- variabili di branding/API (`--dart-define`) iniettate nel binario compilato

A runtime, l'app installata chiama `GET /api/v1/app/config` (con la propria chiave tenant) per ricevere colori/nome/logo aggiornati — la personalizzazione non richiede una nuova build per ogni modifica estetica, solo per un vero cambio di identità (nome pacchetto). Dettaglio: [`docs/WhiteLabel/`](docs/WhiteLabel/), pipeline completa in `REAL_PROJECT_STATE.md` §Fase 5/9.

## Come funziona il multitenant

Un solo database condiviso da tutti i clienti — **non** un database per tenant. L'isolamento è a livello di riga: ogni tabella rilevante ha una colonna `tenant_id`, e uno scope Eloquent globale filtra automaticamente ogni query sul tenant risolto per la richiesta corrente (da un header firmato, un claim JWT, o la sessione — mai da un parametro modificabile dal client). Se il contesto tenant non è impostato, il sistema fallisce (eccezione), non esegue query non filtrate. Verificato con test dedicati di isolamento cross-tenant. Dettaglio completo: `REAL_PROJECT_STATE.md` §Fase 8, `PROJECT_DEPENDENCIES.md` §1.

## Come funziona il backend

Laravel 13 (PHP 8.3), organizzato in moduli per bounded context (`app/Modules/{TenantManagement,Scheduling,Catalog,Staff,Customers,Branding,Notifications,AppFactory,ControlRoom,Dashboard}`), più codice trasversale in `app/Foundation/{Tenancy,Auth,Audit,Http}`. Autenticazione via guard JWT custom (non Sanctum), tre guard distinti (`web` dashboard, `admin` Control Room, `api` app mobile). Dettaglio: [`docs/Backend/`](docs/Backend/), mappa completa in [`PROJECT_MAP.md`](PROJECT_MAP.md).

## Come funziona Flutter

App unica (`platform-mobile/apps/client_app`), Riverpod per lo stato, go_router per la navigazione, Dio per l'HTTP con refresh-token automatico. 12 schermate (auth, home, catalogo/booking a 3 step, appuntamenti, profilo, scheda attività), tutte reali (nessun placeholder). Dettaglio: [`docs/Flutter/`](docs/Flutter/), `PROJECT_FREEZE_STATE.md` §7.

## Come funziona la build

Driver configurabile: `manual` (default sicuro, nessuna azione), `local` (compila davvero `flutter build apk` sulla macchina che esegue il backend — solo sviluppo), `github` (delega a GitHub Actions, driver corretto per build reali). Dettaglio + gap noti (firma, versioning): [`PROJECT_FREEZE_STATE.md`](PROJECT_FREEZE_STATE.md) §8, `docs/AppFactory/`.

## Come funziona il download APK

Ogni build completata genera un token di download beta: revocabile, scade dopo 7 giorni, limite di download configurabile. Il file viene servito in streaming dal Control Room, mai esposto come URL pubblico permanente. Dettaglio: `REAL_PROJECT_STATE.md` §Fase 7.

## Come funziona per il cliente (titolare/staff)

Dashboard web (`/dashboard`), sessione autenticata separata dal Control Room: gestione servizi, operatori, disponibilità/orari, conferma/cancellazione prenotazioni, personalizzazione brand. Guida: [`docs/Operations/PREVIEW_ACCESS_GUIDE.md`](docs/Operations/PREVIEW_ACCESS_GUIDE.md).

## Come funziona il centro operativo (Control Room)

Pannello interno riservato al proprietario della piattaforma (`/control-room`, guard separato, MFA), da cui si crea/gestisce ogni cliente, si generano/lanciano le build, si gestiscono tester e link beta. Guida operatore: [`docs/ControlRoom/CONTROL_ROOM_OPERATOR_GUIDE.md`](docs/ControlRoom/CONTROL_ROOM_OPERATOR_GUIDE.md). Uso quotidiano: [`OPERATION_MANUAL.md`](OPERATION_MANUAL.md).

## Beta

Ogni build completata può generare un link beta (token da 48 caratteri, scadenza fissa a 7 giorni, limite download configurabile — default 50) o essere distribuita via Firebase App Distribution/TestFlight tramite la CI. I tester si gestiscono da Control Room (invita/attiva/blocca); il feedback che inviano dall'app è visibile in sola lettura, non gestibile da lì. Guida: [`docs/Runbooks/BETA_TESTING_GUIDE.md`](docs/Runbooks/BETA_TESTING_GUIDE.md).

## Release

Il numero di versione tracciato nel database (`app_builds.version`, `app_versions`) e quello realmente compilato nell'APK **non sono ancora collegati** (gap noto, vedi `TECHNICAL_DEBT.md`). Processo e checklist: [`docs/Release/`](docs/Release/) (decisioni GO/NO-GO, checklist store, processo di rilascio).

## Come funziona la pubblicazione

Distribuzione beta via link revocabile (interno) o Firebase App Distribution/TestFlight (CI, `app-factory-build.yml`); pubblicazione store (Google Play/App Store) tramite lo stesso workflow, condizionale alla presenza dei segreti di pubblicazione. Dettaglio: `docs/Release/`.

## Componenti del repository

| Componente | Cosa contiene |
|---|---|
| `platform-backend/` | API Laravel, dashboard web, Control Room, App Factory, scheduler, queue |
| `platform-mobile/apps/client_app/` | App Flutter cliente (unica, white-label a build/runtime) |
| `platform-infra/` | Terraform (ambiente "pilota"), script di deploy, runbook |
| `docs/` | Documentazione ufficiale, per categoria (vedi [`PROJECT_MAP.md`](PROJECT_MAP.md)) |
| `.github/workflows/` | CI (test) + pipeline di build per-tenant/in-batch |

## Moduli indipendenti

`ControlRoom` e `Dashboard` sono superfici di presentazione — nessun altro modulo dipende da loro. `AppFactory` è il modulo più connesso (dipende da `TenantManagement`+`Branding`, consumato da `ControlRoom` e dalla CI). Grafo completo, accoppiamenti e cicli: [`DEPENDENCY_GRAPH.md`](DEPENDENCY_GRAPH.md).

## Come aggiungere una nuova feature

1. Leggi [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md) per setup/convenzioni.
2. Identifica il modulo di dominio corretto (o valuta se ne serve uno nuovo) — mai codice di dominio fuori da `app/Modules/<Nome>/`.
3. Segui gli standard vincolanti in [`PROJECT_STANDARD.md`](PROJECT_STANDARD.md) (naming, namespace, test obbligatori — in particolare: ogni nuovo modello con `tenant_id` **deve** usare `BelongsToTenant`).
4. Se il modulo tocca dati tenant-scoped, scrivi un test di isolamento cross-tenant (pattern in `TenantIsolationTest`/`WhiteLabelIsolationTest`).
5. Aggiorna la documentazione pertinente **in-place** (non creare un nuovo file "readiness" — vedi lo standard di documentazione, §4).

## Stato del progetto

Verificato riga per riga sul codice, non su intenzioni: [`REAL_PROJECT_STATE.md`](REAL_PROJECT_STATE.md) (panoramica) e [`PROJECT_FREEZE_STATE.md`](PROJECT_FREEZE_STATE.md) (dettaglio per modulo/cartella/comando/schermata). Audit strutturale del repository: [`PROJECT_STRUCTURE_AUDIT.md`](PROJECT_STRUCTURE_AUDIT.md) e [`PROJECT_STANDARDIZATION_AUDIT.md`](PROJECT_STANDARDIZATION_AUDIT.md). Valutazione numerica per dimensione: [`PROJECT_SCORE.md`](PROJECT_SCORE.md). Debito tecnico esplicito, con priorità e stima: [`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md). Cosa il sistema può e non può fare: [`SYSTEM_BOUNDARIES.md`](SYSTEM_BOUNDARIES.md).

## Roadmap

Solo miglioramenti strutturali, nessuna nuova feature di prodotto — 20 interventi organizzati in Immediate/Next/Future/Long Term: [`PROJECT_EVOLUTION_ROADMAP.md`](PROJECT_EVOLUTION_ROADMAP.md).

## Link a tutta la documentazione

Indice assoluto, con ordine di lettura per ruolo: [`PROJECT_INDEX.md`](PROJECT_INDEX.md). Onboarding sviluppatore in 30 minuti: [`DEVELOPER_ONBOARDING.md`](DEVELOPER_ONBOARDING.md). Guida per il proprietario della piattaforma: [`OWNER_GUIDE.md`](OWNER_GUIDE.md).
