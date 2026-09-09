# CURRENT CUSTOMER APP (FASE 0)

> Come funziona **oggi** l'app cliente, verificato nel codice Flutter reale.
> File letti: `app/router.dart`, `main.dart`, `home_screen.dart`, `booking_services_screen.dart`,
> `booking_schedule_screen.dart`, `booking_success_screen.dart`, `booking_flow_controller.dart`,
> `login/register/verify_email_screen.dart`, `my_appointments_screen.dart`, `business_info_screen.dart`,
> `profile_screen.dart`, `app.dart`, `white_label_config.dart`. Nessun file modificato.
> Diviso in **GIÀ OTTIMO / MIGLIORABILE / ASSENTE**.

---

## Come funziona oggi (flusso reale)

```
Splash (carica config)
  → Login / Registrazione (email + password ≥10 + privacy)
     → Verifica Email (codice 6 cifre)          ← MURO per il primo accesso
        → Home (hub, push-based, niente bottom nav)
           → "Prenota ora"
              → Step 1 · Servizi (multi-select, card)
                 → Step 2 · Data+Operatore+Orario (una sola schermata, default intelligenti)
                    → Conferma (prenota direttamente, nessuna schermata review)
                       → Successo (recap dal record reale)
Altre schermate: Le mie prenotazioni (storico), Informazioni (scheda attività), Profilo.
```

**Navigazione:** hub-and-spoke con `go_router`, **nessuna bottom navigation**. Ogni schermata ha AppBar.
**Auth:** JWT + refresh, email/password (min 10 caratteri), verifica email a 6 cifre, MFA per staff, consenso privacy alla registrazione.
**Booking (utente registrato e verificato):** Home → Servizi (1 tap + Continua) → Orario (default già impostati → 1 tap slot + Conferma). **~4-5 tap, sotto i 60 secondi.**

---

## 🟢 GIÀ OTTIMO (da non toccare)

- **Default intelligenti nello Step 2**: prima sede, oggi, primo operatore capace → l'utente atterra e deve solo scegliere lo slot. *(`_applyDefaults` post-frame.)* È la scelta di design migliore dell'app.
- **Schedule su una sola schermata**: striscia giorni + operatore + slot Mattina/Pomeriggio + conferma. Nessuna navigazione sprecata. Densità in stile Calendly, fatta bene.
- **Prenotazione diretta senza schermata di review ridondante**: il bottone conferma mostra l'orario ("Conferma per le 10:30") e prenota. Meno passaggi = più veloce.
- **Logica di compatibilità**: mostra solo operatori/servizi eseguibili insieme → niente vicoli ciechi (`performsAll`).
- **Recap di successo dal record reale persistito** (non dalle scelte locali): affidabile; gestisce stato *confermato* vs *in attesa*.
- **Gestione `slot_unavailable`**: lo slot viene liberato e la disponibilità si aggiorna da sola.
- **Stati loading/empty/error ovunque**, con "Riprova".
- **Routing consapevole di config/sessione**: splash finché la config non è pronta, schermata di cortesia se il tenant è sospeso, gate di verifica.
- **Verifica email**: codice 6 cifre, resend con cooldown 30s, schermata pulita.
- **Tema white-label + dark mode** applicati a tutta l'app (da missioni precedenti).
- **Idempotenza prenotazione** + cache config ETag (backend) → robustezza.

---

## 🟡 MIGLIORABILE

- **Nessuna opzione "Qualsiasi operatore / nessuna preferenza"**: l'utente deve scegliere una persona; si perde disponibilità e un tap. Booksy/Fresha lo offrono.
- **Success screen è un vicolo cieco**: recap + "Vedi prenotazioni"/"Home". **Manca "Aggiungi al calendario", indicazioni stradali, condividi.** Enorme valore percepito lasciato sul tavolo.
- **Nessuna rassicurazione di prezzo/riepilogo prima della conferma finale**: il bottone mostra solo l'orario, non il totale/servizio.
- **Registrazione pesante per un'app di prenotazione**: nome+email+**password ≥10**+privacy. Nessun OTP telefono, nessun social, nessun magic link.
- **Nessun "primo slot disponibile"**: l'utente scorre i giorni manualmente; manca il quick-book "prima disponibilità".
- **Nessun "prenota di nuovo"** per i clienti abituali (ri-prenotare lo stesso servizio/operatore in un tap).
- **Loader generici** (`CircularProgressIndicator`), non skeleton → percezione di lentezza.
- **Striscia giorni senza "Oggi/Domani" né contesto mese**: solo abbreviazione giorno + numero.
- **Nessuna micro-animazione/haptic** alla conferma (icona statica) → manca il "momento" emotivo.
- **Home**: funzionale (CTA prenota, prossimo appuntamento, link) ma non ottimizzata per il **re-booking** rapido.
- **Storico "Le mie prenotazioni"**: consente la cancellazione ma **non lo spostamento** (reschedule).

---

## 🔴 ASSENTE

- **Prenotazione prima della registrazione (guest booking)**: oggi devi avere account **e** email verificata prima di prenotare. *È il più grande ostacolo alla prima conversione.*
- **Login passwordless / OTP telefono / social login**: nessuna alternativa a email+password.
- **Aggiungi al calendario** (Apple/Google) da successo o dettaglio prenotazione.
- **Reschedule** (spostare a un altro orario): esiste solo la cancellazione.
- **"Qualsiasi operatore" auto-assegnato**.
- **Waitlist "avvisami se si libera"**: la tabella esiste nel backend ma **nessuna UI** (feature non implementata end-to-end).
- **Indicazioni stradali / deep-link mappa** dal dettaglio prenotazione.
- **Preferiti / operatore preferito / rebook shortcut**.
- **Onboarding di benvenuto** alla prima apertura.
- **Illustrazioni negli empty state** (oggi solo testo).
- **Sincronizzazione calendario / gestione promemoria lato cliente**.

---

## Sintesi FASE 0

**Il cuore della prenotazione è già di livello alto**: default intelligenti, una sola schermata per data/operatore/orario,
conferma diretta. Per un cliente **abituale**, prenotare è già un'esperienza da <60 secondi.

**Il problema non è il booking: è tutto ciò che lo circonda.** Due debolezze dominano:
1. **Il muro del primo accesso** (registrazione + verifica email prima di prenotare) → la *prima* prenotazione è lentissima e perde clienti.
2. **La coda dell'esperienza** (success screen cieca, niente calendario/indicazioni/reschedule/rebook) → manca il "wow" che fa dire *"questa app è fantastica"*.

Il resto del dossier progetta come rendere **eccezionale** ciò che oggi è **buono**, restando esclusivamente nel dominio prenotazioni.
