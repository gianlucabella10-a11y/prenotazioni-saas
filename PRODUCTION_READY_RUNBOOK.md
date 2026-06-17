# PRODUCTION_READY_RUNBOOK

> **Come creo una nuova app cliente in 5 minuti.** Procedura operativa per un addetto, senza toccare codice. Esempio: **Giuffrida Barber**.

## Prerequisiti (una tantum)
- Accesso Control Room (super-admin, MFA): `/control-room`.
- Per la build reale: runner CI + segreti (`APP_FACTORY_RELEASE_SECRETS.md`); per la beta: Firebase / account Apple (`BETA_RELEASE.md`). Senza, il flusso arriva fino al **pacchetto scaricabile**.

## I 6 passi (≈5 minuti)
**1. Crea tenant** — *Clienti ▸ + Nuovo cliente*: «Giuffrida Barber», settore *barber*, email titolare, piano, **template Barber**, colore brand.
→ tenant + titolare + brand + sede + orari + catalogo + invito; identità app (bundle/package) **unica e immutabile** allocata. Stato: `draft`.

**2. Carica logo** — scheda App ▸ Giuffrida ▸ upload logo (PNG/JPG/WebP ≥256px, validato).
→ stato `configured` (transizione tracciata in audit).

**3. Configura dati** — il titolare completa servizi/operatori/orari dalla sua dashboard; colori/contatti/social dal brand. (Arrivano all'app via `/app/config`, nessuna build necessaria.)

**4. Genera app package** (1 click) — *Genera pacchetto*.
→ Asset Factory (icone/splash/store, versionati) + manifest 2.0 + cartella self-contained. Stato `ready_to_build`. Anteprima: logo, icona, splash, feature graphic, colori.
CLI equivalente: `php artisan app:generate {tenant-uuid}`.

**5. Crea build** (1 click) — *Build Android* / *Build iOS*.
→ `app_builds(queued)` + worker (`RunAppBuildJob`) → `building`; la CI compila l'AAB/IPA firmato. Timeline visibile (queued → building → built); errori in `error_message`.

**6. Scarica / distribuisci beta** — *Scarica package (ZIP)* per ispezione; per i tester: Firebase App Distribution (Android) / TestFlight (iOS) — vedi `BETA_RELEASE.md`.

## Verifica rapida
- Control Room ▸ scheda app: stato, versioni, build history, download.
- Control Room ▸ **Flotta**: app costruibili/allineate/stale + build riuscite/fallite/in corso.

## Se qualcosa va storto
- **Build fallita**: lo stato diventa `failed` con `error_message`; correggi (es. configura il driver/segreti) e rilancia.
- **Asset sbagliati**: *Rollback* a una versione precedente (lo storico non si cancella), poi rigenera il pacchetto.
- **Driver non configurato** (`github`): messaggio esplicito; usa `manual` e lancia la CI a mano.

## Regola d'oro
1 codice · 1 backend · N clienti. Ogni cliente è **configurazione + asset + identità**, mai codice. Nessun fork, nessun `if tenant==`.
