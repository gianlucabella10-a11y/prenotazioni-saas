# FOUNDER_DASHBOARD — Definizione (non implementata)

> Solo definizione concettuale — nessun codice/UI creato in questa fase. Una futura implementazione dovrebbe vivere come nuova vista in Control Room (`/control-room`), non come sistema separato.

## Widget

| Widget | Cosa mostra | Fonte dati (già esistente) |
|---|---|---|
| Clienti totali / attivi / sospesi | Conteggio per stato (`TenantStatus`) | `tenants` |
| Nuovi clienti (ultimi 7/30 giorni) | Trend di crescita | `tenants.created_at` |
| Build in corso / in coda | Stato aggregato di tutte le build attive su tutti i tenant | `app_builds` (oggi visibile solo per singola app, non aggregato — gap coerente con `CONTROL_ROOM_ROADMAP.md` "Build queue") |
| Build fallite (ultime 24h) | Elenco rapido, con link diretto al log | `app_builds.status = failed` |
| Tester attivi / feedback non letto | Quanti tester per stato, quanti feedback recenti | `beta_testers`, `beta_feedback` |
| Link beta in scadenza (prossimi 3 giorni) | Evita che un tester resti senza accesso senza preavviso | `beta_download_tokens.expires_at` |
| Ultimo backup eseguito | Data/ora, con indicatore rosso se oltre soglia | 🔴 Richiede l'automazione #1/#2 di `AUTOMATION_CATALOG.md` — oggi non tracciato da nessuna parte |
| Stato toolchain build machine | Flutter/Android SDK disponibili e versione | 🔴 Non esiste oggi (`CONTROL_ROOM_ROADMAP.md`) |

## KPI

| KPI | Definizione | Perché conta |
|---|---|---|
| Tasso di successo build | build `built` / build totali (finestra configurabile) | Segnala degrado della pipeline prima che diventi un problema visibile ai clienti |
| Tempo medio da "Genera" a "Build completata" | Differenza `queued_at`→`finished_at` | Misura l'efficienza operativa reale, non percepita |
| Clienti attivi vs. totali | `active` / totale `tenants` | Salute commerciale di base |
| Tester attivi vs. invitati | `active` / totale `beta_testers` | Qualità del programma beta |
| Giorni dall'ultimo backup | `now() - ultimo backup` | Rischio operativo diretto |

**Nota onesta**: nessun KPI di utilizzo reale dell'app (sessioni, prenotazioni completate, retention) è oggi disponibile — dipendono da `AnalyticsService`, che non è collegato a nulla (`TECHNICAL_DEBT.md` #3). Un Founder Dashboard costruito oggi misurerebbe solo la salute *operativa* della piattaforma, non l'utilizzo *reale* da parte dei clienti finali.

## Grafici

- Andamento clienti nel tempo (linea, cumulativo).
- Build per esito, ultimi 30 giorni (barre impilate: built/failed/in corso).
- Distribuzione clienti per settore (barber/salone/dentale/ecc. — già presente come campo `sector`).
- Distribuzione clienti per piano (`Plan`).

## Alert

| Alert | Condizione | Priorità |
|---|---|---|
| Backup non eseguito | > 24-48h dall'ultimo backup riuscito | 🔴 Alta |
| Build fallita | Ogni fallimento, notifica immediata | 🟡 Media |
| Link beta in scadenza senza rinnovo | < 24h alla scadenza, tester ancora `active` | 🟢 Bassa |
| Toolchain build machine non disponibile | Health check fallito (vedi automazione #9) | 🟡 Media |
| Cliente in stato `at_risk` da più di N giorni | Nessuna azione presa sul tenant | 🟢 Bassa |

## Azioni rapide (dalla dashboard, senza navigare altrove)

- "+ Nuovo cliente" (form rapido).
- "Rebuild flotta stale" (richiama `BuildFleet`/logica già esistente).
- "Backup ora" (richiama lo script esistente — vedi `AUTOMATION_CATALOG.md`).
- "Vedi build fallite" (filtro diretto).
- "Vedi feedback non letto" (filtro diretto).

## Cosa NON dovrebbe esserci

Azioni distruttive dirette dalla dashboard riassuntiva (sospendi/termina cliente, elimina build) — quelle restano nella scheda cliente/app dedicata, con conferma esplicita, per evitare click accidentali su una vista pensata per essere consultata velocemente e spesso.
