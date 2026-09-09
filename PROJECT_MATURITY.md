# PROJECT_MATURITY — Livello di maturità

> Coerente con `PROJECT_SCORE.md` (73/100 medio) e la valutazione CTO in `REAL_PROJECT_STATE.md` (7/10) — questo documento traduce quei numeri in un modello di maturità a stadi, con criteri espliciti per ognuno.

## Prototype — ✅ Superato

**Raggiunto**: dimostra che l'idea funziona end-to-end (un cliente può essere creato, brandizzato, un APK reale compilato). **Non manca nulla** a questo livello — il sistema lo supera ampiamente.

## Alpha — ✅ Superato

**Raggiunto**: funzionalità core stabili e testate (multi-tenancy isolata con test reali, booking engine con gestione di concorrenza, auth JWT+MFA). Non è più "funziona in condizioni ideali" — ci sono test che verificano condizioni avverse (cross-tenant probing, DST, no-show, cancellazioni). **Non manca nulla** a questo livello.

## Beta — ✅ Sostanzialmente raggiunto

**Raggiunto**: distribuzione beta reale e funzionante (link revocabili, scadenza, limite download, gestione tester, raccolta feedback), documentazione ora organizzata per un onboarding reale (< 30 minuti). Il prodotto è usabile da persone esterne al team di sviluppo.

**Manca ancora, per chiudere completamente questo stadio**: firma release affidabile (oggi ricade su debug silenziosamente) — una beta distribuita con firma debug non è distinguibile, per l'utente, da un artefatto di sviluppo.

## Release Candidate — 🟡 Parzialmente raggiunto

**Raggiunto**: pipeline CI funzionante (test automatici su ogni push), processo di build riproducibile, checklist di rilascio già scritte (`docs/Release/`).

**Manca**:
- Versioning APK reale (`versionCode`/`versionName` statici oggi, `TECHNICAL_DEBT.md` #2) — un Release Candidate deve poter essere identificato univocamente da ciò che gira su un device.
- Firma release affidabile per default, non opt-in silenzioso.
- Test end-to-end della pipeline di build reale (oggi nessun test esercita `flutter build apk` per intero).
- `config/cors.php` esplicito (oggi wide-open per omissione, non per scelta consapevole).

## Production Ready — 🟡 Parzialmente raggiunto

**Raggiunto**: infrastruttura Terraform reale e funzionante per un ambiente "pilota" (sicurezza di rete corretta: RDS non pubblico, cifratura at-rest, IMDSv2), script di deploy idempotente, backup disponibile (seppur manuale).

**Manca**:
- Un ambiente di staging distinto dalla produzione.
- Monitoraggio applicativo (oggi solo Sentry opzionale, nessuna vista di salute del sistema).
- Backup automatico con verifica (oggi manuale, dipende dalla memoria umana — `AUTOMATION_CATALOG.md` #1-2).
- Procedura di ripristino verificata con un test reale (esiste la guida, non risulta un ripristino di prova eseguito e documentato).
- Test di architettura che garantiscano i confini tra moduli (oggi rispettati per disciplina, non per garanzia strutturale).

## Enterprise — 🔴 Non ancora raggiunto

**Raggiunto, parzialmente**: documentazione ora a livello enterprise (categorizzata, indicizzata, standard scritti — questa stessa linea di sessioni lo ha costruito), struttura di ruoli concettualmente definita (`PLATFORM_ROLES.md`), anche se non tutti implementati tecnicamente.

**Manca, sostanzialmente**:
- **Scalabilità infrastrutturale reale**: 1 istanza EC2 + 1 RDS micro non regge un carico enterprise (`REAL_PROJECT_STATE.md` §Fase 9).
- **Permessi granulari**: oggi solo 4 ruoli fissi, nessun modello di permessi fine-grained, nessuna distinzione tecnica tra Founder e Platform Admin.
- **Fatturazione/licensing**: assenti del tutto — un requisito enterprise tipico.
- **Monitoraggio e alerting proattivo**: definiti concettualmente (`FOUNDER_DASHBOARD.md`) ma non costruiti.
- **SLA/uptime formalizzati**: nessun impegno di disponibilità tracciato o misurato.
- **Multi-regione/disaster recovery**: nessuna ridondanza geografica, nessun piano DR testato oltre un backup manuale.
- **Audit trail completo e consultabile**: i dati esistono (`audit_logs`) ma non sono esposti in nessuna interfaccia.

## Posizione attuale sintetica

```
Prototype ──✅── Alpha ──✅── Beta ──✅(quasi)── Release Candidate ──🟡── Production Ready ──🟡── Enterprise ──🔴
                                        ↑
                              LA PIATTAFORMA È QUI OGGI
```

Il sistema ha superato con margine gli stadi che dipendono dalla **qualità del codice core** (Prototype, Alpha, quasi Beta). Gli stadi successivi (Release Candidate → Enterprise) dipendono quasi interamente da **maturità operativa e infrastrutturale**, non da nuove funzionalità di prodotto — coerente con ogni audit precedente in questa linea di sessioni.
