#!/usr/bin/env bash
# Daily DB backup — runs on the production server via cron.
# Keeps the last 30 days of gzipped mysqldumps in /var/backups/pandora/.
#
# Install once on the server:
#   chmod +x /var/www/pandora/scripts/db-backup.sh
#   echo "0 3 * * * /var/www/pandora/scripts/db-backup.sh >> /var/log/pandora-db-backup.log 2>&1" | crontab -
#
# Restore with:
#   gunzip < /var/backups/pandora/2026-04-17_030000.sql.gz | mysql -uroot pandora
set -euo pipefail

BACKUP_DIR="/var/backups/pandora"
RETENTION_DAYS=30
ENV_FILE="/var/www/pandora/backend/.env"

mkdir -p "$BACKUP_DIR"

# Read DB creds from Laravel .env
if [[ ! -f "$ENV_FILE" ]]; then
  echo "❌ .env not found at $ENV_FILE"
  exit 1
fi

get_env() { grep -E "^${1}=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }

DB_HOST=$(get_env DB_HOST)
DB_PORT=$(get_env DB_PORT)
DB_DATABASE=$(get_env DB_DATABASE)
DB_USERNAME=$(get_env DB_USERNAME)
DB_PASSWORD=$(get_env DB_PASSWORD)

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"

TIMESTAMP=$(date +%Y-%m-%d_%H%M%S)
OUT="${BACKUP_DIR}/${TIMESTAMP}.sql.gz"

echo "[$(date -Iseconds)] backup → $OUT"

# Dump + gzip in one stream so we never keep an uncompressed file on disk
mysqldump \
  --host="$DB_HOST" --port="$DB_PORT" \
  --user="$DB_USERNAME" --password="$DB_PASSWORD" \
  --single-transaction --routines --triggers --events \
  --no-tablespaces \
  "$DB_DATABASE" \
  | gzip > "$OUT"

SIZE=$(du -h "$OUT" | cut -f1)
echo "[$(date -Iseconds)] ✓ done (${SIZE})"

# Prune old backups
find "$BACKUP_DIR" -name '*.sql.gz' -type f -mtime "+${RETENTION_DAYS}" -print -delete | \
  sed "s|^|[$(date -Iseconds)] pruned |"

# Count remaining
REMAINING=$(find "$BACKUP_DIR" -name '*.sql.gz' -type f | wc -l | tr -d ' ')
echo "[$(date -Iseconds)] ✓ ${REMAINING} backups kept (retention ${RETENTION_DAYS}d)"
