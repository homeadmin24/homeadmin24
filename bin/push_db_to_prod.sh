#!/bin/bash
###############################################################################
# Push local dev DB to production server
#
# Usage:
#   ./bin/push_db_to_prod.sh <prod-server-ip>
#
# Dumps local Docker MySQL (excluding user table), SCPs to prod, imports
# via SSH. Production users are preserved — only financial/config data
# is overwritten.
#
# Run this BEFORE triggering the GitHub Actions deploy workflow.
#
# WARNING: This REPLACES the production database (except users). Use with care.
###############################################################################

set -e

PROD_HOST="${1:-}"

if [ -z "$PROD_HOST" ]; then
    echo "Usage: $0 <prod-server-ip>"
    echo "Example: $0 123.456.789.0"
    exit 1
fi

DUMP_FILE="/tmp/homeadmin24_db_push.sql"

echo "=========================================="
echo "⚠️  Push local DB → PRODUCTION"
echo "=========================================="
echo "Server: $PROD_HOST"
echo "Note:   Production 'user' table is excluded from dump — login credentials preserved"
echo ""
read -p "Are you sure? This replaces prod DB (except users). (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

echo ""
echo "[1/3] Dumping local Docker MySQL DB (excluding user table)..."
docker compose exec -T mysql mysqldump \
    --set-gtid-purged=OFF \
    --single-transaction \
    --ignore-table=homeadmin24.user \
    -u root -prootpassword homeadmin24 > "$DUMP_FILE"
echo "      Done: $(wc -c < "$DUMP_FILE" | xargs) bytes"

echo "[2/3] Copying dump to production server..."
scp -o StrictHostKeyChecking=no "$DUMP_FILE" "root@${PROD_HOST}:/tmp/homeadmin24_db_push.sql"

echo "[3/3] Importing DB on production..."
ssh -o StrictHostKeyChecking=no "root@${PROD_HOST}" "
    cd /opt/homeadmin24-prod
    docker compose exec -T mysql \
        mysql -u root -p\$(grep MYSQL_ROOT_PASSWORD docker-compose.yaml | head -1 | awk -F': ' '{print \$2}') \
        homeadmin24 < /tmp/homeadmin24_db_push.sql
    rm /tmp/homeadmin24_db_push.sql
    echo 'Import complete. Production users preserved.'
"

echo ""
echo "✅ DB pushed to production. Now trigger the deploy workflow."
