# COMPANY_OPERATING_MANUAL — Come funziona l'azienda

> Non il codice — l'azienda. Chi fa cosa, quando, perché, con quali strumenti. Riflette lo stato reale di oggi (un'azienda gestibile da una persona) e la struttura a cui tende quando crescerà (vedi `TEAM_ROLES.md`).

## Cosa vende l'azienda

Un'app di prenotazione brandizzata per professionisti che lavorano su appuntamento (barbieri, saloni, centri estetici, studi sanitari). Il cliente paga per avere la propria app, senza doverla far sviluppare da zero — la piattaforma la genera.

## Chi fa cosa, oggi

Con un solo Founder, tutto passa da una persona e da un solo strumento (Control Room):

| Attività | Quando | Perché | Strumento |
|---|---|---|---|
| Onboarding nuovo cliente | Alla vendita chiusa | È il primo passo che sblocca tutto il resto | Control Room → Clienti |
| Generazione e build app | Dopo che il cliente ha fornito il brand | Produce l'artefatto da consegnare | Control Room → Apps |
| Distribuzione | Dopo build completata | Consegna il valore al cliente | Link beta / store |
| Controllo mattutino | Ogni giorno, appena acceso il PC | Unico modo di sapere se qualcosa richiede attenzione (il sistema non manda notifiche proattive) | Cruscotto `/control-room` |
| Backup | Automatico ogni notte | Rete di sicurezza contro perdita dati | Schedulato (nessuna azione umana necessaria da questa sessione) |
| Supporto clienti | Quando arriva una segnalazione | Mantenere la relazione col cliente | Canale esterno (email/messaggi) + log/audit per diagnosi |
| Manutenzione tecnica | Solo se necessaria (bug, deploy) | Il codice non si mantiene da sé | Developer (ruolo distinto, vedi sotto) |

## Perché è organizzata così

Il principio guida (verificato in tre sessioni di audit precedenti) è: **ogni operazione che il codice può già fare da solo deve essere un pulsante, non una procedura**. Quello che resta manuale (vendita, supporto, decisioni commerciali, relazione col cliente) è manuale perché **deve** esserlo — non perché manca ancora l'automazione.

## Il ritmo operativo (oggi, un Founder solo)

```
Mattina  → Cruscotto: c'è qualcosa da fare? (backup, build fallite, coda bloccata)
Giorno   → Vendita/onboarding nuovi clienti, generazione/build app, supporto
Sera     → Verifica finale, eventuale backup fuori-ciclo se serve qualcosa di specifico
```

## Con quali strumenti (oggi)

- **Control Room** — unico strumento operativo, copre l'intero ciclo cliente→app→distribuzione→backup→audit→log.
- **Canale esterno** (email/messaggi) — per invio link e supporto clienti, non integrato nella piattaforma.
- **Documentazione** (`docs/`, i file canonici alla radice) — riferimento per procedure non quotidiane (ripristino, deploy, setup).

## Come cambia quando arriva un team

Il principio non cambia — cambia solo **chi** preme i pulsanti e legge il cruscotto. Il Founder mantiene decisioni commerciali e supervisione; le operazioni quotidiane (build, supporto, monitoraggio) si distribuiscono su ruoli dedicati. Dettaglio completo di permessi e responsabilità per ogni ruolo: `TEAM_ROLES.md`.

## Cosa questo manuale non è

Non descrive come funziona il codice (per quello: `PROJECT_INDEX.md` → `REAL_PROJECT_STATE.md`), non è una guida passo-passo per singola azione (per quello: `OWNER_GUIDE.md`, `OPERATING_PROCEDURES.md`). È la descrizione di **come l'azienda si muove nel tempo**, non di come il software è costruito.
