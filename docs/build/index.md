# docs/build/ — Build Pipeline

Cartella indice (nessun contenuto duplicato — il Build Engine è documentato in dettaglio altrove, qui solo i puntatori).

| Argomento | Documento |
|---|---|
| Pipeline completa (Genera → Build → APK → checksum) | `REAL_PROJECT_STATE.md` §Fase 7, `PROJECT_FREEZE_STATE.md` §8 (radice) |
| Segreti CI richiesti | [`docs/AppFactory/APP_FACTORY_RELEASE_SECRETS.md`](../AppFactory/APP_FACTORY_RELEASE_SECRETS.md) |
| Setup macchina di build | [`docs/Deployment/BUILD_MACHINE_SETUP.md`](../Deployment/BUILD_MACHINE_SETUP.md) |
| Firma Android | [`docs/Deployment/SIGNING_SETUP.md`](../Deployment/SIGNING_SETUP.md) |
| **Gap noto** (🔴, non risolto in questa fase) | Firma release ricade su debug se le env keystore mancano; `versionCode`/`versionName` statici — vedi `TECHNICAL_DEBT.md` |
