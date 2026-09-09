# 26 — Autenticazione e Autorizzazione

## 1. Modello di identità

Tutte le identità vivono nella tabella `users` ([24-database-er.md](24-database-er.md)) con `type` e `tenant_id` (NULL per super admin). La stessa persona fisica può avere identità distinte su tenant diversi (è il modello corretto per white label: il customer "appartiene" al tenant, titolare del trattamento).

## 2. Flussi di autenticazione

| Attore | Metodi | Note |
|---|---|---|
| Customer (app cliente) | email+password, OTP telefono, Google/Apple Sign-In | Apple Sign-In obbligatorio su iOS se esiste social login (policy App Store) |
| Staff / Tenant Admin (app gestionale + dashboard) | email+password + **MFA TOTP obbligatoria per tenant_admin** | invito via email con set-password |
| Super Admin | email+password + MFA obbligatoria + restrizione di rete (allowlist/VPN) | dominio dashboard separato |

## 3. Token: JWT + refresh token con rotazione

| Token | Forma | Vita | Storage client |
|---|---|---|---|
| Access token | JWT firmato asimmetrico (RS256/ES256) | 15 minuti | memoria + secure storage mobile (Keychain/Keystore) |
| Refresh token | stringa opaca (hash persistito in `refresh_tokens`) | 30 giorni, **rotazione a ogni uso** | secure storage mobile; cookie httpOnly+Secure per dashboard web |

Claims JWT: `sub` (user uuid), `tid` (tenant uuid, assente per super admin), `typ` (customer/staff/tenant_admin/super_admin), `scope`, `mfa` (bool), `exp`, `iat`, `jti`.

Proprietà del disegno:
- **Revoca**: il refresh token è revocabile immediatamente (logout, sospensione, cambio password). L'access token a 15 minuti limita la finestra residua; per i casi d'emergenza (compromissione, sospensione tenant) una **denylist Redis su `jti`/`tid`** chiude anche quella finestra
- **Rotazione con rilevamento riuso**: ogni refresh emette un nuovo token nella stessa `family_uuid`; il riuso di un token già ruotato (furto) revoca l'intera famiglia e forza re-login
- **Chiavi di firma**: coppia asimmetrica in AWS Secrets Manager, `kid` nel header JWT, rotazione periodica con doppia chiave attiva durante la transizione
- La **sospensione tenant** (Flusso 9) si applica come check sullo stato tenant in middleware (cache Redis), indipendente dalla validità del token

## 4. MFA

- TOTP (app authenticator) come fattore primario; recovery codes monouso generati alla attivazione
- Enforcement: `users.mfa_enforced` true ⇒ login incompleto finché MFA non verificata (`mfa: false` nel JWT limita gli scope a `mfa-setup` soltanto)
- Trusted device opzionale (30 giorni) per la dashboard, mai per super admin

## 5. RBAC

### Ruoli e matrice (estratto)

| Permesso | customer | staff | tenant_admin | super_admin |
|---|---|---|---|---|
| Prenotare per sé | ✅ | — | — | — |
| Vedere propria agenda | — | ✅ | ✅ | — |
| Vedere agenda altri staff | — | configurabile | ✅ | — |
| Gestire appuntamenti di qualunque staff | — | configurabile | ✅ | — |
| CRUD catalogo/orari/sedi | — | ❌ | ✅ | — |
| Leggere note interne cliente | — | ✅ | ✅ | — |
| Leggere/scrivere note cliniche | — | solo se autorizzato per-staff e modulo attivo | ✅ (se modulo attivo) | ❌ |
| Branding e build | — | ❌ | ✅ | ✅ |
| Campagne marketing | — | ❌ | ✅ | — |
| Export/report | — | propri | ✅ | aggregati anonimi |
| Gestione tenant/piani/sospensioni | — | — | — | ✅ |
| Impersonificazione | — | — | — | ✅ (audit completo, a tempo) |

### Implementazione
- Ruolo base da `users.type` + **permessi granulari per-staff** (JSON di capability assegnate dal tenant_admin: vede_altri_calendari, gestisce_clienti, accede_note_cliniche…)
- Enforcement con Policy Laravel su ogni risorsa; le policy ricevono il TenantContext già risolto — un controllo di autorizzazione non può mai attraversare il confine tenant
- I **feature flag di piano** sono un layer ortogonale: la policy verifica il permesso, il middleware di feature verifica che il piano del tenant includa la funzionalità (criticità 4 Fase 1)

## 6. Impersonificazione (supporto)

- Il super admin può aprire una sessione di supporto su un tenant: token dedicato con claim `impersonator_sub`, durata max 1h, banner visibile in dashboard
- Ogni azione in impersonificazione è scritta in `audit_logs` con entrambe le identità
- Disattivabile contrattualmente per tenant con requisiti di riservatezza rafforzati (sanitario)

## 7. Sicurezza credenziali

- Password: hash Argon2id; politica lunghezza ≥ 10, verifica contro liste di password compromesse
- OTP telefono: 6 cifre, scadenza 5 min, max 5 tentativi, rate limit per numero e per IP
- Brute force: lockout progressivo per account + per IP (Redis)
- Reset password: token monouso a scadenza breve; invalidazione sessioni e refresh token al completamento
