#!/usr/bin/env bash
# Sauvegarde quotidienne de PostgreSQL. À planifier avec cron sur l'hôte :
#   0 3 * * * /home/<user>/video_streaming/scripts/backup-db.sh
set -euo pipefail
cd "$(dirname "$0")/.."

BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
mkdir -p "$BACKUP_DIR"

docker compose -f compose.prod.yml --env-file .env.prod exec -T postgres \
  pg_dump -U homeflix homeflix | gzip > "$BACKUP_DIR/homeflix-$(date +%F).sql.gz"

find "$BACKUP_DIR" -name 'homeflix-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
