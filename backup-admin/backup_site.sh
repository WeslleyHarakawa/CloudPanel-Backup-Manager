#!/bin/bash
SITE_USER="$1"
DOMAIN="$2"
DOCROOT="$3"
DB_HOST="$4"
DB_NAME="$5"
DB_USER="$6"
DB_PASS="$7"
TIMESTAMP="$8"
if [ -z "$SITE_USER" ] || [ -z "$DOMAIN" ] || [ -z "$DOCROOT" ] || [ -z "$DB_NAME" ] || [ -z "$TIMESTAMP" ]; then
  echo "Usage: backup_site.sh SITE_USER DOMAIN DOCROOT DB_HOST DB_NAME DB_USER DB_PASS TIMESTAMP" >&2
  exit 1
fi
BASE_DIR="/home/backup-admin/backups/${DOMAIN}-${TIMESTAMP}"
mkdir -p "$BASE_DIR"
cd "$DOCROOT" || exit 1
zip -r "$BASE_DIR/site.zip" . >/dev/null
if [ -n "$DB_NAME" ]; then
  mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BASE_DIR/db.sql" 2>"$BASE_DIR/mysqldump.log" || true
fi
chown -R backup-admin:backup-admin "$BASE_DIR"
echo "$BASE_DIR"

