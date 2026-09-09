# PLATFORM_LIFECYCLE — Ciclo di vita completo di un cliente

> Le fasi già coperte da `BUSINESS_FLOW.md` (Tenant→Prenotazione) sono qui riassunte con riferimento, non riscritte — questo documento aggiunge le fasi **prima** (Lead, vendita) e **dopo** (supporto, versione successiva, archiviazione) che `BUSINESS_FLOW.md` non copriva perché fuori dal perimetro tecnico stretto.

```
1. LEAD
   Contatto commerciale, fuori dal sistema (nessun CRM integrato — gap, vedi CONTROL_ROOM_ROADMAP.md "Fatturazione"/"Licenze")
        ↓
2. CLIENTE
   Accordo commerciale chiuso, fuori dal sistema (nessuna fatturazione automatizzata oggi)
        ↓
3. TENANT
   Platform Admin crea il tenant da Control Room → ProvisionTenant::execute()
   (dettaglio tecnico: BUSINESS_FLOW.md passaggi 2-3)
        ↓
4. CONFIGURAZIONE
   Titolare (via invito) o Platform Admin per suo conto: orari, servizi, operatori
   dalla Dashboard (`/dashboard`)
        ↓
5. LOGO / BRAND
   Caricamento logo, scelta colori → asset generati (icone/splash/store) via GD
   (BUSINESS_FLOW.md passaggio 4)
        ↓
6. BUILD
   "Genera" → "Build" da Control Room → APK reale compilato
   (BUSINESS_FLOW.md passaggi 5-7)
        ↓
7. APK
   Prodotto, checksum, salvato per tenant/versione
        ↓
8. INSTALLAZIONE
   Link beta (interno) o store — utente finale installa
        ↓
9. AGGIORNAMENTO
   🔴 GAP: nessun meccanismo di forced-update — un aggiornamento oggi significa
   rigenerare manualmente pacchetto+build e ridistribuire lo stesso link/canale,
   non un push automatico ai device già installati (TECHNICAL_DEBT.md #2, #20)
        ↓
10. SUPPORTO
    🔴 GAP: nessun canale di supporto strutturato nel sistema — il feedback
    dell'utente finale arriva (POST /me/feedback) ma è solo consultabile,
    non gestibile/tracciabile come ticket (CONTROL_ROOM_ROADMAP.md "Feedback")
        ↓
11. VERSIONE SUCCESSIVA
    Ripete i passaggi 5-9 (nuovo brand/servizi → nuova build → nuova distribuzione)
    Nessun automatismo di "rilascio pianificato" — ogni ciclo è un'azione manuale
        ↓
12. ARCHIVIAZIONE
    🔴 GAP: il modello dati prevede lo stato `Terminated` (TenantStatus enum),
    ma NESSUNA azione di Control Room lo raggiunge — un cliente che cessa
    il servizio oggi resta tecnicamente "sospeso", non archiviato
    (CONTROL_ROOM_ROADMAP.md "Terminazione/archiviazione cliente")
```

## Le 3 fasi realmente scoperte (gap operativi, non di dominio)

| Fase | Gap | Perché non è un problema di codice core |
|---|---|---|
| 9. Aggiornamento | Nessun forced-update | La tabella `app_versions` esiste già — serve solo collegarla, non progettarla da zero |
| 10. Supporto | Nessun ticketing | Il feedback arriva già al sistema — serve solo una schermata di gestione, non un nuovo canale di raccolta |
| 12. Archiviazione | Nessuna azione UI | Lo stato `Terminated` esiste già nell'enum — serve solo esporre il pulsante e il flusso di conferma |

Tutti e tre confermano lo stesso pattern osservato in `CONTROL_ROOM_ROADMAP.md`: **il dato/la logica esistono nel backend, manca solo l'esposizione operativa in Control Room.** Nessuna di queste tre lacune richiede di toccare il core.
