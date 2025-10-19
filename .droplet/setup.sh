#!/bin/bash

###############################################################################
# homeadmin24 Droplet Setup Script
#
# This script sets up a DigitalOcean Droplet for running homeadmin24 WEG Management
# System with Docker, Nginx, and SSL certificates.
#
# Requirements:
# - Ubuntu 22.04 or 24.04 LTS
# - Root access
# - Domain name pointed to droplet IP (for SSL)
#
# Usage:
#   wget https://raw.githubusercontent.com/homeadmin24/hausman/main/.droplet/setup.sh
#   chmod +x setup.sh
#   sudo ./setup.sh yourdomain.com your@email.com
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
echo "homeadmin24 Droplet Setup"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo ""

# Update system
echo "[1/8] Updating system packages..."
apt-get update -qq
apt-get upgrade -y -qq

# Install dependencies
echo "[2/8] Installing Docker and dependencies..."
apt-get install -y -qq \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    ufw

# Install Docker
if ! command -v docker &> /dev/null; then
    echo "[3/8] Installing Docker..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
else
    echo "[3/8] Docker already installed"
fi

# Install Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo "[4/8] Installing Docker Compose..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
else
    echo "[4/8] Docker Compose already installed"
fi

# Install Nginx
echo "[5/8] Installing Nginx..."
apt-get install -y -qq nginx

# Install Certbot for SSL
echo "[6/8] Installing Certbot..."
apt-get install -y -qq certbot python3-certbot-nginx

# Configure firewall
echo "[7/8] Configuring firewall..."
ufw --force enable
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw status

# Create application directory
echo "[8/8] Setting up application directory..."
mkdir -p /opt/homeadmin24
cd /opt/homeadmin24

# Clone repository (will be done later via deploy script)
echo ""
echo "=========================================="
echo "✅ Base setup complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Clone your repository:"
echo "   cd /opt/homeadmin24"
echo "   git clone https://github.com/homeadmin24/hausman.git ."
echo ""
echo "2. Copy .env.example to .env and configure"
echo ""
echo "3. Run deployment script:"
echo "   bash .droplet/deploy.sh $DOMAIN $EMAIL"
echo ""
