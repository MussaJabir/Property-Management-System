#!/usr/bin/env bash
#
# Nightly Postgres backup. Runs on the host as `opc` from a cron entry.
# Writes a gzipped pg_dump locally, then uploads to Backblaze B2 via the
# rclone configured by the operator (see DEPLOYMENT.md). 30-day retention
# enforced on the B2 bucket via lifecycle rule — this script doesn't prune.
#
# Install:
#   crontab -e
#   30 2 * * *  /opt/pms/backup-postgres.sh >> /opt/pms/backups/backup.log 2>&1

set -euo pipefail

STAMP=$(date -u +%Y%m%d-%H%M%S)
BACKUP_DIR=/opt/pms/backups
LOCAL_FILE="${BACKUP_DIR}/pms-${STAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

# Dump from the postgres container. --env-file picks DB_USERNAME/DB_DATABASE.
cd /opt/pms
docker compose -f docker-compose.production.yml --env-file .env exec -T pgsql \
    pg_dump -U "${DB_USERNAME:-pms}" -d "${DB_DATABASE:-pms}" \
    | gzip > "$LOCAL_FILE"

echo "[$(date -u +%FT%TZ)] wrote ${LOCAL_FILE} ($(du -h "$LOCAL_FILE" | cut -f1))"

# Upload if rclone is configured ("b2:" remote name expected). Skip silently
# if not — local backup still wins.
if command -v rclone &> /dev/null && rclone listremotes 2>/dev/null | grep -q '^b2:'; then
    rclone copy "$LOCAL_FILE" "b2:${B2_BACKUP_BUCKET:-pms-backups}/$(date -u +%Y/%m)/"
    echo "[$(date -u +%FT%TZ)] uploaded to B2"
fi

# Prune local copies older than 7 days — B2 keeps the 30-day window.
find "$BACKUP_DIR" -name 'pms-*.sql.gz' -mtime +7 -delete
