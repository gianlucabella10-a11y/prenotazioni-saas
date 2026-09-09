# CONTROL_ROOM_OPERATOR_GUIDE

> Guida per l'**operatore non tecnico**. Tutto si fa dalla Control Room (`/control-room`, login super-admin + codice MFA). Nessun codice da toccare.

## 1. Creo un cliente
*Clienti ▸ + Nuovo cliente*: nome attività, settore, email del titolare, piano, template (es. Barber), colore. → Salva. Il cliente è creato (stato **Bozza**) e il titolare riceve l'invito per la sua dashboard.

## 2. Carico il brand
*App ▸ [cliente]*: carica il **logo** (PNG/JPG ≥ 256px). Imposta nome/colori. → stato **Configurata**.
Il titolare completa servizi/operatori/orari dalla **sua** dashboard.

## 3. Genero l'app
*App ▸ [cliente] ▸ **Genera pacchetto***. Compaiono l'anteprima (logo, icona, splash, colori) e lo stato **Pronta build**.

## 4. Faccio la build
*App ▸ [cliente] ▸ **Build Android***. Lo stato passa a **In build**; quando finisce diventa **Compilata** con: dimensione APK, durata, checksum, data e **log** (apribile se serve). Se fallisce: **Fallita** con l'errore leggibile (apri il log).

## 5. Mando l'APK al cliente
Sul build **Compilata** ▸ **Link beta**. Compare un link (valido 7 giorni, con limite download). **Copialo e invialo** all'esercente (WhatsApp/email). Aggiungi l'esercente come tester: *Beta tester ▸ Invita* (nome + email).
Per togliere l'accesso: *Link beta ▸ **Revoca***.

## 6. Aggiorno la versione
*Versioni ▸ Registra versione* (numero versione + build number crescente + note). Poi *Genera pacchetto ▸ Build Android ▸ Link beta* nuovo. Il cliente installa il nuovo APK sopra il precedente.
Per ritirare una versione problematica: *Versioni ▸ **Deprecata*** e ridistribuisci il link della precedente.

## 7. Vedo errori e feedback
- *App ▸ [cliente]*: **Feedback beta** (segnalazioni dall'app), **build log** (errori di build), **Link beta** (download usati).
- *Flotta*: panoramica — app totali, build riuscite/fallite/in corso, beta attive.
- I **crash** dell'app arrivano su Sentry (con tenant e versione).

## In caso di problemi
Vedi `BETA_DEBUG_RUNBOOK.md` (build fallita / APK non installa / errore API / push). Per il setup della macchina che compila: `BUILD_MACHINE_SETUP.md` (lo fa un tecnico una volta sola).
