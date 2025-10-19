#!/bin/bash

###############################################################################
# homeadmin24 Production Droplet Setup Script
#
# This script sets up the PRODUCTION DigitalOcean Droplet for running homeadmin24
# WEG Management System with Docker, Nginx, and SSL certificates.
#
# Domain: prod.homeadmin24.de
# Type: Production (persistent data, no auto-reset)
#
# Requirements:
# - Ubuntu 22.04 or 24.04 LTS
# - Root access
# - Domain name pointed to droplet IP (for SSL)
#
# Usage:
#   wget https://raw.githubusercontent.com/homeadmin24/hausman/main/.droplet/setup-production.sh
#   chmod +x setup-production.sh
#   sudo ./setup-production.sh prod.homeadmin24.de your@email.com
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
echo "homeadmin24 PRODUCTION Droplet Setup"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Type: PRODUCTION (persistent data)"
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
mkdir -p /opt/homeadmin24
cd /opt/homeadmin24

# Create backup directory
mkdir -p /opt/homeadmin24/backups

# Setup daily backup cron job
echo "[9/9] Setting up daily backup cron job..."
cat > /usr/local/bin/homeadmin24-backup.sh <<'BACKUP_SCRIPT'
#!/bin/bash
# Daily backup script for homeadmin24 production database
BACKUP_DIR="/opt/homeadmin24/backups"
DATE=$(date +%Y%m%d_%H%M%S)
CONTAINER_NAME="homeadmin24-mysql-1"

# Keep only last 30 days of backups
find $BACKUP_DIR -name "homeadmin24_prod_*.sql.gz" -mtime +30 -delete

# Create backup
docker exec $CONTAINER_NAME mysqldump -uroot -prootpassword homeadmin24 | gzip > "$BACKUP_DIR/homeadmin24_prod_$DATE.sql.gz"

echo "Backup created: homeadmin24_prod_$DATE.sql.gz"
BACKUP_SCRIPT

chmod +x /usr/local/bin/homeadmin24-backup.sh

# Add cron job (daily at 3 AM)
(crontab -l 2>/dev/null; echo "0 3 * * * /usr/local/bin/homeadmin24-backup.sh >> /var/log/homeadmin24-backup.log 2>&1") | crontab -

echo ""
echo "=========================================="
echo "✅ Production base setup complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Clone your repository:"
echo "   cd /opt/homeadmin24"
echo "   git clone https://github.com/homeadmin24/homeadmin24.git ."
echo ""
echo "2. Copy .env.example to .env and configure for PRODUCTION"
echo "   Important: Set APP_ENV=prod and secure APP_SECRET"
echo ""
echo "3. Run deployment script:"
echo "   bash .droplet/deploy-production.sh $DOMAIN $EMAIL"
echo ""
echo "Daily backups scheduled at 3 AM → /opt/homeadmin24/backups/"
echo ""
