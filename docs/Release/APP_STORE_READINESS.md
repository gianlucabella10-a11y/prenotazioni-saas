# APP_STORE_READINESS — Stato di conformità store

**Data**: 12/06/2026, post-stabilizzazione MVP. Confronto con i blocchi rilevati in [MVP_PRODUCTION_READINESS_REPORT.md](MVP_PRODUCTION_READINESS_REPORT.md) §7-8.

## Apple App Store

| Requisito | Stato | Dettaglio |
|---|---|---|
| **Account deletion 5.1.1(v)** | ✅ PRESENTE | `DELETE /me` + flusso in-app (Profilo → Elimina account, doppia conferma): immediato, revoca tutte le sessioni, pseudonimizza, audit-loggato. Testato (AccountManagementTest) |
| **Privacy Policy** | ✅ PRESENTE (da compilare per tenant) | URL white-label configurabile (`/manage/brand`), esposto nel config (`legal.privacy_policy_url`), linkato in registrazione e profilo. **Azione**: ogni tenant deve fornire un URL https reale prima della submission |
| Consenso raccolta dati | ✅ | Checkbox esplicita in registrazione, consenso versionato e timestampato (GDPR) |
| Sign in with Apple | ✅ NON RICHIESTO | Solo login proprietario email/password; nessun login di terze parti |
| Tracking / ATT | ✅ NON RICHIESTO | Nessun tracking né SDK pubblicitari |
| Permessi | ✅ | Nessun permesso oggi; push (futuro) con purpose string contestuale |
| Pagamenti / IAP | ✅ NON APPLICABILE | Servizi fisici pagati di persona (3.1.3(e)) |
| Notifiche | ⚠️ FOUNDATION PRONTA | Endpoint devices ✅ + pulizia token ✅; manca integrazione FCM client + APNs key (account tenant) |
| Verifica account | ✅ | Email verification a 6 cifre con resend; account deletion accessibile anche da non verificati |
| Login flow | ✅ | Logout, sessione persistente sicura (Keychain), refresh rotation |
| **Icona/Splash brandizzate** | ❌ MANCANTE | Ancora default Flutter: serve la pipeline asset nativa per tenant (docs/27 §3) |
| **HTTPS/ATS** | ⚠️ DI PIPELINE | Codice pronto (URL da dart-define); la build store deve puntare a https senza eccezioni ATS |
| **Account Apple del tenant** | ❌ PROCESSO DA AVVIARE | Strategia 4.2.6 definita (docs/27 §6); enrollment pilota mai eseguito |
| Privacy Nutrition Label | ❌ DA COMPILARE | Dati raccolti: email, nome, telefono (opz.), prenotazioni; nessuna condivisione; collegati all'identità |

## Google Play

| Requisito | Stato | Dettaglio |
|---|---|---|
| **Account deletion (in-app)** | ✅ PRESENTE | Come sopra |
| **Account deletion (link web)** | ⚠️ PARZIALE | La policy Play richiede ANCHE un URL web: l'endpoint esiste (`DELETE /me`), serve una pagina web minima per tenant che lo invochi — da includere nella dashboard web (incremento successivo) |
| Data Safety form | ❌ DA COMPILARE | Stessi dati della Nutrition Label Apple |
| Permissions | ✅ | Solo INTERNET; release con `usesCleartextTraffic=false` (verifica di pipeline) |
| Account management | ✅ | Profilo visualizza/modifica/elimina + consensi |
| Strategia account (sharding) | ❌ DA IMPLEMENTARE | Decisione presa (ARCHITECTURE_FINAL_REVIEW §6): max ~50 app/account org — da attuare alla prima pubblicazione |

## Configurazioni white-label richieste per ogni submission

Per ciascun tenant, PRIMA della submission: `privacy_policy_url`, `terms_url`, `support_url` (https, già configurabili via API/dashboard) · icona/splash generate dal logo master (pipeline da costruire) · metadata store con contenuti reali (descrizione, screenshot dal catalogo — anti 4.3) · account developer (Apple: del tenant; Play: account org piattaforma shardato).

## Azioni necessarie residue (in ordine)

1. Pipeline asset nativi (icone/splash) per tenant — ultimo blocker tecnico di submission
2. Pagina web "elimina account" per requisito Play (1 pagina, stessa API)
3. Compilazione Data Safety / Nutrition Label (i dati sono già censiti sopra)
4. Enrollment Apple del primo tenant pilota + submission di validazione 4.2.6
5. FCM client + APNs per le push (non blocca la submission, blocca la promessa promemoria)
