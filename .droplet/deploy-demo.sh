#!/bin/bash

###############################################################################
# homeadmin24 Demo Droplet Deployment Script
#
# This script deploys or updates the homeadmin24 WEG Management System on the
# DEMO DigitalOcean Droplet with Docker, Nginx, SSL, and AUTO-RESET cron.
#
# Domain: demo.homeadmin24.de
# Type: Demo (auto-resets every 30 minutes to fresh demo data)
#
# Requirements:
# - Setup script must have been run first (.droplet/setup-demo.sh)
# - Domain name pointed to droplet IP
# - Git repository access
#
# Usage:
#   cd /opt/homeadmin24-demo
#   bash .droplet/deploy-demo.sh demo.homeadmin24.de your@email.com
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
    echo "Example: $0 demo.example.com admin@example.com"
    exit 1
fi

echo "=========================================="
echo "homeadmin24 DEMO Deployment"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Type: DEMO (auto-reset every 30 minutes)"
echo ""

# Ensure we're in the app directory
cd /opt/homeadmin24-demo

# Pull latest changes
echo "[1/11] Pulling latest code from GitHub..."
git pull origin main

# Check if .env exists
if [ ! -f .env ]; then
    echo "[2/11] Creating .env file..."
    if [ -f .env.example ]; then
        cp .env.example .env
        # Auto-configure for demo environment
        sed -i 's/APP_ENV=.*/APP_ENV=dev/' .env
        sed -i 's/APP_SECRET=.*/APP_SECRET=9d7239a8d9bbf2c779f561151fcb2e10/' .env
        sed -i 's|DATABASE_URL=.*|DATABASE_URL="mysql://root:rootpassword@mysql:3306/homeadmin24?serverVersion=8.0\&charset=utf8mb4\&collation=utf8mb4_unicode_ci"|' .env
        sed -i 's/# MAILER_DSN=.*/MAILER_DSN=null:\/\/null/' .env
        sed -i 's/^MAILER_DSN=null/MAILER_DSN=null:\/\/null/' .env 2>/dev/null || true
        echo "✅ .env created and auto-configured for DEMO (APP_ENV=dev, DATABASE_URL, APP_SECRET)"
    else
        echo "Error: .env.example not found"
        exit 1
    fi
else
    echo "[2/11] .env already exists"
fi

# Create demo docker-compose override
echo "[3/11] Creating demo docker-compose configuration..."
cat > docker-compose.demo.yml <<'DOCKER_COMPOSE'
services:
  web:
    container_name: homeadmin24-demo-web
    environment:
      - APP_ENV=dev
    restart: unless-stopped

  mysql:
    container_name: homeadmin24-demo-mysql
    restart: unless-stopped
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
    name: homeadmin24-demo_mysql_data
DOCKER_COMPOSE

# Build and start Docker containers
echo "[4/11] Building Docker containers..."
docker-compose -f docker-compose.yaml -f docker-compose.demo.yml build --no-cache

echo "[5/11] Starting Docker containers..."
docker-compose -f docker-compose.yaml -f docker-compose.demo.yml down
docker-compose -f docker-compose.yaml -f docker-compose.demo.yml up -d

# Wait for database to be ready
echo "[6/11] Waiting for database to be ready..."
sleep 20

# Run database migrations
echo "[7/11] Running database migrations..."
docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction

# Load demo data
echo "[8/11] Loading demo data..."
docker-compose exec -T web php bin/console doctrine:fixtures:load --group=system-config --no-interaction
docker-compose exec -T web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Configure Nginx reverse proxy with demo banner
echo "[9/11] Configuring Nginx..."
cat > /etc/nginx/sites-available/homeadmin24-demo <<EOF
server {
    listen 80;
    server_name $DOMAIN;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Demo indicator header
    add_header X-Demo-Mode "This is a demo system. Data resets every 30 minutes." always;

    # Proxy to Docker container
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # WebSocket support
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Max upload size
    client_max_body_size 20M;
}
EOF

# Enable site
if [ ! -L /etc/nginx/sites-enabled/homeadmin24-demo ]; then
    ln -s /etc/nginx/sites-available/homeadmin24-demo /etc/nginx/sites-enabled/
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
echo "[10/11] Setting up SSL certificate..."
if [ ! -d /etc/letsencrypt/live/$DOMAIN ]; then
    certbot --nginx -d $DOMAIN --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already exists for $DOMAIN"
    certbot renew --dry-run
fi

# Setup auto-reset cron job
echo "[11/11] Configuring auto-reset cron job..."
# Remove existing hausman-demo-reset jobs
crontab -l 2>/dev/null | grep -v hausman-demo-reset | crontab - 2>/dev/null || true

# Add new cron job (every 30 minutes: at :00 and :30 of each hour)
(crontab -l 2>/dev/null; echo "0,30 * * * * /usr/local/bin/homeadmin24-demo-reset.sh") | crontab -

echo "✅ Auto-reset configured: Every 30 minutes (on the hour and half-hour)"

echo ""
echo "=========================================="
echo "✅ DEMO Deployment complete!"
echo "=========================================="
echo ""
echo "🔒 Demo system running at: https://$DOMAIN"
echo ""
echo "📋 Demo System Info:"
echo "   • Auto-resets: Every 30 minutes (:00 and :30)"
echo "   • Next reset: $(date -d '30 min' '+%H:%M' 2>/dev/null || date -v+30M '+%H:%M')"
echo "   • Login: admin@hausman.local / admin123"
echo "   • Demo data: 3 fictional WEG properties (Musterhausen, Berlin, Hamburg)"
echo ""
echo "🔧 Management commands:"
echo "   • Manual reset: /usr/local/bin/homeadmin24-demo-reset.sh"
echo "   • View reset logs: tail -f /var/log/homeadmin24-demo-reset.log"
echo "   • View app logs: cd /opt/homeadmin24-demo && docker-compose logs -f web"
echo "   • Check cron: crontab -l | grep homeadmin24"
echo ""
echo "⏰ Auto-reset schedule: 00:00, 00:30, 01:00, 01:30, ... (48 times/day)"
echo "📜 SSL auto-renewal: Configured via certbot systemd timer"
echo ""
