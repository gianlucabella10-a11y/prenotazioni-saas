# FLEET_OPERATIONS — Da "un cliente alla volta" a operazioni di massa

> Ogni operazione oggi richiede di aprire il singolo cliente/app. Per ognuna: già possibile / implementabile / non consigliata — con motivazione, non per default prudenziale.

| Operazione | Stato | Motivazione |
|---|---|---|
| **Aggiorna tutte le app / Ricostruisci APK** (in massa) | ✅ **Già possibile** *(implementata in questa sessione)* | `/control-room/apps/flotta` → "Ricostruisci flotta stale", riusa `BuildFleet`+`BuildService`, nessuna nuova build-logic |
| **Backup** | ✅ **Già possibile** *(sessioni precedenti + automazione in questa sessione)* | Pulsante manuale + schedulazione automatica giornaliera con retention |
| **Rigenera manifest** (in massa, per tutte le app già buildabili) | Implementabile | Stesso pattern del rebuild fleet (`PrepareApp`/`GenerateAppPackage` già esistono per singola app) — non implementata in questa sessione, prossimo candidato naturale se serve rigenerare manifest prima di una build di massa |
| **Rigenera assets** (icone/splash per tutti i tenant) | Implementabile, basso valore quotidiano | Utile solo dopo un cambio di formato/dimensioni delle icone stesse (evento raro) — non un'operazione quotidiana, quindi bassa priorità |
| **Elimina build obsolete** (pulizia storage) | Implementabile, rischio moderato | Richiede attenzione: non cancellare artefatti ancora referenziati da un link beta attivo — fattibile ma va progettata con cura, non fatta in questa sessione |
| **Rollback** (build/APK, non solo asset brand) | Implementabile, richiede nuova logica di dominio | Oggi esiste solo rollback degli asset brand. Un vero rollback build richiederebbe definire cosa significa "build corrente" oltre alla cronologia già tracciata — è più vicino a una feature nuova che a un'automazione di superficie, per questo non implementata qui |
| **Rigenera logo** (di massa) | ❌ **Non applicabile concettualmente** | Il logo è specifico per singolo cliente — non esiste un "rigenera tutti i loghi" sensato (rigenerarlo significherebbe cambiarlo, non è un'operazione tecnica di manutenzione) |
| **Invia email di massa** (a tutti/gruppo di clienti) | ❌ **Non consigliata in questa fase** | Si avvicina a una funzionalità di marketing/comunicazione commerciale, non un'automazione operativa — fuori dal mandato esplicito di queste sessioni ("non funzionalità commerciali"), e introduce rischio di abuso senza un sistema di consenso/opt-out |
| **Invia notifica push di massa** | ❌ **Non consigliata in questa fase** | Stesso motivo — tocca utenti finali (clienti dei clienti), non la relazione Founder↔tenant; è prodotto, non operatività |
| **Pulizia cache** | ❌ **Non consigliata come pulsante** | La cache applicativa (`TenantRegistry`, TTL 300s) si autoinvalida da sola; un pulsante che la svuota per tutti i tenant contemporaneamente rischia solo di causare un picco di query al database senza un beneficio reale |
| **Riavvia queue / Riavvia worker** | ❌ **Non consigliata da un pulsante web** | Richiede privilegi di sistema (systemd) che l'applicazione Laravel non dovrebbe avere per design — un bug in quel pulsante potrebbe interrompere build in corso per tutta la flotta (`ZERO_MANUAL_OPERATIONS_AUDIT.md`, invariato) |
| **Archivia clienti** (di massa, su selezione arbitraria) | ❌ **Non consigliata** | L'azione singola esiste ed è corretta (irreversibile, con conferma). Farla in massa aumenta drasticamente il rischio di un errore catastrofico (selezionare i clienti sbagliati) per un'operazione che non ha quasi mai un motivo legittimo di essere fatta in blocco |
| **Disattiva/sospendi clienti** (di massa) | Implementabile ma senza un trigger reale oggi | Tecnicamente più sicura dell'archiviazione (reversibile), ma non esiste nel sistema alcun criterio automatico per "quali clienti sospendere in massa" (nessuna fatturazione, quindi nessun concetto di "scaduto") — resta implementabile ma priva di un caso d'uso finché non esiste billing |

## Le uniche 2 già rese possibili in questa linea di sessioni

**Ricostruzione di massa della flotta** e **backup** sono le uniche due operazioni di questa lista che soddisfano contemporaneamente: logica già esistente per il singolo caso, nessun rischio di danno catastrofico se sbagliate, beneficio quotidiano reale. Ogni altra voce fallisce almeno uno di questi tre criteri — le "non consigliate" non lo sono per prudenza generica, ma per un motivo specifico riportato accanto a ciascuna.
