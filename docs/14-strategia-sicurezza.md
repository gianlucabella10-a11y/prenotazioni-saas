# 14 — Strategia di Sicurezza

## 1. Principi generali

La piattaforma adotta un approccio "security & privacy by design", in linea con l'art. 25 GDPR, applicando i principi di:

- **Minimizzazione dei dati**: raccolta solo dei dati necessari alle finalità (prenotazione, comunicazione)
- **Isolamento multi-tenant**: nessun dato di un tenant è accessibile da un altro tenant (vedi [12-strategia-multi-tenant.md](12-strategia-multi-tenant.md))
- **Defense in depth**: più livelli di controllo (rete, applicazione, dati)
- **Least privilege**: ogni attore (RF — vedi [05-srs.md](05-srs.md)) ha accesso solo alle funzionalità e ai dati strettamente necessari al proprio ruolo

## 2. Gestione identità e accessi (IAM)

| Ruolo | Ambito di accesso |
|---|---|
| Super Admin | Accesso amministrativo alla piattaforma; nessun accesso diretto ai dati dei clienti finali dei tenant salvo per finalità di supporto, tracciato (audit) |
| Tenant Admin | Accesso completo ai dati del proprio tenant (configurazione, clienti, appuntamenti) |
| Operatore | Accesso limitato alla propria agenda e ai dati dei clienti necessari per l'erogazione del servizio, secondo permessi configurati dal Tenant Admin |
| Cliente Finale | Accesso ai propri dati e appuntamenti |

Requisiti:
- Autenticazione a più fattori (MFA) **obbligatoria per Super Admin e Tenant Admin** (il Tenant Admin ha accesso completo ai dati personali dei clienti del tenant, a tutti gli effetti dato sensibile GDPR); raccomandata, e configurabile come obbligatoria dal Tenant Admin, per gli Operatori
- Politiche password robuste, gestione sessioni con scadenza
- Controllo accessi basato su ruoli (RBAC) applicato a livello di API, non solo di interfaccia

## 3. Protezione dei dati

### 3.1 Dati in transito
- TLS 1.2+ obbligatorio per tutte le comunicazioni (app, dashboard, API, comunicazioni tra servizi interni)

### 3.2 Dati a riposo
- Cifratura dei dati sensibili a riposo (es. dati di contatto, eventuali dati sanitari, credenziali)
- Gestione sicura dei segreti (chiavi API, credenziali di servizi terzi) tramite vault dedicato, non in configurazione in chiaro

### 3.3 Dati particolari (settore sanitario)
- Le note cliniche e altri dati relativi alla salute (categorie particolari ai sensi dell'art. 9 GDPR) richiedono:
  - Attivazione esplicita del modulo dedicato (vedi [10-funzionalita.md](10-funzionalita.md))
  - Misure di sicurezza rafforzate (cifratura dedicata, accesso ristretto, audit log su ogni accesso/modifica)
  - Accordo di trattamento dati (Data Processing Agreement) specifico tra software house e tenant sanitario, e tra tenant e propri pazienti (informativa privacy)

## 4. Conformità GDPR

| Requisito GDPR | Implementazione |
|---|---|
| Base giuridica del trattamento | Contratto (per gestione appuntamenti), consenso (per marketing) |
| Registro dei consensi | Modulo D5 — Audit & Compliance ([09-moduli.md](09-moduli.md)) traccia opt-in/opt-out per ciascun cliente finale |
| Diritto di accesso | Funzione di esportazione dati personali del cliente finale |
| Diritto alla cancellazione | Procedura di cancellazione/anonimizzazione dati su richiesta, con gestione delle eccezioni per obblighi legali (es. conservazione fiscale) |
| Diritto alla portabilità | Esportazione dati in formato strutturato (es. JSON/CSV) |
| Privacy by default | Le impostazioni di default non prevedono opt-in marketing; le notifiche di servizio (promemoria) sono basate su base contrattuale, non su consenso marketing |
| Data Processing Agreement (DPA) | Contratto tra software house (sub-processor/processor) e ciascun tenant (titolare del trattamento verso i propri clienti) |
| Valutazione d'impatto (DPIA) | Da effettuarsi per i tenant che attivano moduli con dati sanitari |

## 5. Sicurezza applicativa

- Adozione di pratiche di **secure coding** e revisione orientata alla prevenzione delle vulnerabilità OWASP Top 10 (injection, autenticazione debole, controllo accessi, ecc.)
- **Validazione input** rigorosa su tutte le interfacce (dashboard, app, API), specialmente per i campi configurabili dal Tenant Admin che alimentano la generazione delle build (per evitare injection negli asset/manifest generati)
- **Rate limiting** sulle API pubbliche (in particolare endpoint di autenticazione e prenotazione) per prevenire abusi
- **Gestione sicura degli upload** (loghi, icone, immagini servizi): validazione tipo file, scansione contenuti, limiti dimensionali

## 6. Sicurezza della pipeline di build white label

- Le configurazioni fornite dai tenant (testi, asset) sono trattate come **input non fidati** e validate/sanificate prima di essere incorporate nelle build (Flusso 2, [08-flussi-applicativi.md](08-flussi-applicativi.md))
- Le credenziali di firma delle app (certificati, keystore) sono gestite centralmente in modo sicuro, con accesso limitato al sistema di build automatizzato
- Audit log di ogni build generata e pubblicata, con tracciabilità della configurazione utilizzata

## 7. Monitoraggio e risposta agli incidenti

- Logging centralizzato degli eventi di sicurezza (accessi falliti, modifiche a configurazioni critiche, accessi Super Admin ai dati tenant)
- Procedura di **incident response** documentata, con classificazione della gravità e tempi di notifica conformi al GDPR (notifica al Garante entro 72 ore in caso di data breach con rischio per gli interessati, notifica ai tenant/interessati se richiesto)
- Test periodici di sicurezza (vulnerability assessment, penetration test) con cadenza almeno annuale, e dopo modifiche architetturali significative

## 8. Continuità operativa

| Requisito | Target |
|---|---|
| Backup | Giornalieri, retention minima 30 giorni, test di restore periodici |
| RPO (Recovery Point Objective) | ≤ 24h per servizi core |
| RTO (Recovery Time Objective) | ≤ 4h per servizi core |
| Ridondanza | Componenti critici (autenticazione, prenotazione, notifiche) progettati senza singolo punto di guasto |

## 9. Sicurezza nel ciclo di vita del tenant

- **Onboarding**: provisioning automatizzato con credenziali generate in modo sicuro e comunicate tramite canale verificato
- **Sospensione** (Flusso 9, [08-flussi-applicativi.md](08-flussi-applicativi.md)): i dati restano protetti e isolati durante il periodo di sospensione
- **Cessazione**: procedura di esportazione dati su richiesta del tenant e successiva cancellazione sicura entro i termini contrattuali/legali, con cancellazione anche degli asset di branding e revoca degli accessi
