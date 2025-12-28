#!/bin/bash

###############################################################################
# homeadmin24 Production Droplet Deployment Script
#
# This script deploys or updates the homeadmin24 WEG Management System on the
# PRODUCTION DigitalOcean Droplet with Docker, Nginx reverse proxy, and SSL.
#
# Domain: prod.homeadmin24.de
# Type: Production (persistent data, no auto-reset)
#
# Requirements:
# - Setup script must have been run first (.droplet/setup-production.sh)
# - Domain name pointed to droplet IP
# - Git repository access
#
# Usage:
#   cd /opt/homeadmin24-prod
#   bash .droplet/deploy-production.sh ballauf35.homeadmin24.de info@homeadmin24.de [--quick]
#
# Options:
#   --quick    Quick deployment (skip Docker rebuild, ~2-3 min instead of 30 min)
#              Use for code-only changes. Omit for dependency/config updates.
###############################################################################

set -e  # Exit on error

# Check if running as root
if [ "$EUID" -ne 0 ]; then
   echo "Please run as root (use sudo)"
   exit 1
fi

# Parse arguments
QUICK_MODE=false
DOMAIN=""
EMAIL=""

for arg in "$@"; do
    if [ "$arg" = "--quick" ]; then
        QUICK_MODE=true
    elif [ -z "$DOMAIN" ]; then
        DOMAIN="$arg"
    elif [ -z "$EMAIL" ]; then
        EMAIL="$arg"
    fi
done

if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
    echo "Usage: $0 <domain> <email> [--quick]"
    echo "Example: $0 your-production-domain.com admin@example.com"
    echo "Example: $0 your-production-domain.com admin@example.com --quick"
    exit 1
fi

echo "=========================================="
echo "homeadmin24 PRODUCTION Deployment"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Type: PRODUCTION (persistent data)"
if [ "$QUICK_MODE" = true ]; then
    echo "Mode: ⚡ QUICK (skip Docker rebuild, ~2-3 min)"
else
    echo "Mode: 🔨 FULL (Docker rebuild + swap setup, ~30 min)"
fi
echo ""

# Get the script directory (should be /opt/homeadmin24-prod/.droplet)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"

# Change to app directory
cd "$APP_DIR"
echo "App directory: $APP_DIR"
echo ""

# Pull latest changes
echo "[1/12] Pulling latest code from GitHub..."
git fetch origin main
git reset --hard origin/main

# Check if .env exists
if [ ! -f .env ]; then
    echo "[2/12] Creating .env file..."
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "⚠️  IMPORTANT: Configure $APP_DIR/.env for PRODUCTION"
        echo "    - Set APP_ENV=prod"
        echo "    - Generate secure APP_SECRET"
        echo "    - Configure database credentials"
        echo ""
        echo "Press Enter to continue after editing .env..."
        read
    else
        echo "Error: .env.example not found"
        exit 1
    fi
else
    echo "[2/12] .env already exists"
fi

# Create production docker-compose override
echo "[3/12] Creating production docker-compose configuration..."
cat > docker-compose.prod.yml <<'DOCKER_COMPOSE'
services:
  web:
    environment:
      - APP_ENV=prod
    restart: unless-stopped

  mysql:
    restart: unless-stopped
    volumes:
      - mysql_data:/var/lib/mysql
      - ./backups:/backups

volumes:
  mysql_data:
    driver: local
DOCKER_COMPOSE

if [ "$QUICK_MODE" = true ]; then
    echo "[4/8] ⚡ Skipping Docker rebuild (quick mode)..."
    echo "       Containers will continue running with new code"

    echo "[5/8] Clearing Symfony cache..."
    docker-compose exec -T web php bin/console cache:clear

    echo "[6/8] Rebuilding frontend assets..."
    docker-compose exec -T web npm run build

    echo "[7/8] Running database migrations..."
    docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction

    echo "[8/8] Deployment complete (skipping Nginx/SSL config in quick mode)"
    echo ""
    echo "=========================================="
    echo "✅ Quick PRODUCTION Deployment complete!"
    echo "=========================================="
    echo ""
    echo "🔒 Application running at: https://$DOMAIN"
    echo "⚡ Deployment time: ~2-3 minutes"
    echo ""
    exit 0
else
    # Full deployment with Docker rebuild
    echo "[4/12] Setting up swap space (if needed)..."
    if ! swapon --show | grep -q '/swapfile'; then
        echo "Creating 2GB swap file..."
        fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048
        chmod 600 /swapfile
        mkswap /swapfile
        swapon /swapfile

        # Make swap permanent
        if ! grep -q '/swapfile' /etc/fstab; then
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi

        echo "✅ Swap enabled: $(free -h | grep Swap)"
    else
        echo "✅ Swap already configured: $(swapon --show | grep '/swapfile')"
    fi

    # Build and start Docker containers
    echo "[5/12] Building Docker containers..."
    docker-compose -f docker-compose.yaml -f docker-compose.prod.yml build --no-cache

    echo "[6/12] Starting Docker containers..."
    docker-compose -f docker-compose.yaml -f docker-compose.prod.yml down -v
    docker-compose -f docker-compose.yaml -f docker-compose.prod.yml up -d

    # Wait for database to be ready
    echo "[7/12] Waiting for database to be ready..."
    MAX_TRIES=30
    COUNTER=0
    until docker-compose exec -T mysql mysqladmin ping -h localhost --silent; do
        COUNTER=$((COUNTER+1))
        if [ $COUNTER -eq $MAX_TRIES ]; then
            echo "❌ Database failed to become ready after ${MAX_TRIES} attempts"
            exit 1
        fi
        echo "   Waiting for MySQL... (attempt $COUNTER/$MAX_TRIES)"
        sleep 2
    done
    echo "mysqld is alive"

    # Additional wait: Verify MySQL is accepting TCP connections from web container
    echo "Verifying MySQL connection from web container..."
    COUNTER=0
    until docker-compose exec -T web php -r "new PDO('mysql:host=mysql;dbname=homeadmin24', 'root', 'rootpassword');" 2>/dev/null; do
        COUNTER=$((COUNTER+1))
        if [ $COUNTER -eq $MAX_TRIES ]; then
            echo "❌ MySQL not accepting connections from web container after ${MAX_TRIES} attempts"
            exit 1
        fi
        echo "   Waiting for MySQL TCP connection... (attempt $COUNTER/$MAX_TRIES)"
        sleep 2
    done
    echo "✅ Database is ready!"

    # Run database migrations
    echo "[8/12] Running database migrations..."
    docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction
fi

# Load system configuration (only if empty database)
echo "[9/12] Checking database status..."
TABLE_COUNT=$(docker-compose exec -T mysql mysql -uroot -prootpassword homeadmin24 -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='homeadmin24'")

if [ "$TABLE_COUNT" -lt 5 ]; then
    echo "Loading system configuration..."
    docker-compose exec -T web php bin/console doctrine:fixtures:load --group=system-config --no-interaction
else
    echo "Database already populated, skipping fixtures"
fi

# Configure Nginx reverse proxy
echo "[10/12] Configuring Nginx..."
cat > /etc/nginx/sites-available/homeadmin24-production <<EOF
server {
    listen 80;
    server_name $DOMAIN;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Proxy to Docker container
    location / {
        proxy_pass http://localhost:8001;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # WebSocket support (for Turbo/Mercure if used)
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Max upload size (for PDF/CSV imports)
    client_max_body_size 20M;
}
EOF

# Enable site
if [ ! -L /etc/nginx/sites-enabled/homeadmin24-production ]; then
    ln -s /etc/nginx/sites-available/homeadmin24-production /etc/nginx/sites-enabled/
fi

# Remove default site if it exists
if [ -L /etc/nginx/sites-enabled/default ]; then
    rm /etc/nginx/sites-enabled/default
fi

# Test Nginx configuration
nginx -t

# Reload Nginx
systemctl reload nginx

# Setup SSL with Certbot
echo "[11/12] Setting up SSL certificate..."
# Check if HTTPS is configured in Nginx (not just if certificate exists)
if ! grep -q "listen 443 ssl" /etc/nginx/sites-available/homeadmin24-production 2>/dev/null; then
    echo "Configuring HTTPS with certbot..."
    certbot --nginx -d $DOMAIN --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already configured in Nginx"
    if [ -d /etc/letsencrypt/live/$DOMAIN ]; then
        certbot renew --dry-run
    fi
fi

echo "[12/12] Deployment summary..."
echo ""
echo "=========================================="
echo "✅ PRODUCTION Deployment complete!"
echo "=========================================="
echo ""
echo "🔒 Application running at: https://$DOMAIN"
echo ""
echo "Next steps:"
echo "1. Create admin user:"
echo "   docker-compose exec web php bin/console app:create-admin"
echo ""
echo "2. Test application access"
echo ""
echo "3. View logs:"
echo "   docker-compose logs -f web"
echo ""
echo "4. Create manual backup:"
echo "   /usr/local/bin/homeadmin24-backup.sh"
echo ""
echo "🔄 Automated backups: Daily at 3 AM → $APP_DIR/backups/"
echo "📜 SSL auto-renewal: Configured via certbot systemd timer"
echo ""
