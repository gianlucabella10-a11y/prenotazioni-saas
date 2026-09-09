# FOUNDER_EXPERIENCE_AUDIT — Operazioni ancora tecniche

> Aggiorna `ZERO_MANUAL_OPERATIONS_AUDIT.md` (sessione precedente) con lo stato **dopo** l'implementazione fatta in questa sessione (cruscotto operativo + log viewer). Punto di vista: un Founder che non sa programmare.

## CRITICHE (bloccano l'uso quotidiano se il Founder dovesse farle da solo)

| Operazione | Stato |
|---|---|
| Avvio/arresto della piattaforma (`START_CONTROL_CENTER.command`) | 🟡 Zero digitazione richiesta (doppio click), ma su macOS un file `.command` apre comunque una finestra Terminal — non è "aprire un terminale" nel senso di scrivere comandi, ma la finestra è visibile. Non risolto in questa sessione (richiederebbe un launcher nativo, fuori scope per "nessuna nuova architettura") |
| Diagnosi di un errore applicativo generico (non di build) | ✅ **Risolta in questa sessione** — `/control-room/logs`, sola lettura, nessun terminale |
| Sapere se la piattaforma è "in salute" senza controllare a occhio ogni pagina | ✅ **Risolta in questa sessione** — cruscotto `/control-room` con alert espliciti |

## IMPORTANTI (friction reale ma non giornaliera)

| Operazione | Stato |
|---|---|
| Deploy di una nuova versione del backend | 🔧 Resta tecnica, per design — un pulsante diretto rimuoverebbe l'unico controllo umano prima di toccare produzione |
| Setup iniziale keystore/firma Android | 🔧 Resta tecnica — segreto, mai un percorso UI |
| Configurazione Firebase (push notification) | 🔧 Resta tecnica — richiede accesso a una console esterna (Firebase), non è automatizzabile dal codice di questo repository |
| Bootstrap del primo super-admin (`control-room:create-admin`) | 🔧 Resta tecnica — problema dell'uovo e della gallina, ma è **una tantum per ambiente**, non un'operazione ricorrente |
| Migration del database | 🔧 Resta tecnica per design — mai un pulsante, rischio troppo alto |

## OPZIONALI (edge case, raramente incontrate da un Founder)

| Operazione | Stato |
|---|---|
| `adb install` diretto su device | Il link beta già copre la distribuzione normale — `adb` serve solo per debug hardware diretto, mai nel flusso quotidiano |
| `cloudflared`/tunnel | Serve solo se il backend non è già raggiungibile pubblicamente (caso di sviluppo/demo locale, non produzione con dominio reale) |
| Git | Mai necessario per l'operatività — solo per chi modifica il codice |

## Sintesi

Delle operazioni **CRITICHE** identificate a inizio sessione, 2 su 3 sono ora chiuse (log viewer, cruscotto/health check). L'unica rimasta (avvio via file `.command` che apre una finestra Terminal visibile) è un limite della piattaforma macOS, non del codice applicativo — risolverla richiederebbe un launcher nativo (menu bar app o simile), esplicitamente fuori scope per questa sessione ("non introdurre nuova architettura"). Tutte le operazioni **IMPORTANTI** restano deliberatamente tecniche per ragioni di sicurezza o di natura strutturale (segreti, schema DB, deploy) — non sono gap, sono confini corretti.
