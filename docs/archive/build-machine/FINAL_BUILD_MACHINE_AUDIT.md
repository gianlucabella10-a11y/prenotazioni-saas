# FINAL_BUILD_MACHINE_AUDIT (FASE 0 + FASE 1)

> Audit **eseguito sul serio** su questa macchina + **toolchain installata davvero**. Risultato: da "impossibile" a **APK reale prodotta**.

## Prima (audit iniziale)
| Strumento | Risultato | Stato |
|---|---|---|
| `flutter --version` | Flutter 3.44.2 (stable) | 🟢 |
| `java -version` | Unable to locate a Java Runtime | 🔴 → risolto |
| `adb` / `sdkmanager` | not found | 🔴 → risolto |
| `ANDROID_HOME` | unset | 🔴 → risolto |
| PHP / Composer | 8.4.22 / 2.10.1 | 🟢 |
| node / npm | assenti | 🟡 (non necessari) |

## Installazione eseguita (FASE 1, user-space, senza sudo)
| Componente | Dove | Verifica |
|---|---|---|
| **Temurin JDK 17.0.19** | `~/.local/toolchain/jdk-17.0.19+10` | `java -version` ✅ |
| **Android cmdline-tools 12.0** | `~/android-sdk/cmdline-tools/latest` | `sdkmanager --version` ✅ |
| **platform-tools** (adb) | `~/android-sdk/platform-tools` | ✅ |
| **platforms;android-34/35/36** | `~/android-sdk/platforms` | ✅ (35 auto durante build) |
| **build-tools;34.0.0 / 36.0.0** | `~/android-sdk/build-tools` | ✅ |
| **cmake 3.22.1** | `~/android-sdk/cmake` | ✅ (auto durante build) |
| licenze SDK | `~/android-sdk/licenses` | accettate ✅ |
| Flutter ↔ SDK/JDK | `flutter config --android-sdk … --jdk-dir …` | persistito ✅ |

## Dopo (verificato con build reale)
- `flutter build apk --release` → **✓ Built app-release.apk (56 MB)** (nessun mock).
- Quindi **Android toolchain: 🟢 OPERATIVA** su questa macchina.

## Variabili (per worker/SaaS)
`flutter config` persiste SDK/JDK per i comandi `flutter` diretti. Per la build via SaaS (`php artisan app:build` → Process → flutter) il processo eredita l'env del worker: esporta nel servizio/`.zshrc`:
```bash
export JAVA_HOME="$HOME/.local/toolchain/jdk-17.0.19+10/Contents/Home"
export ANDROID_HOME="$HOME/android-sdk"
export PATH="$HOME/.local/flutter/bin:$ANDROID_HOME/platform-tools:$PATH"
```

## Classificazione finale
- **Build machine: 🟢 PRONTA** — toolchain installata e verificata producendo un APK reale.
- **Device fisico Android: 🟡** — non collegato a questa macchina (`flutter doctor` vede Chrome + macOS, nessun telefono). L'installazione avviene aprendo il beta link sul telefono o `adb install` con un telefono connesso.
