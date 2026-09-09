# 10 — Catalogo delle Funzionalità

Questo documento elenca le funzionalità concrete della piattaforma, organizzate per modulo (vedi [09-moduli.md](09-moduli.md)) e classificate per fascia di piano (Base, Pro, Enterprise — vedi anche [15-strategia-monetizzazione.md](15-strategia-monetizzazione.md)).

## Legenda fasce
- **B** = Incluso nel piano Base (canone standard 250€/mese)
- **P** = Incluso nel piano Pro / disponibile come add-on
- **E** = Incluso nel piano Enterprise / multi-sede avanzato

## Branding & White Label

| Funzionalità | Fascia |
|---|---|
| Logo, icona, splash screen personalizzati | B |
| Palette colori personalizzata (tema chiaro) | B |
| Tema scuro personalizzato | P |
| Nome app e metadata store personalizzati | B |
| Domini/sottodomini personalizzati per dashboard | P |
| App nativa pubblicata su store dedicati al tenant | B (con processo descritto in [11-strategia-white-label.md](11-strategia-white-label.md)) |
| Kit di lancio (QR code, locandina, post social personalizzati con branding tenant) | B |

## Catalogo Servizi

| Funzionalità | Fascia |
|---|---|
| Gestione servizi (CRUD), prezzi, durate | B |
| Categorie di servizi | B |
| Varianti servizio (es. corto/lungo) | B |
| Pacchetti/combo di servizi | P |
| Listini differenziati per sede | E |
| Gestione abbonamenti/pacchetti prepagati a sedute (es. fisioterapia) | P |

## Gestione Operatori

| Funzionalità | Fascia |
|---|---|
| Profili operatore, foto, ruoli | B |
| Calendario disponibilità individuale | B |
| Gestione ferie/permessi/assenze | B |
| Riassegnazione automatica appuntamenti in caso di indisponibilità | P |
| Statistiche per operatore (occupazione, fatturato) | P |

## Orari, Sedi e Disponibilità

| Funzionalità | Fascia |
|---|---|
| Orari di apertura per sede/giorno | B |
| Eccezioni/chiusure straordinarie | B |
| Singola sede | B |
| Multi-sede (fino a 3) | P |
| Multi-sede illimitate con reportistica aggregata | E |
| Engine di calcolo disponibilità in tempo reale | B |

## Prenotazione (App Cliente Finale)

| Funzionalità | Fascia |
|---|---|
| Registrazione/login (email, telefono) | B |
| Login social (Google/Apple) | B |
| Catalogo servizi e prezzi | B |
| Prenotazione self-service | B |
| Selezione operatore preferito | B |
| Modifica/cancellazione appuntamento (entro soglia) | B |
| Lista d'attesa (waitlist) | P |
| Prenotazione ricorrente (es. ogni 4 settimane) | P |

## Notifiche e Comunicazioni

| Funzionalità | Fascia |
|---|---|
| Notifica di conferma prenotazione | B |
| Promemoria automatico (1 soglia configurabile) | B |
| Promemoria multipli (più soglie) | P |
| Notifica push per modifiche/cancellazioni | B |
| Campagne broadcast manuali | P |
| Campagne segmentate (clienti inattivi, per servizio) | P |
| Invio SMS (oltre push/email) | P (costo a consumo) |

## CRM Clienti

| Funzionalità | Fascia |
|---|---|
| Anagrafica clienti e storico appuntamenti | B |
| Note interne sul cliente | B |
| Note cliniche con permessi differenziati (settore sanitario) | Obbligatorio (attivazione automatica) per tenant che dichiarano settore sanitario, a sovrapprezzo fisso |
| Identificazione automatica clienti inattivi | P |
| Esportazione anagrafica (CSV) | B |
| Importazione massiva anagrafica clienti (CSV) durante onboarding | B |

## Recensioni & Fidelizzazione (roadmap)

| Funzionalità | Fascia |
|---|---|
| Richiesta recensione post-servizio | P |
| Visualizzazione valutazioni aggregate | P |
| Programma punti fedeltà | E (roadmap) |
| Referral clienti | E (roadmap) |

## Reportistica

| Funzionalità | Fascia |
|---|---|
| Dashboard KPI base (prenotazioni, occupazione, no-show) | B |
| Esportazione dati CSV | B |
| Report avanzati (per operatore, per sede, andamento mensile) | P |
| Report comparativo multi-sede | E |

## Pagamenti (roadmap add-on)

| Funzionalità | Fascia |
|---|---|
| Pagamento online del servizio | Add-on |
| Acconto/caparra alla prenotazione | Add-on |
| Gestione rimborsi | Add-on |

## Sicurezza & Compliance

| Funzionalità | Fascia |
|---|---|
| Conformità GDPR di base (consensi, export/cancellazione dati) | B |
| Gestione avanzata dati sanitari (categorie particolari) | P (richiesto per settori regolamentati) |
| Audit log avanzato | E |
| SSO aziendale per dashboard (multi-sede enterprise) | E |

## Amministrazione Piattaforma (Super Admin — non esposto al tenant)

| Funzionalità |
|---|
| Provisioning automatizzato tenant |
| Pipeline build white label (Android/iOS/PWA) |
| Dashboard monitoraggio piattaforma e billing |
| Gestione feature flag per piano/tenant |
| Strumenti di supporto e impersonificazione controllata |

## Note di progettazione

- Tutte le funzionalità della fascia **B** devono essere disponibili "out of the box" alla fine dell'onboarding (Flusso 1, [08-flussi-applicativi.md](08-flussi-applicativi.md))
- Le funzionalità **P/E** sono gestite tramite feature flag a livello di tenant (vedi [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md)), permettendo upsell senza modifiche al codice
- Le funzionalità relative a dati sanitari (note cliniche, gestione categorie particolari) richiedono l'attivazione esplicita e l'accettazione di clausole contrattuali aggiuntive (Data Processing Agreement specifico) — vedi [14-strategia-sicurezza.md](14-strategia-sicurezza.md)
