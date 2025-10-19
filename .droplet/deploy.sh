#!/bin/bash

###############################################################################
# homeadmin24 Droplet Deployment Script
#
# This script deploys or updates the homeadmin24 WEG Management System on a
# DigitalOcean Droplet with Docker, Nginx reverse proxy, and SSL.
#
# Requirements:
# - Setup script must have been run first (.droplet/setup.sh)
# - Domain name pointed to droplet IP
# - Git repository access
#
# Usage:
#   cd /opt/homeadmin24
#   bash .droplet/deploy.sh yourdomain.com your@email.com
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
    echo "Example: $0 homeadmin24.example.com admin@example.com"
    exit 1
fi

echo "=========================================="
echo "homeadmin24 Deployment"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo ""

# Ensure we're in the app directory
cd /opt/homeadmin24

# Pull latest changes
echo "[1/9] Pulling latest code from GitHub..."
git pull origin main

# Check if .env exists
if [ ! -f .env ]; then
    echo "[2/9] Creating .env file..."
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "⚠️  Please edit /opt/homeadmin24/.env with your configuration"
        echo "Press Enter to continue after editing .env..."
        read
    else
        echo "Error: .env.example not found"
        exit 1
    fi
else
    echo "[2/9] .env already exists"
fi

# Build and start Docker containers
echo "[3/9] Building Docker containers..."
docker-compose build --no-cache

echo "[4/9] Starting Docker containers..."
docker-compose down
docker-compose up -d

# Wait for database to be ready
echo "[5/9] Waiting for database to be ready..."
sleep 10

# Run database migrations
echo "[6/9] Running database migrations..."
docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction

# Load system configuration
echo "[7/9] Loading system configuration..."
docker-compose exec -T web php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Configure Nginx reverse proxy
echo "[8/9] Configuring Nginx..."
cat > /etc/nginx/sites-available/homeadmin24 <<EOF
server {
    listen 80;
    server_name $DOMAIN;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

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
if [ ! -L /etc/nginx/sites-enabled/homeadmin24 ]; then
    ln -s /etc/nginx/sites-available/homeadmin24 /etc/nginx/sites-enabled/
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
echo "[9/9] Setting up SSL certificate..."
if [ ! -d /etc/letsencrypt/live/$DOMAIN ]; then
    certbot --nginx -d $DOMAIN --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already exists for $DOMAIN"
    certbot renew --dry-run
fi

echo ""
echo "=========================================="
echo "✅ Deployment complete!"
echo "=========================================="
echo ""
echo "Your application is now running at:"
echo "🔒 https://$DOMAIN"
echo ""
echo "Next steps:"
echo "1. Create admin user:"
echo "   docker-compose exec web php bin/console app:create-admin"
echo ""
echo "2. Load demo data (optional):"
echo "   docker-compose exec web php bin/console doctrine:fixtures:load --group=demo-data"
echo ""
echo "3. View logs:"
echo "   docker-compose logs -f web"
echo ""
echo "SSL certificate auto-renewal is configured via certbot systemd timer"
echo ""
