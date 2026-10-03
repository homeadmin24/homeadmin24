#!/bin/bash

# Create a production database backup without storing credentials in this file.
# The MySQL container receives MYSQL_ROOT_PASSWORD from the server-only
# .env.prod.local file; Docker Compose passes it into this command.

set -euo pipefail

APP_DIR="/opt/homeadmin24-prod"
BACKUP_DIR="$APP_DIR/backups"
ENV_FILE="$APP_DIR/.env.prod.local"
DATE="$(date +%Y%m%d_%H%M%S)"

if [ ! -r "$ENV_FILE" ]; then
    echo "Error: production credentials file is missing: $ENV_FILE" >&2
    exit 1
fi

DATABASE_NAME="$(sed -n -E 's#^DATABASE_URL="?mysql://[^/]+/([A-Za-z0-9_]+)\?.*$#\1#p' "$ENV_FILE" | tail -n 1)"
if ! [[ "$DATABASE_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Error: could not determine a safe database name from $ENV_FILE" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
find "$BACKUP_DIR" -name 'homeadmin24_prod_*.sql.gz' -mtime +30 -delete

TEMP_FILE="$(mktemp "$BACKUP_DIR/.homeadmin24_prod_${DATE}.XXXXXX")"
trap 'rm -f "$TEMP_FILE"' EXIT

cd "$APP_DIR"
docker compose -f docker-compose.yaml -f docker-compose.prod.yml exec -T mysql \
    sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$1"' sh "$DATABASE_NAME" \
    | gzip > "$TEMP_FILE"

mv "$TEMP_FILE" "$BACKUP_DIR/homeadmin24_prod_${DATE}.sql.gz"
trap - EXIT

echo "Backup created: homeadmin24_prod_${DATE}.sql.gz"
