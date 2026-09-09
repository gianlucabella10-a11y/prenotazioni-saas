# CEO_DASHBOARD — Cosa apre il Founder ogni mattina

> Estende `FOUNDER_COMMAND_CENTER.md` (taglio operativo: "va tutto bene tecnicamente?") con il taglio business di questa sessione: "va bene anche commercialmente?". Il cruscotto tecnico esiste già (implementato in due sessioni precedenti + l'aggiunta rinnovi di questa sessione); qui si specifica come dovrebbe integrarsi la vista business, con lo stesso principio "solo dati reali, nessuna finzione".

## I due livelli che un CEO/Founder deve vedere, distinti

```
┌───────────────────────────────────────────────────────────┐
│  LIVELLO 1 — Il prodotto funziona?         (già implementato) │
│  🟢/🟡/🔴 + backup, coda, build, disco                        │
├───────────────────────────────────────────────────────────┤
│  LIVELLO 2 — L'azienda sta bene?           (nuovo in questa   │
│                                              sessione: solo    │
│                                              rinnovi, dato     │
│                                              già esistente)    │
│  N clienti attivi · N in scadenza (7gg) · N scaduti           │
└───────────────────────────────────────────────────────────┘
```

## Cosa mostra oggi, realmente (dopo questa sessione)

- Clienti per stato (attivi/in attivazione/sospesi/archiviati) — già esistente
- **Abbonamenti in scadenza nei prossimi 7 giorni** e **abbonamenti scaduti** — nuovo in questa sessione, dato reale da `subscriptions.current_period_end`
- Salute tecnica (coda, disco, backup, build fallite) — sessioni precedenti
- Flotta (build stale, aggregati) — sessioni precedenti

## Cosa un vero CEO Dashboard vorrebbe, ma NON è ottenibile onestamente oggi

| Vorrebbe vedere | Perché non è ottenibile | Riferimento |
|---|---|---|
| Fatturato/MRR | Zero integrazione di pagamento — nessun dato di incasso esiste nel sistema | `BUSINESS_OS_AUDIT.md` |
| Tasso di abbandono (churn) | Richiederebbe storicizzare le transizioni a `terminated` nel tempo con motivazione — oggi solo lo stato attuale è tracciato, non un log analitico dedicato | `BUSINESS_OS_AUDIT.md` |
| Ticket di supporto aperti | Nessun sistema di ticketing esiste | `BUSINESS_OS_AUDIT.md` |
| Lead in pipeline | Nessun CRM — il ciclo di vita tracciato parte da `Tenant`, non da `Lead` (`PLATFORM_LIFECYCLE.md` fase 1) | `BUSINESS_OS_AUDIT.md` |
| Soddisfazione cliente (NPS/simili) | Nessuna raccolta di questo tipo di dato esiste | `BUSINESS_OS_AUDIT.md` |

**Principio seguito**: non fingere questi dati con placeholder o stime — ogni riga sopra è dichiarata assente piuttosto che simulata, coerente con "non inventare" richiesto in ogni sessione di questa linea di audit.

## La vista realistica, oggi

Un Founder che apre la piattaforma stamattina vede, in ordine di priorità:
1. Se il prodotto tecnico ha bisogno di attenzione immediata (livello 1).
2. Se ci sono clienti con l'abbonamento in scadenza/scaduto di cui occuparsi commercialmente (livello 2, nuovo).
3. Il resto (flotta, clienti recenti, build recenti) come dettaglio consultabile, non come prima cosa da leggere.

**Non vede** (perché non esiste, non perché nascosto): fatturato, churn, pipeline commerciale, supporto. Questo dashboard è onesto sul fatto che oggi risponde bene alla domanda "il prodotto sta bene?" e solo parzialmente alla domanda "l'azienda sta bene?" — la seconda richiede i moduli progettati in `BUSINESS_OS_AUDIT.md`, non ancora costruiti.
