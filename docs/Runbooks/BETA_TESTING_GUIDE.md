# BETA_TESTING_GUIDE

> Guida per i **beta tester** (esercenti e staff) dell'app white-label. Android via link privato / Firebase; iOS via TestFlight.

## Android — installazione
**Opzione A — link privato (APK)**
1. Apri sul telefono il **link beta** ricevuto dall'operatore (valido 7 giorni).
2. Parte il download di `app-<versione>.apk`.
3. Se richiesto, abilita *"Consenti installazione da questa sorgente"* (Impostazioni ▸ App ▸ accesso speciale).
4. Apri l'APK scaricato e premi **Installa**.

**Opzione B — Firebase App Distribution**
1. Accetta l'invito email a entrare nel gruppo tester.
2. Installa l'app **App Tester** di Firebase, accedi e installa l'app dalla lista.

## Android — aggiornamento
- **Link/APK**: apri il nuovo link, scarica e installa sopra la versione esistente (i dati restano).
- **Firebase**: l'app Tester notifica le nuove versioni; tocca *Aggiorna*.
> La versione è visibile in app; segnala sempre la versione nei bug.

## Android — disinstallazione
Tieni premuta l'icona ▸ *Disinstalla* (o Impostazioni ▸ App ▸ Disinstalla). Reinstallabile in qualsiasi momento dal link.

## Rollback (tornare a una versione precedente)
1. Disinstalla la versione attuale.
2. Chiedi all'operatore il link della versione precedente (lo storico build è in Control Room).
3. Installa l'APK precedente.
> Nota: un downgrade può richiedere la disinstallazione prima dell'installazione.

## iOS — TestFlight
1. Installa **TestFlight** dall'App Store.
2. Accetta l'invito (email/link) ▸ *Installa*. Gli aggiornamenti arrivano da TestFlight.

## Segnalare un bug
Dall'app (quando disponibile l'apposita voce) o comunicando all'operatore:
- **cosa** è successo e **come** riprodurlo,
- la **versione** dell'app,
- il **telefono/OS**.
Le segnalazioni inviate dall'app arrivano alla Control Room (sezione *Feedback beta* della scheda app), con tenant, utente, versione e timestamp.

## Privacy
L'app beta usa gli stessi dati della versione finale del tuo account. Per cancellare l'account: *Profilo ▸ Elimina account* (sempre disponibile).
