#!/bin/bash

###############################################################################
# homeadmin24 Demo Droplet Setup Script
#
# This script sets up the DEMO DigitalOcean Droplet for running homeadmin24
# WEG Management System with Docker, Nginx, SSL, and AUTO-RESET functionality.
#
# Domain: demo.homeadmin24.de
# Type: Demo (auto-resets every 30 minutes to fresh demo data)
#
# Requirements:
# - Ubuntu 22.04 or 24.04 LTS
# - Root access
# - Domain name pointed to droplet IP (for SSL)
#
# Usage:
#   wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
#   chmod +x setup-demo.sh
#   sudo ./setup-demo.sh demo.homeadmin24.de your@email.com
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
echo "homeadmin24 DEMO Droplet Setup"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Type: DEMO (auto-reset every 30 minutes)"
echo ""

# Update system
echo "[1/9] Updating system packages..."
apt-get update -qq
apt-get upgrade -y -qq

# Install dependencies
echo "[2/9] Installing Docker and dependencies..."
apt-get install -y -qq \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    ufw \
    mysql-client

# Install Docker
if ! command -v docker &> /dev/null; then
    echo "[3/9] Installing Docker..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
else
    echo "[3/9] Docker already installed"
fi

# Install Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo "[4/9] Installing Docker Compose..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
else
    echo "[4/9] Docker Compose already installed"
fi

# Install Nginx
echo "[5/9] Installing Nginx..."
apt-get install -y -qq nginx

# Install Certbot for SSL
echo "[6/9] Installing Certbot..."
apt-get install -y -qq certbot python3-certbot-nginx

# Configure firewall
echo "[7/9] Configuring firewall..."
ufw --force enable
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw status

# Create application directory
echo "[8/9] Setting up application directory..."
mkdir -p /opt/homeadmin24-demo
cd /opt/homeadmin24-demo

# Create demo reset script
echo "[9/9] Setting up auto-reset functionality..."
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
echo "✅ Database is ready!" >> $LOG_FILE
sleep 2  # Extra buffer for safety

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

# Add cron job (every 30 minutes: 0,30 * * * *)
# Note: Cron job will be added after first deployment
echo "Auto-reset script created: /usr/local/bin/homeadmin24-demo-reset.sh"
echo "Cron job will be configured during deployment"

echo ""
echo "=========================================="
echo "✅ Demo base setup complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Clone your repository:"
echo "   cd /opt/homeadmin24-demo"
echo "   git clone https://github.com/homeadmin24/homeadmin24.git ."
echo ""
echo "2. Run deployment script (auto-configures .env for demo):"
echo "   bash .droplet/deploy-demo.sh $DOMAIN $EMAIL"
echo ""
echo "⏰ Auto-reset: Database will reset every 30 minutes"
echo "📝 Reset logs: /var/log/homeadmin24-demo-reset.log"
echo ""
