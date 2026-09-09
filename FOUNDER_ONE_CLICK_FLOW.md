# FOUNDER_ONE_CLICK_FLOW — Dal login alla consegna APK

> Flusso reale, verificato contro le route e i controller esistenti (incluse le 3 azioni aggiunte in questa sessione). Conteggio click onesto — non arrotondato per ottimismo.

```
1. Login Control Room                          1 pagina (email+password) + MFA se attiva
2. "+ Nuovo cliente"                            1 click dalla home
3. Compila form (nome, settore, email, piano)   1 submit
4. Carica logo (dalla scheda cliente appena creata)   1 upload + 1 submit
5. "Genera"                                     1 click
6. "Build"                                      1 click
7. Attendi compilazione                         0 click (stato aggiornato nella stessa pagina)
8. "Link beta"                                   1 click (sulla build completata)
9. Invia il link al cliente                      fuori dal sistema (copia/incolla)
```

**Totale: 8 click + 2 submit di form, dal login alla consegna di un link scaricabile.** Nessun passaggio da terminale — confermato dall'audit di questa sessione (`ZERO_MANUAL_OPERATIONS_AUDIT.md`).

## Cosa rende il flusso più lungo di quanto potrebbe essere

| Passaggio | Perché non è 1 click | Sarebbe risolvibile? |
|---|---|---|
| 3-4 (form + upload separati) | Creazione tenant e brand sono due form distinti per design (il brand può arrivare dopo, da titolare o operatore) | Sì, con un form "crea e brandizza" unico — ma unirebbe due responsabilità diverse, non è chiaramente un miglioramento |
| 5-6 (Genera poi Build, due click separati) | `BuildService` richiede esplicitamente che il manifest sia già generato prima di una build | Sì — un solo pulsante "Genera e compila" che concatena le due azioni è implementabile senza rischio (`AUTOMATION_CATALOG.md` #11, priorità bassa) |
| 7 (attesa compilazione) | Compilazione reale (`flutter build apk`), richiede minuti | No, è un limite fisico, non di UX |
| 9 (invio link fuori sistema) | Nessun canale di comunicazione integrato (email/SMS al cliente) | Sì in teoria, ma sarebbe una funzionalità commerciale nuova, fuori scope per una sessione di "solo automazioni operative" |

## Cosa NON serve più fare (eliminato in questa sessione)

- ~~Aprire un terminale per eseguire `backup-control-center.sh`~~ → ora "Backup ora" in `/control-room/backup`.
- ~~Interrogare il database per capire cosa è successo~~ → ora `/control-room/audit`.
- ~~Nessun modo di chiudere definitivamente un cliente~~ → ora "Archivia" nella scheda cliente.

## Flusso operativo quotidiano (non solo onboarding), aggiornato

```
Login → Home (lista clienti)
  ├─ "Backup ora" (1 click, quando serve — nessuna cadenza automatica ancora, vedi TECHNICAL_ROADMAP.md Next)
  ├─ "Audit" (1 click, per verificare cosa è successo)
  └─ Scheda cliente → Build / Beta / Tester / Archivia (secondo necessità)
```

Il Founder oggi può completare un intero ciclo — dalla creazione di un cliente alla consegna dell'APK, passando per backup e verifica audit — **senza mai aprire un editor di codice o un terminale**, con l'unica eccezione delle operazioni che restano deliberatamente tecniche (`ZERO_MANUAL_OPERATIONS_AUDIT.md`): migration, deploy, segreti, keystore.
