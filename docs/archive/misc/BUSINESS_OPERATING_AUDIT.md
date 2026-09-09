# BUSINESS_OPERATING_AUDIT (FASE 0)

> Audit reale per l'uso quotidiano come centro operativo. 🟢 funzionante · 🟡 migliorabile · 🔴 bloccante.

## BACKEND
| Voce | Stato | Nota |
|---|---|---|
| Laravel / PHP | 🟢 | Laravel **13.15.0**, PHP **8.4.22**. |
| Environment | 🟢 | `.env` (local); `.env` non committato (gitignore). |
| Database | 🟢 (dev) · 🟡 (prod) | sqlite per uso locale; in produzione DB gestito. |
| Storage | 🟢 | `local` privato (artifact/manifest) + `public` (`storage:link`). |
| Queue | 🟢 | `database` + worker (`START_CONTROL_CENTER`). |
| Scheduler / cron | 🟢 | `schedule:work` nel launcher (nessun task pianificato ad oggi). |
| Workers | 🟢 | `queue:work` avviato dal launcher. |

## CONTROL ROOM
| Voce | Stato |
|---|---|
| Login (super-admin + MFA) | 🟢 |
| Ruoli / permessi (guard `admin`, EnsureSuperAdmin) | 🟢 |
| Gestione tenant / app / build / versioni | 🟢 |
| Account proprietario reale | 🟢 (`owner@platform.local` creato) |

## BUILD MACHINE
| Voce | Stato |
|---|---|
| Flutter 3.44.2 | 🟢 |
| Java (JDK 17.0.19) | 🟢 |
| Android SDK (platform-tools/34/35/36, build-tools 34/36, cmake) | 🟢 |
| Gradle | 🟢 (wrapper) |
| Signing / keystore | 🟢 (`~/.local/keystore/platform.jks`, ENV) |

## DELIVERY
| Voce | Stato |
|---|---|
| Beta link (token) | 🟢 |
| Download / scadenza / limite / revoca / conteggio | 🟢 |
| Sicurezza (nessun APK esposto direttamente) | 🟢 |
| URL pubblico raggiungibile da telefono | 🟡 | localhost; per il telefono serve tunnel/deploy (`DEPLOYMENT_READY.md`). |

## MOBILE
| Voce | Stato |
|---|---|
| API base URL (per-ambiente, dart-define) | 🟢 |
| Environments | 🟢 |
| Crash reporting (Sentry) | 🟢 |
| Analytics (AnalyticsService) | 🟢 |

## OPERATIONS
| Voce | Stato | Nota |
|---|---|---|
| Backup | 🟡 → 🟢 | aggiunti `backup-control-center.sh` + `BACKUP_RECOVERY_GUIDE.md`. |
| Logs | 🟢 | `storage/logs/*` + build log per-build in Control Room. |
| Monitoring | 🟢 | Sentry (crash) + dashboard Flotta (build/beta/feedback). |

## Sintesi
Centro operativo **pronto all'uso locale**: launcher one-click, worker/scheduler, admin reale, Barber Rossi creato, APK firmato. Unico 🟡 strutturale: **URL pubblico** (deploy/tunnel) per far installare da telefoni esterni — non è codice, è infrastruttura. Piano nel `BUSINESS_READY_REPORT.md`.
