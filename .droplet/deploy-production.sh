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
#   cd /opt/homeadmin24
#   bash .droplet/deploy-production.sh prod.homeadmin24.de your@email.com
###############################################################################

set -e  # Exit on error

# Check if running as root
if [ "$EUID" -ne 0 ]; then
   echo "Please run as root (use sudo)"
   exit 1
fi

# Check arguments
DOMAIN=${1:-}
EMAIL=${2:-}

if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
    echo "Usage: $0 <domain> <email>"
    echo "Example: $0 your-production-domain.com admin@example.com"
    exit 1
fi

echo "=========================================="
echo "homeadmin24 PRODUCTION Deployment"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Type: PRODUCTION (persistent data)"
echo ""

# Ensure we're in the app directory
cd /opt/homeadmin24

# Pull latest changes
echo "[1/10] Pulling latest code from GitHub..."
git pull origin main

# Check if .env exists
if [ ! -f .env ]; then
    echo "[2/10] Creating .env file..."
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "⚠️  IMPORTANT: Configure /opt/homeadmin24/.env for PRODUCTION"
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
    echo "[2/10] .env already exists"
fi

# Create production docker-compose override
echo "[3/10] Creating production docker-compose configuration..."
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

# Build and start Docker containers
echo "[4/10] Building Docker containers..."
docker-compose -f docker-compose.yaml -f docker-compose.prod.yml build --no-cache

echo "[5/10] Starting Docker containers..."
docker-compose -f docker-compose.yaml -f docker-compose.prod.yml down
docker-compose -f docker-compose.yaml -f docker-compose.prod.yml up -d

# Wait for database to be ready
echo "[6/10] Waiting for database to be ready..."
sleep 15

# Run database migrations
echo "[7/10] Running database migrations..."
docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction

# Load system configuration (only if empty database)
echo "[8/10] Checking database status..."
TABLE_COUNT=$(docker-compose exec -T mysql mysql -uroot -prootpassword homeadmin24 -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='homeadmin24'")

if [ "$TABLE_COUNT" -lt 5 ]; then
    echo "Loading system configuration..."
    docker-compose exec -T web php bin/console doctrine:fixtures:load --group=system-config --no-interaction
else
    echo "Database already populated, skipping fixtures"
fi

# Configure Nginx reverse proxy
echo "[9/10] Configuring Nginx..."
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
        proxy_pass http://localhost:8000;
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
echo "[10/10] Setting up SSL certificate..."
if [ ! -d /etc/letsencrypt/live/$DOMAIN ]; then
    certbot --nginx -d $DOMAIN --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already exists for $DOMAIN"
    certbot renew --dry-run
fi

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
echo "🔄 Automated backups: Daily at 3 AM → /opt/homeadmin24/backups/"
echo "📜 SSL auto-renewal: Configured via certbot systemd timer"
echo ""
