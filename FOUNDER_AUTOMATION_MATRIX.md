# FOUNDER_AUTOMATION_MATRIX — Automatizzabile / nascondibile / tecnica / pulsante

| Operazione | Automatizzabile? | Nascondibile? | Deve restare tecnica? | Pulsante Control Room? |
|---|---|---|---|---|
| Avvio backend/worker/scheduler | ✅ Già systemd in produzione | 🟡 Solo parzialmente (finestra Terminal visibile su macOS in locale) | Sì, il processo che ospita la Control Room non può auto-avviarsi da sé | ❌ Impossibile per definizione |
| Diagnosi errori applicativi | No (la lettura resta umana) | — | No | ✅ **Fatto** — `/control-room/logs` |
| Salute piattaforma (coda, disco, backup) | ✅ Il calcolo è automatico | — | No | ✅ **Fatto** — cruscotto `/control-room` |
| Creazione cliente | Parziale (il trigger resta umano per scelta) | — | No | ✅ Già esistente |
| Genera + Build | Parziale (rebuild flotta) | — | No | ✅ Già esistente |
| Backup | ✅ Automatizzabile a scheduling | — | No | ✅ Già esistente (sessione precedente) |
| Terminazione/archiviazione cliente | No, decisione umana | — | No | ✅ Già esistente (sessione precedente) |
| Audit log | No (lettura) | — | No | ✅ Già esistente (sessione precedente) |
| Deploy backend | 🟡 Parziale (CI con gate) | No | ✅ Sì | ❌ Non implementabile in sicurezza senza gate |
| Migration DB | ❌ Mai | No | ✅ Sì, sempre | ❌ Mai |
| Segreti (.env, keystore) | ❌ Mai | ✅ Sì (non devono avere percorso UI) | ✅ Sì | ❌ Mai |
| Config Firebase | ❌ Richiede console esterna | No | ✅ Sì | ❌ Non dipende dal codice di questo repository |
| Bootstrap primo super-admin | ❌ Una tantum, per design | No | ✅ Sì | ❌ Problema dell'uovo e della gallina |
| Build queue aggregata (tutti i tenant) | ✅ Sì | — | No | 🟡 Parziale — il cruscotto ora mostra le ultime 8 build, non una coda filtrata/gestibile completa |
| Rollback build/APK | ✅ Sì (funzionalità nuova) | — | No | ⏸️ Non implementata (richiederebbe nuova logica applicativa, non solo esposizione — fuori scope "solo automazioni") |
| Health-check toolchain build machine (Flutter/Android SDK) | ✅ Sì | — | No | ⏸️ Non implementata (richiederebbe eseguire `flutter --version` come sottoprocesso — nuova superficie, valutata a rischio/beneficio non prioritaria in questa sessione) |

## Le 2 automazioni scelte per l'implementazione (oltre alle 3 della sessione precedente)

1. **Log viewer** — soddisfaceva tutti i criteri: la logica di lettura file è banale, sola lettura (zero rischio), e chiudeva l'ultima diagnosi CRITICA rimasta identificata nella sessione precedente.
2. **Cruscotto operativo** — trasforma la home da semplice lista clienti a una vista che risponde "cosa devo fare adesso?", componendo solo dati già esistenti (job, build, tenant, backup, disco) senza alcuna nuova tabella o stato persistito.
