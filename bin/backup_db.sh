#!/bin/bash

# homeadmin24 WEG Management - Database Backup Script
# Usage: ./bin/backup_db.sh [description]
# Example: ./bin/backup_db.sh before_migration

set -e

# Configuration
DB_HOST="127.0.0.1"
DB_USER="root"
DB_NAME="homeadmin24"
BACKUP_DIR="$(dirname "$0")/../backup"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate filename
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
DESCRIPTION=${1:-"manual"}
FILENAME="backup_${TIMESTAMP}_${DESCRIPTION}.sql"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

echo "🔄 Creating database backup..."
echo "📁 File: ${FILENAME}"

# Create backup with options that make restoration easier:
# - Exclude doctrine_migration_versions to avoid conflicts
# - Include routines, triggers
# - Use single transaction for consistency
mysqldump -h"$DB_HOST" -u"$DB_USER" \
          --routines --triggers --single-transaction \
          --ignore-table="${DB_NAME}.doctrine_migration_versions" \
          "$DB_NAME" 2>/dev/null > "$FILEPATH"

# Check if backup was successful
if [ -f "$FILEPATH" ] && [ -s "$FILEPATH" ]; then
    # Get file size
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
    echo "🔧 To restore this backup, use:"
    echo "   ./bin/restore_db.sh ${FILEPATH}"
    echo ""
    echo "📝 Note: Migration versions table excluded to avoid conflicts"
    echo "   After restore, run: docker compose exec web php bin/console doctrine:schema:update --force"
    echo ""
    echo "🗂  Recent backups:"
    ls -lah "$BACKUP_DIR"/*.sql 2>/dev/null | tail -5 || echo "   (This is your first backup)"
else
    echo "❌ Backup failed!"
    exit 1
fi