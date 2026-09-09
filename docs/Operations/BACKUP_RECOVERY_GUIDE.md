# BACKUP_RECOVERY_GUIDE (FASE 10)

> Cosa salvare, come, e come ripristinare il centro operativo. Script pronto: `backup-control-center.sh`.

## Cosa va salvato
1. **Database** — tenant, brand, app_projects, app_builds, token, tester, feedback, versioni.
2. **Storage** (`platform-backend/storage/app`) — APK in `builds/`, manifest in `app_factory/`, pacchetti in `generated_apps/`, asset in `public/brand/`.
3. **Segreti** (FUORI dal repo, da custodire a parte): `~/.local/keystore/platform.jks` + `~/.local/keystore/keystore.env`, `.env`.

## Backup automatico
```bash
bash backup-control-center.sh
#  → backups/backup-YYYYmmdd-HHMMSS.tar.gz  (database + storage/app)
```
Pianificabile (cron/launchd) una volta al giorno.

### Database non-sqlite (produzione)
```bash
# MySQL
mysqldump -u <user> -p <db> > backups/db-$(date +%F).sql
# PostgreSQL
pg_dump -U <user> <db> > backups/db-$(date +%F).sql
```

### Keystore (CRITICO)
La keystore di firma **non è ricreabile**: se la perdi non puoi più aggiornare le app già distribuite. Copiala in un vault/cloud cifrato:
```bash
cp ~/.local/keystore/platform.jks /percorso/sicuro/   # + keystore.env
```

## Ripristino
```bash
# 1) estrai il backup
mkdir -p /tmp/restore && tar -xzf backups/backup-YYYYmmdd-HHMMSS.tar.gz -C /tmp/restore

# 2) database (sqlite)
cp /tmp/restore/database.sqlite platform-backend/database/database.sqlite
#    (MySQL/PG: mysql < db.sql  /  psql < db.sql)

# 3) storage
tar -xzf /tmp/restore/storage-app.tar.gz -C platform-backend/storage

# 4) verifica
cd platform-backend && php artisan migrate:status && php artisan storage:link
```

## Disaster recovery (nuova macchina)
1. Installa toolchain (`BUILD_MACHINE_SETUP.md`) + repo.
2. Ripristina keystore (`~/.local/keystore/`) + `.env`.
3. Ripristina database + storage (sopra).
4. `bash START_CONTROL_CENTER.command`.

## Retention consigliata
- Giornaliero per 7 giorni, settimanale per 4 settimane. Keystore + .env in vault separato (non nei backup di routine sul disco di lavoro).
