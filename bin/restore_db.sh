#!/bin/bash

# homeadmin24 Database Restore Script
# Usage: ./bin/restore_db.sh <backup_file>
# Example: ./bin/restore_db.sh backup/backup_20251228_manual.sql

set -e

if [ -z "$1" ]; then
    echo "❌ Error: No backup file specified"
    echo "Usage: $0 <backup_file>"
    echo ""
    echo "Available backups:"
    ls -lah backup/*.sql 2>/dev/null | tail -10 || echo "  (No backups found)"
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

echo "==========================================="
echo "🔄 Database Restore"
echo "==========================================="
echo "📁 File: $(basename "$BACKUP_FILE")"
echo ""
echo "⚠️  WARNING: This will REPLACE all data!"
echo ""
read -p "Type 'yes' to continue: " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Restore cancelled"
    exit 0
fi

echo "🔄 Restoring database..."
docker compose exec -T mysql mysql -uroot -prootpassword homeadmin24 < "$BACKUP_FILE" 2>&1 | grep -v "Warning: Using a password"

echo ""
echo "✅ Database restored successfully!"
echo ""
echo "📋 IMPORTANT: Run these commands:"
echo "   docker compose exec web php bin/console doctrine:schema:update --force"
echo "   docker compose exec web php bin/console cache:clear"
echo ""
