# client_app — App Cliente White Label (Flutter)

L'app mobile dei clienti finali: **una sola codebase per tutti i tenant**. Nessun codice specifico cliente: l'identità (nome, tema, sedi, catalogo, feature) arriva a runtime da `GET /app/config` del backend (`platform-backend/`). Le uniche cose compilate nella build sono bundle id, nome, icona, splash e la `TENANT_KEY` (docs/27).

## Architettura

- **Feature-first + Clean Architecture**: `lib/features/<feature>/{domain,data,application,presentation}` con il trasversale in `lib/core` e composition root in `lib/app/providers.dart`
- **Stato**: Riverpod 3 (Notifier/AsyncNotifier, DI via provider, override nei test)
- **Routing**: go_router con redirect su stato config (splash → courtesy screen per tenant sospesi → login → home)
- **Networking**: Dio con interceptor che inietta `X-Tenant-Key` e `Authorization`, **refresh token single-flight** su 401 con replay della richiesta, mapping uniforme degli errori in `ApiFailure(code)` coerente con l'envelope backend
- **Sicurezza token**: access+refresh in `flutter_secure_storage` (Keychain/Keystore), astratti da `TokenStorage` (fake in-memory nei test)
- **White Label Engine**: `WhiteLabelRepository` (ETag/304 + cache locale offline-safe) → `WhiteLabelConfig` → `AppThemeBuilder` (token → ThemeData M3). Cambiare tenant = cambiare `--dart-define`, zero modifiche al codice
- **Booking flow**: `BookingFlowController` con invarianti testate — cambiare servizi azzera staff/slot, cambiare staff/giorno azzera lo slot, ogni slot scelto conia una **Idempotency-Key** stabile per i retry e rigenerata al cambio slot; `slot_unavailable` (409) svuota lo slot e fa ricaricare la disponibilità

## Ambienti

`--dart-define`: `ENV` (development|staging|production), `API_BASE_URL`, `TENANT_KEY`. La pipeline white label (docs/27 §3) inietta questi valori per tenant; in locale:

```bash
flutter run \
  --dart-define=ENV=development \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1 \
  --dart-define=TENANT_KEY=API_KEY_DEL_TENANT
```

L'app **fallisce all'avvio** se `TENANT_KEY` manca: una build white label senza identità è un errore di packaging.

## Schermate

Splash (caricamento config con retry) · Tenant non disponibile (courtesy, docs/27 §7) · Login/Registrazione · Home (brand, prossimo appuntamento, CTA) · Informazioni attività e sedi · Selezione servizi multipli (con incompatibilità staff disabilitate) · Data+operatore+orario (strip giorni, chip staff, slot Mattina/Pomeriggio) · Conferma (dati reali persistiti) · Le mie prenotazioni (in programma/passate, annulla con conferma e gestione cutoff) · Profilo (logout).

## Test

```bash
flutter test          # 25 unit + widget (l'e2e si auto-salta senza backend)
flutter analyze       # zero issues
```

- **Unit**: parsing config (incl. payload courtesy e prova "due tenant, stesso codice"), theme builder (token, hex invalidi degradano senza crash, scala tipografica), grouping slot mattina/pomeriggio, mapping errori, **invarianti del BookingFlowController** (idempotency key stabile sui retry, slot azzerato su 409)
- **Widget**: login (brand da config runtime, validazioni), lista prenotazioni (empty state, card, dialog cancellazione, chip "in attesa")
- **E2E contro backend reale** (`test/e2e`, tag `e2e`): config → registrazione → catalogo → disponibilità → **prenotazione con replay idempotente** → slot rimosso → doppia prenotazione 409 → storico → cancellazione → slot riaperto:

```bash
# col backend in `php artisan serve` e un tenant provisionato:
flutter test test/e2e --tags e2e \
  --dart-define=TENANT_KEY=API_KEY_DEL_TENANT \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

## Stato verso TestFlight

Il codice è pronto per la build iOS (progetto `ios/` generato, nessuna dipendenza nativa oltre secure storage/shared_preferences). I passi rimanenti sono **ambientali**, non di codice: Xcode completo (questa macchina ha solo i CommandLineTools), account Apple Developer del tenant (strategia docs/27 §6), firma e `flutter build ipa` dalla pipeline (docs/27 §3). Push FCM: l'integrazione client arriva col backend `PUT /me/devices` (gap P0, GIUFFRIDA_FEATURE_GAP §4.2) — l'astrazione è predisposta.

## Dipendenze segnalate verso il backend (niente workaround, per scelta)

- **Note sulla prenotazione**: campo non ancora supportato dall'API (P0 nel gap) → la UI lo aggiunge con l'endpoint
- **Richiesta senza slot ("INVIA RICHIESTA")**: richiede `POST /booking-requests` (P0)
- **Orari/social/galleria nella scheda attività**: estensione del payload config (P0)
- **Modifica profilo/cancellazione account**: `GET/PATCH /me`, `/me/gdpr/erasure` (P0/P1)
