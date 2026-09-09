# PROJECT_CLEANUP_PLAN — Piano di pulizia (nessuna azione eseguita)

> Nessun file è stato spostato, rinominato o eliminato. Ogni riga sotto è una **raccomandazione** per un intervento futuro esplicitamente autorizzato. Legenda: **KEEP** (resta dov'è, è corretto così) · **MOVE** (stessa versione, nuova posizione — vedi `PROJECT_STRUCTURE.md`) · **MERGE** (consolidare 2+ file quasi-duplicati in uno) · **ARCHIVE** (valore storico, va preservato ma fuori dai percorsi "attivi") · **DELETE** (raccomandato per rimozione — nessun valore residuo, motivato caso per caso).

## Metodo

L'inventario di 77 file markdown + 3 script + 17 immagini alla radice è stato letto (titolo + primi paragrafi di ognuno, timestamp di modifica, storia git) e raggruppato in cluster tematici — molti file sono coppie o catene "audit → readiness report" prodotte nella stessa sessione di lavoro a poche ore di distanza, sullo stesso argomento. **19 di questi file non sono mai stati committati** (esistono solo nella working tree): sono gli artefatti della sessione più recente, mai consolidati.

---

## 1. Cluster App Factory (13 file) — pianificazione storica del motore di build

| File | Verdetto | Motivazione |
|---|---|---|
| `APP_FACTORY_MASTER_PLAN.md` | **ARCHIVE** | Piano architetturale storico, superato dall'implementazione reale (documentata in `REAL_PROJECT_STATE.md` §Fase 7/10). Valore: cronologia decisionale. |
| `APP_FACTORY_ROADMAP.md` | **ARCHIVE** | Predecessore del Master Plan — stesso destino. |
| `APP_FACTORY_PHASE1_PLAN.md` | **ARCHIVE** | Piano di fase, superato. |
| `APP_FACTORY_PHASE2_PLAN.md` | **ARCHIVE** | Piano di fase, superato. |
| `APP_FACTORY_PHASE2_AUDIT.md` | **ARCHIVE** | Audit pre-implementazione di fase, superato. |
| `APP_FACTORY_PHASE2_READINESS.md` | **ARCHIVE** | Report di completamento fase, superato. |
| `APP_FACTORY_PHASE2B_READINESS.md` | **ARCHIVE** | Report di completamento fase, superato. |
| `APP_FACTORY_PHASE2C_READINESS.md` | **ARCHIVE** | Report di completamento fase, superato. |
| `APP_FACTORY_PHASE3_READINESS.md` | **ARCHIVE** | Report di completamento fase, superato. |
| `APP_FACTORY_RELEASE_SECRETS.md` | **MOVE** → `docs/developer/` | **Ancora operativamente utile** (elenco segreti CI/GitHub Actions richiesti) — non è uno snapshot storico, è reference attiva. |
| `APP_PROVISIONING_RUNBOOK.md` | **MERGE** con `PRODUCTION_READY_RUNBOOK.md` → `docs/customer/APP_PROVISIONING_RUNBOOK.md` | Stesso identico scopo operativo ("crea un cliente end-to-end"), due nomi diversi. Tenere il più recente/completo dei due come base della fusione. |
| `PRODUCTION_READY_RUNBOOK.md` | **MERGE** (vedi sopra) | — |
| `FRONTEND_NOTIFICATION_IMPLEMENTATION_PLAN.md` | **ARCHIVE** | Piano di fase per feature ora implementata (notifiche push — confermato ESISTE in `PROJECT_FREEZE_STATE.md` §3), fuori posto in questo cluster (commit misto), ma stesso destino: storico. |

## 2. Cluster White-Label audit (4 file) — catena lineare stesso giorno

| File | Verdetto | Motivazione |
|---|---|---|
| `WHITE_LABEL_FINAL_AUDIT.md` | **ARCHIVE** | Primo anello della catena (00:21), superato dai successivi. |
| `WHITE_LABEL_10_10_AUDIT.md` | **ARCHIVE** | Secondo anello (00:50), superato. |
| `WHITE_LABEL_PRODUCTION_READINESS.md` | **ARCHIVE** | Terzo anello (00:25 — nota: timestamp precede il "10/10 AUDIT", la catena non è strettamente lineare per data), sovrapposizione di contenuto con il file successivo. |
| `WHITE_LABEL_PRODUCTION_READY.md` | **ARCHIVE** | Report finale della catena (04:42) — il più recente/completo dei quattro, ma comunque uno snapshot puntuale ormai superato dallo stato verificato in `REAL_PROJECT_STATE.md`. |

Nessun MERGE qui: sono 4 checkpoint temporali distinti dello stesso lavoro, non duplicati bit-per-bit. Il valore è nella sequenza (mostra come è evoluto l'hardening), quindi si archiviano tutti e quattro insieme, non se ne sceglie uno solo.

## 3. Cluster Beta readiness (12 file) — pattern ripetuto 6 volte

| File | Verdetto | Motivazione |
|---|---|---|
| `PRODUCTION_GAP_ANALYSIS.md` | **ARCHIVE** | Round 1 audit. |
| `PRODUCTION_BETA_READINESS.md` | **ARCHIVE** | Round 1 report. |
| `FINAL_BETA_AUDIT.md` | **ARCHIVE** | Round 2 audit. |
| `BETA_READY_REPORT.md` | **ARCHIVE** | Round 2 report. |
| `FINAL_PRODUCT_ACTIVATION_AUDIT.md` | **ARCHIVE** | Round 3 audit. |
| `FINAL_BETA_READY_REPORT.md` | **ARCHIVE** | Round 3 report. |
| `FINAL_REAL_BETA_AUDIT.md` | **ARCHIVE** | Round 4 audit. |
| `FINAL_REAL_BETA_READY.md` | **ARCHIVE** | Round 4 report. |
| `FINAL_SHIP_AUDIT.md` | **ARCHIVE** | Round 5 audit. |
| `REAL_BETA_SHIP_REPORT.md` | **ARCHIVE** | Round 5 report. |
| `FINAL_BETA_SHIP_AUDIT.md` | **ARCHIVE** (mai committato) | Round 6 audit — working tree only. |
| `FIRST_REAL_CUSTOMER_BETA_REPORT.md` | **ARCHIVE** (mai committato) | Round 6 report, chiude la catena — è il più recente e completo, ma resta comunque uno snapshot puntuale, non una fonte di verità corrente. |
| — | **Raccomandazione aggiuntiva** | Questo pattern (6 round di "audit → fix → readiness report" sullo stesso sotto-sistema, in una sola sessione) è il segnale più forte, in tutto l'inventario, di un processo che genera un documento ad ogni iterazione invece di aggiornarne uno solo. Vedi `PROJECT_STANDARD.md` §Documentazione per la regola che dovrebbe prevenirlo in futuro. |

## 4. Cluster APK / build-machine / device-install (11 file, per lo più mai committati)

| File | Verdetto | Motivazione |
|---|---|---|
| `FIRST_BETA_ENVIRONMENT_AUDIT.md` | **ARCHIVE** (mai committato) | Snapshot toolchain mancante — superato non appena installata. |
| `FINAL_BUILD_MACHINE_AUDIT.md` | **ARCHIVE** (mai committato) | Stesso audit, toolchain ora presente — quasi-duplicato del precedente con esito diverso. |
| `BUILD_MACHINE_SETUP.md` | **MOVE** → `docs/developer/` | Runbook generico riutilizzabile, non uno snapshot — valore operativo persistente. |
| `FIRST_APK_SHIP_REPORT.md` | **ARCHIVE** (mai committato) | Checkpoint puntuale. |
| `SIGNED_APK_READY.md` | **ARCHIVE** (mai committato) | Checkpoint puntuale — **nota di attenzione**: descrive uno stato ("firma release attiva") che `PROJECT_FREEZE_STATE.md` §8 contraddice per il codice attuale (`LocalBuildDispatcher` non imposta le env del keystore → fallback debug). Se questo file resta navigabile senza contesto, un futuro sviluppatore potrebbe fidarsi di un'affermazione non più vera. Da archiviare con nota esplicita di superamento, non solo spostare. |
| `FIRST_REAL_BETA_READY.md` | **ARCHIVE** (mai committato) | Checkpoint "toolchain assente su questa macchina" — puramente storico/locale a una macchina specifica. |
| `REAL_FIRST_INSTALL_REPORT.md` | **ARCHIVE** (mai committato) | Checkpoint puntuale. |
| `REAL_DEVICE_TEST.md` | **MERGE** con `REAL_DEVICE_TEST_GUIDE.md` | Stesso scopo (checklist test su device fisico), due nomi. |
| `REAL_DEVICE_TEST_GUIDE.md` | **MERGE** (vedi sopra) | — |
| `FINAL_PHONE_INSTALL_AUDIT.md` | **ARCHIVE** (mai committato) | Checkpoint puntuale (tunnel + install telefono). |
| `REAL_PHONE_BETA_READY.md` | **ARCHIVE** (mai committato) | Checkpoint puntuale. |

## 5. Cluster Control Room (5 file)

| File | Verdetto | Motivazione |
|---|---|---|
| `CONTROL_ROOM_IMPLEMENTATION_PLAN.md` | **ARCHIVE** | Piano tecnico pre-implementazione, superato. |
| `CONTROL_ROOM_READINESS.md` | **ARCHIVE** | Report di completamento, superato da `PROJECT_FREEZE_STATE.md` §5 (stato verificato corrente). |
| `CONTROL_ROOM_OPERATOR_GUIDE.md` | **MOVE** → `docs/customer/` (mai committato) | **Attivo e utile** — guida operativa non tecnica, non uno snapshot. |
| `PROFESSIONAL_DASHBOARD_PLAN.md` | **ARCHIVE** | Piano per la Dashboard tenant/staff (sistema distinto dal Control Room), superato. |
| `PROFESSIONAL_DASHBOARD_READINESS.md` | **ARCHIVE** | Report di completamento, superato. |

## 6. Cluster Environment setup (4 file, quasi-duplicati)

| File | Verdetto | Motivazione |
|---|---|---|
| `ENVIRONMENT_GUIDE.md` | **KEEP** come base della fusione → `docs/developer/ENVIRONMENT_GUIDE.md` | Matrice ambienti (LOCAL/STAGING/BETA/PROD) — è il documento di riferimento più generale. |
| `ENVIRONMENT_SETUP.md` | **MERGE** in `ENVIRONMENT_GUIDE.md` | Si dichiara esso stesso "complementare" al file precedente. |
| `ENVIRONMENT_FINAL.md` | **MERGE** in `ENVIRONMENT_GUIDE.md` | Matrice "finale" ridotta (solo STAGING/BETA) — contenuto sovrapposto, non nuovo. |
| `REAL_BETA_ENVIRONMENT.md` | **MERGE** in `ENVIRONMENT_GUIDE.md` | Si dichiara esso stesso "estensione" dei due file precedenti con dettagli build-machine. |
| — | **Raccomandazione** | 4 file che si auto-referenziano a vicenda come "estensione l'uno dell'altro" sono per definizione un candidato a fusione — nessuna perdita di informazione nel consolidarli in un unico documento versionato. |

## 7. Cluster "FINAL_*" freeze/readiness (duplicato confermato testualmente)

| File | Verdetto | Motivazione |
|---|---|---|
| `FINAL_PRODUCT_READINESS_AUDIT.md` | **KEEP** come base → `docs/audit/history/` | — |
| `FINAL_TECHNICAL_FREEZE_REPORT.md` | **DELETE** | Il file **dichiara nel proprio testo** di essere identico a `FINAL_PRODUCT_READINESS_AUDIT.md` ("Identico nel contenuto a..."). Non è un merge, è un duplicato letterale auto-confermato — non ha senso nemmeno archiviarlo due volte. |
| `FINAL_RELEASE_DECISION.md` | **MOVE** → `docs/release/` | Decisione GO/NO-GO, valore storico/decisionale distinto, non un duplicato. |
| `ARCHITECTURE_FINAL_REVIEW.md` | **MOVE** → `docs/architecture/` | Review architetturale con criterio proprio (scalabilità a 1000 tenant) — non sovrapposto agli altri, valore permanente come architecture review. |

## 8. Cluster MVP / business readiness (2 ondate)

| File | Verdetto | Motivazione |
|---|---|---|
| `MVP_PRODUCTION_READINESS_REPORT.md` | **ARCHIVE** | Audit storico "vendibile domani?", superato da audit più recenti e più verificati. |
| `MVP_CLIENT_RELEASE_CHECKLIST.md` | **MOVE** → `docs/release/` | Checklist riutilizzabile per future release, non solo uno snapshot. |
| `APP_STORE_READINESS.md` | **MOVE** → `docs/release/` | Checklist compliance store, riutilizzabile a ogni submission — non uno snapshot puntuale. |
| `BUSINESS_OPERATING_AUDIT.md` | **ARCHIVE** (mai committato) | Audit puntuale "questo PC può essere centro operativo?" — risposta legata a una macchina specifica in un momento specifico. |
| `BUSINESS_READY_REPORT.md` | **ARCHIVE** (mai committato) | Report di chiusura dello stesso audit. |

## 9. Cluster Signing / security

| File | Verdetto | Motivazione |
|---|---|---|
| `SIGNING_SETUP.md` | **MOVE** → `docs/developer/` | Runbook riutilizzabile (creazione keystore), non uno snapshot. |
| `ANDROID_SIGNING_FINAL.md` | **ARCHIVE** (mai committato) | Snapshot "stato verificato" — sovrapposto a `SIGNING_SETUP.md`, **stesso problema di `SIGNED_APK_READY.md`** (sezione 4): descrive uno stato di firma release che il codice attuale contraddice. Archiviare con nota di superamento. |
| `FIREBASE_CONFIGURATION_REQUIRED.md` | **MOVE** → `docs/developer/` | **Ancora vero oggi** — `PROJECT_FREEZE_STATE.md` §3 conferma che i file di config Firebase nativi mancano tuttora. Documento operativo attivo, non storico. |
| `PUSH_NOTIFICATION_READINESS.md` | **ARCHIVE** | Report di implementazione, contenuto ormai assorbito da `PROJECT_FREEZE_STATE.md` §1/§3. |
| `BACKUP_RECOVERY_GUIDE.md` | **MOVE** → `docs/operations/runbooks/` (mai committato) | Runbook attivo, referenzia script (`backup-control-center.sh`) tuttora presenti. |

## 10. Cluster Deployment / release-process

| File | Verdetto | Motivazione |
|---|---|---|
| `DEPLOYMENT_READY.md` | **MOVE** → `docs/deployment/` (mai committato) | Comandi tunnel/deploy — riutilizzabile, non solo storico. |
| `RELEASE_PROCESS.md` | **MOVE** → `docs/deployment/` | Processo riutilizzabile (versioni/rollback via Control Room). |
| `BETA_RELEASE.md` | **MOVE** → `docs/release/` | Architettura di distribuzione (Firebase App Distribution/TestFlight) — reference attiva. |
| `BETA_TESTING_GUIDE.md` | **MOVE** → `docs/customer/` | Guida per tester esterni — riutilizzabile a ogni ciclo beta. |
| `BETA_DEBUG_RUNBOOK.md` | **MOVE** → `docs/operations/runbooks/` | Troubleshooting riutilizzabile. |

## 11. Documenti di stato whole-repository (2 file recenti)

| File | Verdetto | Motivazione |
|---|---|---|
| `REAL_PROJECT_STATE.md` | **KEEP** (radice) | Fonte di verità corrente, generata in questa stessa linea di audit — resta alla radice per visibilità immediata (vedi `PROJECT_STRUCTURE.md` §2). |
| `PROJECT_FREEZE_STATE.md` | **KEEP** (radice) | Idem — congelamento complementare (per-modulo/cartella/comando/schermata). |
| — | **Osservazione** | Questi due file si sovrappongono per ~60% del contenuto (entrambi sono "stato reale verificato da codice", con taglio leggermente diverso). Non sono un duplicato da eliminare — hanno taglio diverso e complementare — ma **in futuro dovrebbero essere un solo documento vivo aggiornato incrementalmente**, non due snapshot paralleli generati a 45 minuti di distanza. Vedi `PROJECT_STANDARD.md` §Documentazione. |

## 12. File standalone

| File | Verdetto | Motivazione |
|---|---|---|
| `TECH_STATUS_REPORT.md` | **MOVE** → `docs/business/` | Analisi propedeutica a roadmap prodotto, non uno snapshot tecnico da superare. |
| `PRODUCT_DESIGN_ROADMAP.md` | **MOVE** → `docs/business/` | Roadmap UX/UI attiva. |
| `GIUFFRIDA_FEATURE_GAP.md` | **MOVE** → `docs/business/` | Analisi competitiva, riferimento persistente. |
| `SCREEN_ANALYSIS.md` | **MOVE** → `docs/legacy/` | Analisi legata alle 17 immagini di riferimento (vedi §14) — completata, valore storico/di analisi. |
| `MASTER_HANDOVER_FABLE5.md` | **ARCHIVE** → `docs/legacy/` | Documento di passaggio tra sessioni — valore storico puro. |
| `PREVIEW_ACCESS_GUIDE.md` | **MOVE** → `docs/developer/` | Guida attiva per avviare/provare il progetto in locale — usata proprio in questa sessione. |
| `BACKEND_RUNTIME_STATUS.md` | **DELETE** | Diagnosi one-off di un problema di shell risolto in una sessione specifica — nessun valore residuo, non riutilizzabile, non storicamente significativo. |

## 13. File non-markdown alla radice

| File | Verdetto | Motivazione |
|---|---|---|
| `START_CONTROL_CENTER.command` | **KEEP** (radice) | Launcher operativo attivo, usato per avviare il progetto — corretto che resti facilmente raggiungibile alla radice. |
| `backup-control-center.sh` | **MOVE** → `platform-infra/bin/` | Script operativo — più coerente vicino a `deploy.sh` che alla radice del repo. |
| `stop-control-center.sh` | **KEEP** (radice) | Compagno diretto di `START_CONTROL_CENTER.command` — deve restare accanto per uso pratico immediato. |

## 14. Le 17 immagini `IMG_97xx.HEIC`

| File | Verdetto | Motivazione |
|---|---|---|
| `IMG_9740.HEIC` … `IMG_9756.HEIC` (17 file, ~3.1 MB, **committati in git**) | **ARCHIVE** (fuori da git) | Sono screenshot di un'app di terze parti ("Giuffrida Barber"), usati una sola volta come materiale sorgente per `SCREEN_ANALYSIS.md`. La cartella `.screens-analysis/` (già `.gitignore`d) contiene già le versioni JPEG derivate usate per l'analisi — le HEIC originali sono ridondanti e, trattandosi di materiale di proprietà di terzi esplicitamente dichiarato come "non riproducibile" nello stesso `SCREEN_ANALYSIS.md`, **non dovrebbero essere in git**. Raccomandazione: spostarle fuori dal repository tracciato (es. in un percorso locale ignorato, coerente con `.screens-analysis/`) — la loro presenza in git aggiunge ~3.1 MB di storia che non si può ridurre senza riscrivere la cronologia. |

---

## Riepilogo quantitativo

| Verdetto | Conteggio file |
|---|---|
| KEEP (resta dov'è) | 4 (`REAL_PROJECT_STATE.md`, `PROJECT_FREEZE_STATE.md`, `START_CONTROL_CENTER.command`, `stop-control-center.sh`) + `FINAL_PRODUCT_READINESS_AUDIT.md`/`ENVIRONMENT_GUIDE.md` come basi di merge |
| MOVE | 24 file verso sottocartelle `docs/*` o `platform-infra/bin/` |
| MERGE | 8 file → 3 documenti consolidati (runbook provisioning, device-test guide, environment guide) |
| ARCHIVE | ~38 file → `docs/audit/history/` o `docs/legacy/` (valore storico, non normativo) |
| DELETE | 2 file (`FINAL_TECHNICAL_FREEZE_REPORT.md` — duplicato letterale auto-dichiarato; `BACKEND_RUNTIME_STATUS.md` — diagnosi one-off esaurita) |
| Fuori git (immagini) | 17 file HEIC |

**Nessuna di queste azioni è stata eseguita.** Questo documento è l'elenco delle raccomandazioni da eseguire nella fase "Immediate" di `PROJECT_EVOLUTION_ROADMAP.md`, previa autorizzazione esplicita.
