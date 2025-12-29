#!/bin/bash

# homeadmin24 Database Backup Script
# Usage: ./bin/backup_db.sh [description]
# Example: ./bin/backup_db.sh before_migration

set -e

BACKUP_DIR="$(dirname "$0")/../backup"
mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
DESCRIPTION=${1:-"manual"}
FILENAME="backup_${TIMESTAMP}_${DESCRIPTION}.sql"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

echo "🔄 Creating database backup..."
echo "📁 File: ${FILENAME}"

docker compose exec -T mysql mysqldump -uroot -prootpassword \
    --set-gtid-purged=OFF \
    --ignore-table=homeadmin24.doctrine_migration_versions \
    --routines --triggers --single-transaction \
    homeadmin24 2>/dev/null > "$FILEPATH"

if [ -f "$FILEPATH" ] && [ -s "$FILEPATH" ]; then
    SIZE=$(wc -c < "$FILEPATH")
    if [ $SIZE -ge 1048576 ]; then
        SIZE_FORMATTED="$(awk "BEGIN {printf \"%.2f MB\", $SIZE/1048576}")"
    elif [ $SIZE -ge 1024 ]; then
        SIZE_FORMATTED="$(awk "BEGIN {printf \"%.2f KB\", $SIZE/1024}")"
    else
        SIZE_FORMATTED="${SIZE} B"
    fi

    echo "✅ Backup created successfully!"
    echo "📊 Size: ${SIZE_FORMATTED}"
    echo "💾 Saved to: ${FILEPATH}"
    echo ""
    echo "🔧 To restore: ./bin/restore_db.sh ${FILEPATH}"
else
    echo "❌ Backup failed!"
    exit 1
fi
