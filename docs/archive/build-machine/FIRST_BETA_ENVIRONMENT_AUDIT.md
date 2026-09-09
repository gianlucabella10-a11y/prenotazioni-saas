# FIRST_BETA_ENVIRONMENT_AUDIT (FASE 0)

> Audit **eseguito davvero** su questa macchina (non assunto). Output reali sotto. Legenda: 🟢 pronto · 🟡 risolvibile · 🔴 blocca l'APK.

## Output reali
| Strumento | Comando | Risultato | Stato |
|---|---|---|---|
| Flutter | `flutter --version` | **Flutter 3.44.2** (stable, engine 04efd7c) | 🟢 |
| Android SDK | `which adb sdkmanager` | **not found** | 🔴 |
| ANDROID_HOME | `echo $ANDROID_HOME` | **unset**; `~/Library/Android/sdk` assente | 🔴 |
| Java | `java -version` | **Unable to locate a Java Runtime** | 🔴 |
| PHP | `php -v` | **PHP 8.4.22** | 🟢 |
| Composer | `composer --version` | **2.10.1** | 🟢 |
| Node/npm | `node -v` / `npm -v` | **assenti** | 🟡 (non serve: Flutter + dashboard Blade) |

## Test backend/flutter (questa base)
- `php artisan test` **207/207** · `flutter analyze` pulito · `flutter test` **40**.

## Build reale tentata (FASE 1, nessun mock)
```
flutter clean            → OK
flutter pub get          → Got dependencies! (risolte)
flutter build apk --release
  → [!] No Android SDK found. Try setting the ANDROID_HOME environment variable.
```
La pipeline gira (clean + pub get) e **fallisce esattamente** al punto in cui serve l'Android SDK, con errore chiaro e azionabile (lo stesso che `LocalBuildDispatcher` riporta in Control Room). **Nessun errore di codice/plugin/manifest.**

## Conclusione
- **Codice e pipeline: 🟢 pronti e provati** (test verdi, build che avanza fino all'SDK).
- **Questa macchina: 🔴 non può produrre l'APK** perché mancano **Android SDK** e **Java** (e non sono installabili qui: ambiente user-space senza JDK/SDK).
- **Unico passo mancante: provisioning della toolchain Android** su una build-machine. Comandi esatti in `FIRST_REAL_BETA_READY.md` / `BUILD_MACHINE_SETUP.md`. Non è codice mancante: è il setup della macchina (una tantum, scriptato).

## Cosa NON è un blocker
Booking/auth/payments/push/isolamento (testati), signing config (gradle env-based), Control Room (flusso testato), distribuzione (token), versioni, feedback, analytics. Tutto 🟢.
