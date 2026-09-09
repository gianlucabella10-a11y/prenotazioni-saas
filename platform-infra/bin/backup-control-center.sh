#!/bin/bash
# Backup del centro operativo: database + storage (artifact/manifest/asset).
# Uso:  bash backup-control-center.sh   →  crea backups/backup-YYYYmmdd-HHMMSS.tar.gz
set -euo pipefail
cd "$(dirname "$0")/platform-backend"
export PATH="$HOME/.local/php-toolchain/bin:$PATH"

TS=$(date +%Y%m%d-%H%M%S)
OUT="../backups"; mkdir -p "$OUT"
STAGE=$(mktemp -d)

echo "▶ Backup $TS"

# --- Database -----------------------------------------------------------------
DB_CONN=$(php -r 'echo config("database.default");' 2>/dev/null || echo sqlite)
if [ "$DB_CONN" = "sqlite" ]; then
  DBFILE=$(php -r 'echo config("database.connections.sqlite.database");')
  cp "$DBFILE" "$STAGE/database.sqlite"
  echo "  db (sqlite): ok"
else
  # MySQL/Postgres: usa il dump nativo con le credenziali del .env
  echo "  db ($DB_CONN): usa mysqldump/pg_dump (vedi BACKUP_RECOVERY_GUIDE.md)"
fi

# --- Storage (artifact APK, manifest, asset, pacchetti) -----------------------
tar -czf "$STAGE/storage-app.tar.gz" -C storage app
echo "  storage/app: ok"

# --- Pacchetto finale ---------------------------------------------------------
tar -czf "$OUT/backup-$TS.tar.gz" -C "$STAGE" .
rm -rf "$STAGE"
echo "✅ $OUT/backup-$TS.tar.gz"
ls -lh "$OUT/backup-$TS.tar.gz" | awk '{print "   "$5}'
