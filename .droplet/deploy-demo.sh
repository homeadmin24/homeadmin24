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
#   bash .droplet/deploy-demo.sh demo.homeadmin24.de your@email.com [--quick]
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
    echo "Example: $0 demo.example.com admin@example.com"
    echo "Example: $0 demo.example.com admin@example.com --quick"
    exit 1
fi

echo "=========================================="
echo "homeadmin24 DEMO Deployment"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Type: DEMO (auto-reset every 30 minutes)"
if [ "$QUICK_MODE" = true ]; then
    echo "Mode: ⚡ QUICK (skip Docker rebuild, ~2-3 min)"
else
    echo "Mode: 🔨 FULL (Docker rebuild + swap setup, ~30 min)"
fi
echo ""

# Ensure we're in the app directory
cd /opt/homeadmin24-demo

# Pull latest changes
echo "[1/11] Pulling latest code from GitHub..."
git pull origin main

# Configure .env file
echo "[2/11] Configuring .env file..."
if [ ! -f .env.example ]; then
    echo "❌ Error: .env.example not found"
    exit 1
fi

if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
    ENV_ACTION="created"
else
    echo ".env already exists, checking configuration..."
    # Check if DATABASE_URL uses 127.0.0.1 (wrong for Docker) or required env vars are missing
    if grep -q "127.0.0.1" .env; then
        echo "⚠️  WARNING: .env uses 127.0.0.1 (local) instead of 'mysql' (Docker)"
        echo "Updating .env for Docker deployment..."
        ENV_ACTION="updated"
    elif ! grep -q "MESSENGER_TRANSPORT_DSN" .env || grep -q "^# MESSENGER_TRANSPORT_DSN" .env; then
        echo "⚠️  WARNING: .env missing MESSENGER_TRANSPORT_DSN"
        echo "Updating .env for Docker deployment..."
        ENV_ACTION="updated"
    elif ! grep -q "TRUSTED_PROXIES" .env || grep -q "^# TRUSTED_PROXIES" .env; then
        echo "⚠️  WARNING: .env missing TRUSTED_PROXIES (needed for Nginx proxy)"
        echo "Updating .env for Docker deployment..."
        ENV_ACTION="updated"
    else
        echo "✅ .env looks good for Docker deployment"
        ENV_ACTION="validated"
    fi
fi

if [ "$ENV_ACTION" != "validated" ]; then
    # Auto-configure for demo environment
    sed -i 's/APP_ENV=.*/APP_ENV=dev/' .env
    sed -i 's/APP_SECRET=.*/APP_SECRET=9d7239a8d9bbf2c779f561151fcb2e10/' .env
    sed -i 's|DATABASE_URL=.*|DATABASE_URL="mysql://root:rootpassword@mysql:3306/homeadmin24?serverVersion=8.0\&charset=utf8mb4\&collation=utf8mb4_unicode_ci"|' .env
    sed -i 's/# MAILER_DSN=.*/MAILER_DSN=null:\/\/null/' .env
    sed -i 's/^MAILER_DSN=.*/MAILER_DSN=null:\/\/null/' .env 2>/dev/null || true

    # Add MESSENGER_TRANSPORT_DSN if missing
    if ! grep -q "MESSENGER_TRANSPORT_DSN" .env; then
        echo "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0" >> .env
    else
        sed -i 's/# MESSENGER_TRANSPORT_DSN=.*/MESSENGER_TRANSPORT_DSN=doctrine:\/\/default?auto_setup=0/' .env
        sed -i 's/^MESSENGER_TRANSPORT_DSN=.*/MESSENGER_TRANSPORT_DSN=doctrine:\/\/default?auto_setup=0/' .env 2>/dev/null || true
    fi

    # Add TRUSTED_PROXIES and TRUSTED_HOSTS for Nginx proxy
    if ! grep -q "TRUSTED_PROXIES" .env; then
        echo "TRUSTED_PROXIES=127.0.0.1" >> .env
    else
        sed -i 's/^# TRUSTED_PROXIES=.*/TRUSTED_PROXIES=127.0.0.1/' .env
        sed -i 's/^TRUSTED_PROXIES=.*/TRUSTED_PROXIES=127.0.0.1/' .env 2>/dev/null || true
    fi

    if ! grep -q "TRUSTED_HOSTS" .env; then
        echo "TRUSTED_HOSTS=^demo\\.homeadmin24\\.de$" >> .env
    else
        sed -i 's/^# TRUSTED_HOSTS=.*/TRUSTED_HOSTS=^demo\\.homeadmin24\\.de$/' .env
        sed -i 's/^TRUSTED_HOSTS=.*/TRUSTED_HOSTS=^demo\\.homeadmin24\\.de$/' .env 2>/dev/null || true
    fi

    echo "✅ .env ${ENV_ACTION} and configured for DEMO:"
    echo "   • APP_ENV=dev (development mode)"
    echo "   • DATABASE_URL=mysql://root:***@mysql:3306/homeadmin24 (Docker)"
    echo "   • APP_SECRET=*** (demo secret)"
    echo "   • MESSENGER_TRANSPORT_DSN=doctrine://default (demo queue)"
    echo "   • TRUSTED_PROXIES=127.0.0.1 (Nginx proxy support)"
    echo "   • TRUSTED_HOSTS=^demo.homeadmin24.de$ (domain restriction)"
fi

# Create demo docker-compose override
echo "[3/11] Creating demo docker-compose configuration..."
cat > docker-compose.demo.yml <<'DOCKER_COMPOSE'
services:
  web:
    container_name: homeadmin24-demo-web
    environment:
      - APP_ENV=dev
      - TRUSTED_PROXIES=127.0.0.1
      - TRUSTED_HOSTS=^demo\.homeadmin24\.de$
      - MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
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

if [ "$QUICK_MODE" = true ]; then
    echo "[4/8] ⚡ Skipping Docker rebuild (quick mode)..."
    echo "       Containers will continue running with new code"

    echo "[5/8] Clearing Symfony cache..."
    docker-compose exec -T web php bin/console cache:clear

    echo "[5.5/8] Restarting web container to clear PHP OPcache..."
    docker-compose restart web
    sleep 3  # Wait for container to be ready

    echo "[6/8] Rebuilding frontend assets..."
    docker-compose exec -T web npm run build

    echo "[7/8] Running database migrations..."
    docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction

    echo "[8/8] Reloading demo data..."
    docker-compose exec -T web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
else
    # Full deployment with Docker rebuild
    echo "[4/13] Setting up swap space (if needed)..."
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

    echo "[5/13] Building Docker containers..."
    docker-compose -f docker-compose.yaml -f docker-compose.demo.yml build --no-cache

    echo "[6/13] Starting Docker containers..."
    docker-compose -f docker-compose.yaml -f docker-compose.demo.yml down -v
    docker-compose -f docker-compose.yaml -f docker-compose.demo.yml up -d

    # Wait for database to be ready
    echo "[7/13] Waiting for database to be ready..."
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
    echo "[8/13] Running database migrations..."
    docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction

    # Load demo data
    echo "[9/13] Loading demo data..."
    docker-compose exec -T web php bin/console doctrine:fixtures:load --group=system-config --no-interaction
    docker-compose exec -T web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
fi

# Configure Nginx reverse proxy with demo banner (skip in quick mode if already configured)
if [ "$QUICK_MODE" = true ] && [ -f /etc/nginx/sites-available/homeadmin24-demo ]; then
    echo "⚡ Skipping Nginx/SSL configuration (quick mode, already configured)"
    echo ""
    echo "=========================================="
    echo "✅ Quick DEMO Deployment complete!"
    echo "=========================================="
    echo ""
    echo "🔒 Demo system running at: https://$DOMAIN"
    echo "⚡ Deployment time: ~2-3 minutes"
    echo ""
    exit 0
fi

echo "[10/13] Configuring Nginx..."
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
echo "[11/13] Setting up SSL certificate..."
# Check if HTTPS is configured in Nginx (not just if certificate exists)
if ! grep -q "listen 443 ssl" /etc/nginx/sites-available/homeadmin24-demo 2>/dev/null; then
    echo "Configuring HTTPS with certbot..."
    certbot --nginx -d $DOMAIN --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already configured in Nginx"
    if [ -d /etc/letsencrypt/live/$DOMAIN ]; then
        certbot renew --dry-run
    fi
fi

# Update auto-reset script with latest version
echo "[12/13] Updating auto-reset script..."
cat > /usr/local/bin/homeadmin24-demo-reset.sh <<'RESET_SCRIPT'
#!/bin/bash
###############################################################################
# homeadmin24 Demo Auto-Reset Script
#
# This script resets the demo database to fresh demo data.
# Runs every 30 minutes via cron.
###############################################################################

LOG_FILE="/var/log/homeadmin24-demo-reset.log"
APP_DIR="/opt/homeadmin24-demo"

echo "========================================" >> $LOG_FILE
echo "Demo Reset: $(date)" >> $LOG_FILE
echo "========================================" >> $LOG_FILE

cd $APP_DIR

# Stop containers
echo "Stopping containers..." >> $LOG_FILE
docker-compose -f docker-compose.yaml -f docker-compose.demo.yml down >> $LOG_FILE 2>&1

# Remove database volume (forces fresh start)
echo "Removing database volume..." >> $LOG_FILE
docker volume rm homeadmin24-demo_mysql_data 2>/dev/null || true

# Start containers
echo "Starting containers..." >> $LOG_FILE
docker-compose -f docker-compose.yaml -f docker-compose.demo.yml up -d >> $LOG_FILE 2>&1

# Wait for database to be ready with connectivity check
echo "Waiting for database..." >> $LOG_FILE
MAX_TRIES=30
COUNTER=0
until docker-compose exec -T mysql mysqladmin ping -h localhost --silent >> $LOG_FILE 2>&1; do
    COUNTER=$((COUNTER+1))
    if [ $COUNTER -eq $MAX_TRIES ]; then
        echo "❌ Database failed to become ready after ${MAX_TRIES} attempts" >> $LOG_FILE
        exit 1
    fi
    echo "   Waiting for MySQL... (attempt $COUNTER/$MAX_TRIES)" >> $LOG_FILE
    sleep 2
done
echo "mysqld is alive" >> $LOG_FILE

# Additional wait: Verify MySQL is accepting TCP connections from web container
echo "Verifying MySQL connection from web container..." >> $LOG_FILE
COUNTER=0
until docker-compose exec -T web php -r "new PDO('mysql:host=mysql;dbname=homeadmin24', 'root', 'rootpassword');" >> $LOG_FILE 2>&1; do
    COUNTER=$((COUNTER+1))
    if [ $COUNTER -eq $MAX_TRIES ]; then
        echo "❌ MySQL not accepting connections from web container after ${MAX_TRIES} attempts" >> $LOG_FILE
        exit 1
    fi
    echo "   Waiting for MySQL TCP connection... (attempt $COUNTER/$MAX_TRIES)" >> $LOG_FILE
    sleep 2
done
echo "✅ Database is ready!" >> $LOG_FILE

# Run migrations
echo "Running migrations..." >> $LOG_FILE
docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction >> $LOG_FILE 2>&1

# Load demo fixtures
echo "Loading demo data..." >> $LOG_FILE
docker-compose exec -T web php bin/console doctrine:fixtures:load --group=system-config --no-interaction >> $LOG_FILE 2>&1
docker-compose exec -T web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction >> $LOG_FILE 2>&1

# Clear cache
echo "Clearing cache..." >> $LOG_FILE
docker-compose exec -T web php bin/console cache:clear >> $LOG_FILE 2>&1

echo "✅ Demo reset complete at $(date)" >> $LOG_FILE
echo "" >> $LOG_FILE

# Keep log file under 10MB
LOG_SIZE=$(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE" 2>/dev/null || echo 0)
if [ "$LOG_SIZE" -gt 10485760 ]; then
    tail -n 1000 "$LOG_FILE" > "${LOG_FILE}.tmp"
    mv "${LOG_FILE}.tmp" "$LOG_FILE"
fi
RESET_SCRIPT

chmod +x /usr/local/bin/homeadmin24-demo-reset.sh
echo "✅ Auto-reset script updated"

# Setup auto-reset cron job
echo "[13/13] Configuring auto-reset cron job..."
# Remove existing homeadmin24-demo-reset jobs (and legacy hausman-demo-reset)
crontab -l 2>/dev/null | grep -v homeadmin24-demo-reset | grep -v hausman-demo-reset | crontab - 2>/dev/null || true

# Add new cron job (every 30 minutes: at :00 and :30 of each hour)
(crontab -l 2>/dev/null; echo "0,30 * * * * /usr/local/bin/homeadmin24-demo-reset.sh") | crontab -

echo "✅ Auto-reset configured: Every 30 minutes (on the hour and half-hour)"

# Display final system info
echo "[14/14] Deployment summary..."
echo ""
echo "=========================================="
echo "✅ DEMO Deployment complete!"
echo "=========================================="
echo ""
echo "🔒 Demo system running at: https://$DOMAIN"
echo ""
echo "📋 Demo System Info:"
echo "   • Auto-resets: Every 30 minutes (:00 and :30)"
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
