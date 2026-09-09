# CONTROL_ROOM_READINESS

> Stato della **Control Room MVP v1** dopo l'implementazione. Console proprietaria super-admin, Blade nella stessa app Laravel. Backend: **104/104 test verdi** (90 preesistenti + 14 Control Room). Nessuna modifica a booking engine, auth cliente, Flutter o API esistenti.

## A) Cosa è pronto
- **Accesso proprietario isolato**: guard dedicata `admin`, login `/control-room/login`, **MFA obbligatoria** (riuso `Totp`/`MfaCredential`), `EnsureSuperAdmin` su ogni rotta. Sessione separata dal dashboard cliente.
- **Bootstrap primo super-admin**: comando `php artisan control-room:create-admin {email} {--password=}` (niente tinker).
- **Lista clienti** (`/control-room/clienti`): nome, categoria, titolare, stato, piano, creato, ultimo aggiornamento; **ricerca** per nome + **filtro stato** (attivi/in attivazione/sospesi/archiviati).
- **Creazione cliente**: form (nome, categoria, email titolare, telefono, indirizzo, colore, piano) → **riusa `ProvisionTenant`** (una transazione: tenant + sottoscrizione + brand + sede + orari + catalogo del settore + titolare + invito). Nessuna logica duplicata.
- **Scheda cliente**: informazioni, operazioni (**attiva / sospendi / riattiva** via `ChangeTenantStatus` condivisa con l'API), **invito** (stato pending/accepted/expired, rigenera, revoca, link mostrato una sola volta), **brand quick-setup** (nome app, colori, **upload logo** su `brand_assets`), dati tecnici (uuid, api key, sottoscrizione).
- **Sicurezza testata**: super-admin entra; titolare/staff/cliente/guest **negati** (403/redirect); una sessione dashboard (`web`) **non** apre la Control Room; isolamento tenant intatto; ogni azione **audit-loggata**.
- **Separazione netta**: `/dashboard` resta dei clienti, `/control-room` è interno; palette visivamente distinta per non confondersi.

## B) Cosa posso fare con i primi 10 clienti (senza supporto tecnico)
1. Creo il mio account proprietario (comando) e accedo con MFA.
2. **Creo un cliente** dal form (categoria reale → catalogo già popolato).
3. **Genero e copio il link di accesso** del titolare (imposta password al primo accesso, flusso `/dashboard/invito` esistente).
4. **Personalizzo il brand** (nome app, colori, logo) dalla scheda.
5. **Sospendo / riattivo** un cliente (es. mancato pagamento, pausa).
6. **Cerco e filtro** i clienti per stato.
→ Tutto il ciclo *acquisizione → configurazione → consegna → gestione* senza toccare il database.

## C) Cosa manca prima di 50 clienti (non bloccante ora)
- **`php artisan storage:link`** in produzione perché i logo caricati siano serviti via URL pubblico (in dev/test già ok; nota operativa).
- **Esporre il `logo_url` nel `/app/config`** così il logo arriva all'app cliente (aggiunta additiva a `BuildWhiteLabelConfig` — ponte con `PRODUCT_DESIGN_ROADMAP.md` P0).
- **Cambio piano / modifica sottoscrizione** dalla scheda (oggi il piano si imposta alla creazione).
- **Paginazione/ordinamento avanzati** e una colonna "ultimo accesso titolare" esplicita (il dato `last_login_at` esiste già).
- **Archiviazione (terminate)** dalla UI (oggi: attiva/sospendi/riattiva; lo stato `terminated` esiste nel dominio).
- **Reinvio invito via email** automatico (oggi: link copiabile a mano).

## D) Rischi futuri
- **Accesso proprietario**: la sicurezza dipende da password forte + MFA del super-admin. Mitigazione presente (MFA obbligatoria, audit accessi); aggiungere allow-list IP in produzione.
- **Storage logo**: in produzione usare disco `s3` (config `branding.asset_disk`) e validare dimensioni/peso (limite 2MB già presente).
- **Crescita oltre 50**: la lista è paginata (30/pagina) ma senza ricerca full-text avanzata; valutare indici/più filtri quando i clienti crescono.
- **Operazioni distruttive**: sospensione/archiviazione impattano clienti reali → confermate via dialog + audit; valutare "soft hold" prima di terminare.

## E) Prossimi step consigliati (in ordine)
1. `storage:link` + esporre `logo_url` nel config → il brand caricato arriva sull'app.
2. Cambio piano + reinvio invito via email dalla scheda.
3. Colonna "ultimo accesso" + stato sottoscrizione in lista.
4. Allow-list IP / 2° fattore hardware per l'accesso proprietario in produzione.
5. (Dopo) statistiche essenziali (clienti per stato, prenotazioni) — quando servono davvero, non prima.

## Definition of Done — verifica
- [x] Entro come proprietario (comando `control-room:create-admin` + login MFA)
- [x] Creo un cliente senza toccare il DB (form → `ProvisionTenant`)
- [x] Genero l'accesso del cliente (invito + link)
- [x] Sospendo/riattivo
- [x] Controllo il brand (nome/colori/logo)
- [x] Nessun cliente può vedere l'area (test di isolamento verdi)
- [x] Tutti i test passano (**104/104**, asserzioni 451)

## Note tecniche
- File nuovi: modulo `app/Modules/ControlRoom/*`, azioni condivise `IssueTenantInvite`/`ChangeTenantStatus`/`StoreBrandLogo`, comando `CreateControlRoomAdmin`, viste `resources/views/control_room/*`, test `tests/Feature/ControlRoom/*`.
- Modifiche additive: `config/auth.php` (guard `admin`), `bootstrap/app.php` (alias + redirect path-aware), `routes/web.php` (gruppo `/control-room`), `ProvisionTenant`/`AdminTenantController` (uso delle azioni condivise — comportamento API invariato), `config/branding.php` (`asset_disk`).
- **Nessuna nuova migration**: riuso di `tenants/plans/subscriptions/tenant_features/brand_profiles/brand_assets/users/password_reset_tokens/audit_logs`.
