# 08 — Flussi Applicativi Completi

## Flusso 1 — Onboarding e Provisioning Tenant

```
[Vendita conclusa]
       |
       v
[Super Admin crea Tenant record]
       | (genera: tenant_id, dominio/sottodominio dashboard, database schema/namespace)
       v
[Invio credenziali a Tenant Admin via email]
       |
       v
[Tenant Admin accede a Wizard di Onboarding]
       |
       +--> Step 1: Dati attività (nome, settore, indirizzo/i sede)
       +--> Step 2: Branding (nome app, logo, icona, splash, palette colori)
       +--> Step 3: Catalogo servizi (nome, durata, prezzo, categoria)
       +--> Step 4: Operatori (profili, servizi associati, foto)
       +--> Step 5: Orari (per sede, per operatore, eccezioni)
       +--> Step 6: Impostazioni notifiche (promemoria, opt-in marketing)
       |
       v
[Tenant dichiara settore = sanitario?] --(sì)--> [Step 7: Attivazione modulo dati sanitari + checklist DPIA]
       | (no)                                              |
       |<-------------------------------------------------+
       v
[Validazione configurazione completa] --(no)--> [Salvataggio bozza, richiesta dati mancanti]
       | (sì)
       v
[Trigger pipeline Build White Label] --> vedi Flusso 2
       |
       v
[Notifica "App in revisione"] --> [Notifica "App pubblicata"] --> [Go-live]
```

## Flusso 2 — Pipeline di Generazione Build White Label

```
[Trigger: configurazione tenant completa o aggiornamento branding]
       |
       v
[Recupero configurazione tenant: asset (logo/icona/splash), tema colori, app_name, bundle_id]
       |
       v
[Validazione asset]
   - dimensioni icona conformi (iOS/Android)
   - contrasto colori >= soglia WCAG AA
   - formato file supportato
       |
   (validazione fallita) --> [Notifica errore a Tenant Admin con istruzioni correzione]
       | (validazione ok)
       v
[Generazione configurazione build]
   - injection asset nel progetto template
   - generazione manifest (app.json / build config)
   - assegnazione bundle identifier univoco per tenant
       |
       v
[Build automatizzata (CI/CD)]
   - build Android (AAB)
   - build iOS (IPA)
   - (alternativa MVP) build PWA
       |
       v
[Firma e pacchettizzazione]
       |
       v
[Caricamento su Play Store / App Store Connect (o deploy PWA)]
       |
       v
[Stato: "In revisione"] --(approvato)--> [Stato: "Pubblicato"] --> [Notifica Tenant Admin]
       |
       +--(rifiutato)--> [Notifica motivo rifiuto] --> [Correzione configurazione] --> (torna a inizio flusso)
```

> Approfondimento sulle strategie alternative (build nativa per tenant vs. wrapper/PWA vs. app container multi-tenant) in [11-strategia-white-label.md](11-strategia-white-label.md).

## Flusso 3 — Calcolo Disponibilità e Prenotazione

```
[Cliente Finale apre sezione "Prenota"]
       |
       v
[Seleziona servizio] --> [Sistema determina operatori abilitati per quel servizio]
       |
       v
[Cliente seleziona operatore (opzionale) e sede]
       |
       v
[Sistema calcola slot disponibili]
   per ciascun giorno nell'intervallo richiesto:
     - recupera orari sede/operatore
     - sottrae assenze/chiusure straordinarie
     - sottrae prenotazioni esistenti + buffer
     - applica durata del servizio selezionato
   --> produce lista slot liberi
       |
       v
[Cliente seleziona slot]
       |
       v
[Sistema esegue verifica di concorrenza (lock/transazione)]
   - lo slot è ancora libero? 
       |
   (no, occupato nel frattempo) --> [Messaggio "slot non più disponibile", ricalcolo lista]
       |
   (sì)
       v
[Creazione prenotazione - stato: "confermata"]
       |
       v
[Invio notifica conferma al cliente]
       |
       v
[Invio notifica al/agli operatore/i coinvolti]
       |
       v
[Pianificazione promemoria automatici (job schedulati)]
```

## Flusso 4 — Modifica/Cancellazione Appuntamento

```
[Cliente Finale apre "I miei appuntamenti"]
       |
       v
[Seleziona appuntamento] --> [Verifica soglia temporale di modifica/cancellazione configurata dal tenant]
       |
   (oltre soglia, modifica non consentita) --> [Messaggio informativo + contatto diretto del tenant]
       |
   (entro soglia)
       v
[Opzione: Riprogramma] --> (torna a Flusso 3 - calcolo disponibilità, mantenendo servizio/operatore)
       |
[Opzione: Cancella] --> [Stato appuntamento: "cancellato"]
       |
       v
[Notifica al tenant/operatore della cancellazione]
       |
       v
[Slot liberato] --> [Se presente waitlist per quello slot, notifica al primo in lista]
```

## Flusso 5 — Gestione Indisponibilità Operatore (es. malattia improvvisa)

```
[Operatore/Tenant Admin segnala indisponibilità per intervallo date]
       |
       v
[Sistema identifica appuntamenti impattati in quell'intervallo]
       |
       v
[Per ciascun appuntamento impattato]
   --> [Notifica al cliente: "Il tuo appuntamento richiede una riprogrammazione"]
   --> [Proposta automatica di slot alternativi (stesso servizio, operatore alternativo se disponibile, o stesso operatore in altra data)]
       |
       v
[Cliente conferma nuovo slot oppure richiede assistenza diretta]
       |
       v
[Aggiornamento stato appuntamento]
```

## Flusso 6 — Notifiche e Promemoria (Job Schedulati)

```
[Scheduler periodico (es. ogni 15 minuti)]
       |
       v
[Query appuntamenti con promemoria pendenti]
   - "promemoria 24h" non ancora inviato e appuntamento tra 23-25h
   - "promemoria 2h" non ancora inviato e appuntamento tra 1.5-2.5h
       |
       v
[Per ciascun appuntamento]
   --> [Verifica consenso notifiche del cliente]
   --> [Invio notifica push (canale primario)]
   --> [Fallback email/SMS se push non disponibile/non installata (in base a piano)]
   --> [Marca promemoria come inviato]
```

## Flusso 7 — Comunicazione Broadcast/Marketing (Tenant Admin)

```
[Tenant Admin crea campagna: titolo, messaggio, segmento destinatari]
       |
       v
[Selezione segmento]
   - tutti i clienti con opt-in marketing
   - clienti inattivi (> N giorni senza prenotazione)
   - clienti di un servizio specifico
       |
       v
[Anteprima campagna]
       |
       v
[Invio immediato o programmato]
       |
       v
[Tracciamento: invii, aperture (se disponibile), eventuali prenotazioni generate]
```

## Flusso 8 — Onboarding Cliente Finale (prima apertura app)

```
[Download/apertura app per la prima volta]
       |
       v
[Splash screen brandizzata tenant]
       |
       v
[Schermata di benvenuto / value proposition]
       |
       v
[Registrazione/Login: email, telefono, social]
       |
       v
[Richiesta permessi notifiche push]
       |
       v
[Selezione sede (se multi-sede)]
       |
       v
[Homepage app: servizi in evidenza, CTA "Prenota ora", eventuali promozioni attive]
```

## Flusso 9 — Sospensione Tenant per Mancato Pagamento

```
[Sistema di billing rileva pagamento mensile non andato a buon fine]
       |
       v
[Tentativo di nuovo addebito automatico] --(fallito ripetutamente, es. 3 tentativi)--> [Stato tenant: "a rischio"]
       |
       v
[Notifica al Tenant Admin: regolarizzare pagamento entro X giorni]
       |
   (pagamento regolarizzato) --> [Stato tenant: "attivo"]
       |
   (termine scaduto)
       v
[Stato tenant: "sospeso"]
   - App cliente finale mostra messaggio "servizio temporaneamente non disponibile"
   - Dashboard Tenant Admin in modalità solo lettura
   - Dati conservati per periodo di retention contrattuale
       |
       v
[Eventuale disattivazione definitiva dopo periodo di retention] --> [Procedura di esportazione/cancellazione dati su richiesta]
```
