#!/bin/bash

# homeadmin24 WEG Management - Database Restore Script
# Usage: ./bin/restore_db.sh <backup_file>
# Example: ./bin/restore_db.sh backup/backup_20251228_manual.sql

set -e

# Configuration
DB_HOST="127.0.0.1"
DB_USER="root"
DB_NAME="homeadmin24"

# Check if backup file is provided
if [ -z "$1" ]; then
    echo "❌ Error: No backup file specified"
    echo ""
    echo "Usage: $0 <backup_file>"
    echo ""
    echo "Available backups:"
    ls -lah backup/*.sql 2>/dev/null | tail -10 || echo "  (No backups found)"
    exit 1
fi

BACKUP_FILE="$1"

# Check if file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Get file size for display
SIZE=$(wc -c < "$BACKUP_FILE")
if [ $SIZE -ge 1048576 ]; then
    SIZE_FORMATTED="$(awk "BEGIN {printf \"%.2f MB\", $SIZE/1048576}")"
elif [ $SIZE -ge 1024 ]; then
    SIZE_FORMATTED="$(awk "BEGIN {printf \"%.2f KB\", $SIZE/1024}")"
else
    SIZE_FORMATTED="${SIZE} B"
fi

echo "==========================================="
echo "🔄 Database Restore"
echo "==========================================="
echo "📁 File: $(basename "$BACKUP_FILE")"
echo "📊 Size: $SIZE_FORMATTED"
echo "🎯 Target: $DB_NAME"
echo ""
echo "⚠️  WARNING: This will REPLACE all data in the database!"
echo ""
read -p "Are you sure you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Restore cancelled"
    exit 0
fi

echo ""
echo "🔄 Creating safety backup of current database..."
SAFETY_BACKUP="backup/backup_$(date '+%Y%m%d_%H%M%S')_before_restore.sql"
mkdir -p backup
mysqldump -h"$DB_HOST" -u"$DB_USER" \
          --routines --triggers --single-transaction \
          "$DB_NAME" 2>/dev/null > "$SAFETY_BACKUP"

if [ -f "$SAFETY_BACKUP" ] && [ -s "$SAFETY_BACKUP" ]; then
    echo "✅ Safety backup created: $SAFETY_BACKUP"
else
    echo "❌ Failed to create safety backup. Aborting."
    exit 1
fi

echo ""
echo "🔄 Restoring database from backup..."
mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < "$BACKUP_FILE"

echo "✅ Database restored successfully!"
echo ""
echo "==========================================="
echo "📋 Post-Restore Steps (IMPORTANT!)"
echo "==========================================="
echo ""
echo "1️⃣  Update database schema to match current code:"
echo "   docker compose exec web php bin/console doctrine:schema:update --force"
echo ""
echo "2️⃣  Clear Symfony cache:"
echo "   docker compose exec web php bin/console cache:clear"
echo ""
echo "3️⃣  Fix file permissions (if needed):"
echo "   docker compose exec web chown -R www-data:www-data /var/www/html/var"
echo "   docker compose exec web chmod -R 775 /var/www/html/var"
echo ""
echo "4️⃣  Verify the application works:"
echo "   Open http://localhost:8000 in your browser"
echo ""
echo "💡 If you need to rollback:"
echo "   ./bin/restore_db.sh $SAFETY_BACKUP"
echo ""
