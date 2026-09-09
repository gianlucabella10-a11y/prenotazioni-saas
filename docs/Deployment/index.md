# docs/Deployment/ — Ambienti, build machine, deploy

| File | Scopo |
|---|---|
| `ENVIRONMENT_GUIDE.md` | Matrice ambienti LOCAL/STAGING/BETA/PRODUCTION (documento unico, consolida 3 versioni precedenti) |
| `BUILD_MACHINE_SETUP.md` | Setup toolchain per una macchina di build (Flutter, Android SDK, JDK) |
| `SIGNING_SETUP.md` | Creazione della keystore Android di piattaforma |
| `DEPLOYMENT_READY.md` | Comandi per esporre il backend pubblicamente (tunnel/deploy) |
| `RELEASE_PROCESS.md` | Processo di versione/rollback via Control Room |

Riferimento infrastruttura reale: [`platform-infra/`](../../platform-infra/) (Terraform, `deploy.sh`, runbook). Stato verificato dell'infrastruttura: `REAL_PROJECT_STATE.md` §Fase 9.
