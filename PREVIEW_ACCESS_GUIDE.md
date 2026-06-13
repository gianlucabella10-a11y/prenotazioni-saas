# PREVIEW_ACCESS_GUIDE — Come provare il prodotto (guida non tecnica)

Questa guida ti fa vedere e provare il prodotto reale (non un mockup): l'app
del cliente finale e la dashboard del professionista, collegate al backend
vero con dati reali. Tutto gira **sul tuo Mac, in locale**.

> ⚠️ I server girano finché questa sessione è attiva. Se chiudi tutto e vuoi
> riavviare, vedi la sezione "Riavviare i server" in fondo.

---

## 1. Cosa puoi aprire adesso

| Cosa | Indirizzo | Come si apre |
|---|---|---|
| **Dashboard del professionista** | http://127.0.0.1:8000/dashboard/login | Browser (Chrome/Safari) |
| **App del cliente finale** (anteprima web) | http://127.0.0.1:4280 | Browser — è la stessa identica app che andrà su iPhone/Android |
| **Backend / API** | http://127.0.0.1:8000 | Non si "apre", è il motore che alimenta tutto |
| **Posta di test** (vedi le email che il sistema invia) | http://127.0.0.1:8025 | Browser — qui arrivano codici di verifica e promemoria |

---

## 2. Credenziali demo

### 👔 Professionista (dashboard di gestione)
```
Indirizzo: http://127.0.0.1:8000/dashboard/login
Email:     demo.titolare@saloneverdi.it
Password:  DemoTitolare2026!
```
Da qui puoi: vedere la home con gli appuntamenti, gestire **Servizi**,
**Operatori**, **Disponibilità** (orari e ferie), confermare/annullare
**Prenotazioni**, e cambiare nome/colori dell'app dei clienti in
**Personalizzazione**.

> 🔒 Nota di sicurezza: per comodità di demo, su questo account la verifica
> in due passaggi (MFA) è **disattivata**. In produzione, per i titolari
> resta **obbligatoria** (così com'è progettato). Non è una falla: è una
> scelta solo per questo account demo.

### 📱 Cliente finale (app di prenotazione)
```
Indirizzo: http://127.0.0.1:4280
Email:     demo.cliente@example.com
Password:  DemoCliente2026!
```
Questo account è stato creato col **flusso reale** (registrazione + verifica
email): è già verificato, quindi puoi accedere e prenotare subito. Da qui
puoi: scegliere un servizio, scegliere l'operatore (Marco), scegliere
giorno e orario tra gli slot reali, confermare, vedere lo storico e annullare.

---

## 3. Un giro di prova consigliato (5 minuti)

1. Apri la **dashboard** e accedi come professionista → guarda Servizi e Operatori.
2. In **Personalizzazione**, cambia il "Colore principale" e salva.
3. Apri l'**app cliente** (http://127.0.0.1:4280) e ricarica: l'app ha
   cambiato colore. *Questo è il cuore "white-label": un solo software,
   ogni attività con il proprio marchio.*
4. Nell'app cliente accedi con l'account cliente e fai una **prenotazione**.
5. Torna nella **dashboard → Prenotazioni**: vedi l'appuntamento appena creato
   e puoi confermarlo o annullarlo.
6. Apri la **posta di test** (http://127.0.0.1:8025) per vedere le email che
   il sistema ha inviato (conferme, promemoria).

L'attività demo si chiama **"Salone Verdi"** (un barbiere) con 3 servizi
(Taglio, Barba, Taglio+barba) e l'operatore **Marco**.

---

## 4. App su iPhone (simulatore) — stato e cosa manca

**Buona notizia**: Xcode 26.5 è installato e i simulatori iPhone ci sono.
**Manca un solo pezzo**: *CocoaPods*, lo strumento che assembla le librerie
iOS. Non si è potuto installare perché la versione di **Ruby di sistema del
Mac (2.6) è troppo vecchia** per le versioni attuali.

**Come sbloccarlo (il modo più semplice per te, ~10 minuti):**

1. Installa Homebrew (incolla nel Terminale, una riga, segui le istruzioni):
   ```
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```
2. Installa CocoaPods (Homebrew porta con sé una Ruby moderna, quindi
   funziona):
   ```
   brew install cocoapods
   ```
3. Dimmi "CocoaPods installato": penso io a compilare e avviare l'app sul
   simulatore iPhone, e ti mando lo screenshot.

> Nel frattempo, l'anteprima web (punto 1) mostra **esattamente** la stessa
> app: stesso codice, stesse schermate, stesse funzioni. La differenza è solo
> la "cornice" (browser invece di iPhone).

---

## 5. GitHub — stato e cosa fare

Il progetto **ora è sotto controllo di versione Git in locale** (ho fatto il
primo commit, senza includere password o segreti). **Non è ancora collegato a
GitHub** perché serve il TUO account — non creo repository a tuo nome.

**Per collegarlo (5 minuti):**

1. Crea un repository vuoto su https://github.com/new (es. `prenotazioni-saas`),
   **senza** inizializzarlo con README.
2. Collega e carica (sostituisci `TUO-UTENTE`):
   ```
   cd "/Users/gianlucabella/Desktop/app prenotazioni progetto"
   git remote add origin https://github.com/TUO-UTENTE/prenotazioni-saas.git
   git push -u origin main
   ```
   GitHub ti chiederà di autenticarti (browser o token).
3. Se preferisci, dimmi "ho creato il repo su GitHub con questo URL: ..." e
   imposto io remote + push (l'autenticazione resta tua).

---

## 6. Problemi bloccanti

| Tema | Stato |
|---|---|
| Anteprima web app cliente | ✅ Funziona |
| Dashboard professionista | ✅ Funziona |
| Backend + email di test | ✅ Funziona |
| Login demo (entrambi) | ✅ Verificati |
| Simulatore iPhone | ⚠️ Bloccato su CocoaPods (vedi §4: installa Homebrew + CocoaPods) |
| GitHub remoto | ⚠️ Serve il tuo account (vedi §5) |

Nessun problema bloccante sull'anteprima: puoi vedere e provare tutto **ora**
dal browser. iPhone e GitHub richiedono un piccolo passo tuo, spiegato sopra.

---

## 7. Riavviare i server (se li hai chiusi)

Apri il Terminale e lancia (tre comandi, lasciali aperti):
```
export PATH="$HOME/.local/php-toolchain/bin:$HOME/.local/flutter/bin:$PATH"

# 1) Posta di test
~/.local/mailpit/mailpit --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025 &

# 2) Backend + dashboard
cd "/Users/gianlucabella/Desktop/app prenotazioni progetto/platform-backend"
php artisan serve --host=127.0.0.1 --port=8000 &

# 3) App cliente (anteprima web)
cd "/Users/gianlucabella/Desktop/app prenotazioni progetto/platform-mobile/apps/client_app"
php -S 127.0.0.1:4280 -t build/web &
```
Poi riapri gli indirizzi del punto 1.
