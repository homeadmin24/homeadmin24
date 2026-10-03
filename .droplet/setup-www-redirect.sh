#!/bin/bash

###############################################################################
# homeadmin24 WWW Redirect Setup Script
#
# This script sets up Nginx to handle redirects:
# - homeadmin24.de → https://www.homeadmin24.de
# - www.homeadmin24.de → Landing page or redirect to production
#
# Can be run on any droplet or separate server
#
# Usage:
#   bash .droplet/setup-www-redirect.sh your@email.com
###############################################################################

set -e  # Exit on error

# Check if running as root
if [ "$EUID" -ne 0 ]; then
   echo "Please run as root (use sudo)"
   exit 1
fi

EMAIL=${1:-}

if [ -z "$EMAIL" ]; then
    echo "Usage: $0 <email>"
    echo "Example: $0 admin@homeadmin24.de"
    exit 1
fi

DOMAIN_ROOT=${2:-}
DOMAIN_WWW=${3:-}
PRODUCTION_DOMAIN=${4:-}
DEMO_DOMAIN=${5:-}

if [ -z "$DOMAIN_ROOT" ] || [ -z "$DOMAIN_WWW" ]; then
    echo "Usage: $0 <email> <root-domain> <www-domain> [production-domain] [demo-domain]"
    echo "Example: $0 admin@example.com example.com www.example.com prod.example.com demo.example.com"
    exit 1
fi

echo "=========================================="
echo "homeadmin24 WWW Redirect Setup"
echo "=========================================="
echo "Email: $EMAIL"
echo "Root domain: $DOMAIN_ROOT"
echo "WWW domain: $DOMAIN_WWW"
echo ""
echo "This will configure:"
echo "  • $DOMAIN_ROOT → https://$DOMAIN_WWW"
echo "  • $DOMAIN_WWW → Landing page"
echo ""

# Install Nginx if not present
if ! command -v nginx &> /dev/null; then
    echo "[1/4] Installing Nginx..."
    apt-get update -qq
    apt-get install -y -qq nginx
else
    echo "[1/4] Nginx already installed"
fi

# Install Certbot if not present
if ! command -v certbot &> /dev/null; then
    echo "[2/4] Installing Certbot..."
    apt-get install -y -qq certbot python3-certbot-nginx
else
    echo "[2/4] Certbot already installed"
fi

# Configure Nginx for redirect
echo "[3/4] Configuring Nginx redirects..."

# Main redirect configuration
cat > /etc/nginx/sites-available/homeadmin24-redirect <<EOF
# Redirect non-www to www
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN_ROOT;

    # Let's Encrypt verification
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Redirect all other traffic to www
    location / {
        return 301 https://$DOMAIN_WWW\$request_uri;
    }
}

# WWW landing page (or redirect to production)
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN_WWW;

    # Let's Encrypt verification
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Option 1: Serve landing page
    root /var/www/homeadmin24-landing;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    # Option 2: Redirect to production (uncomment to use)
    # location / {
    #     return 301 https://YOUR_PRODUCTION_DOMAIN\$request_uri;
    # }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
EOF

# Create landing page directory
LANDING_DIR="/var/www/homeadmin24-landing"
mkdir -p $LANDING_DIR

# Use provided domains or placeholders
PROD_LINK=${PRODUCTION_DOMAIN:-"your-production-domain.com"}
DEMO_LINK=${DEMO_DOMAIN:-"your-demo-domain.com"}

# Create simple landing page
cat > $LANDING_DIR/index.html <<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>homeadmin24 - WEG-Verwaltungssystem</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            padding: 60px 40px;
            text-align: center;
        }
        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .links {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #48bb78;
            color: white;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 homeadmin24</h1>
        <p>Modernes Open-Source WEG-Verwaltungssystem für Wohnungseigentümergemeinschaften</p>

        <div class="links">
            <a href="https://$PROD_LINK" class="btn btn-primary">
                🔐 Zur Anwendung
            </a>
            <a href="https://$DEMO_LINK" class="btn btn-secondary">
                🎮 Demo testen
            </a>
        </div>

        <div class="footer">
            <p>Open Source • Made in Germany • <a href="https://github.com/homeadmin24/homeadmin24" style="color: #667eea;">GitHub</a></p>
        </div>
    </div>
</body>
</html>
HTML

# Enable site
if [ ! -L /etc/nginx/sites-enabled/homeadmin24-redirect ]; then
    ln -s /etc/nginx/sites-available/homeadmin24-redirect /etc/nginx/sites-enabled/
fi

# Remove default site if it exists
if [ -L /etc/nginx/sites-enabled/default ]; then
    rm /etc/nginx/sites-enabled/default
fi

# Test Nginx configuration
nginx -t

# Reload Nginx
systemctl reload nginx

# Setup SSL certificates
echo "[4/4] Setting up SSL certificates..."

# Certificate for non-www domain
if [ ! -d /etc/letsencrypt/live/$DOMAIN_ROOT ]; then
    certbot --nginx -d $DOMAIN_ROOT --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already exists for $DOMAIN_ROOT"
fi

# Certificate for www domain
if [ ! -d /etc/letsencrypt/live/$DOMAIN_WWW ]; then
    certbot --nginx -d $DOMAIN_WWW --email $EMAIL --agree-tos --non-interactive --redirect
else
    echo "SSL certificate already exists for $DOMAIN_WWW"
fi

echo ""
echo "=========================================="
echo "✅ WWW Redirect Setup Complete!"
echo "=========================================="
echo ""
echo "Configuration:"
echo "  • http://$DOMAIN_ROOT → https://$DOMAIN_WWW"
echo "  • https://$DOMAIN_ROOT → https://$DOMAIN_WWW"
echo "  • https://$DOMAIN_WWW → Landing page"
echo ""
echo "Landing page: $LANDING_DIR/index.html"
echo "Edit this file to customize your landing page"
echo ""
echo "To redirect www to production instead:"
echo "  1. Edit /etc/nginx/sites-available/homeadmin24-redirect"
echo "  2. Uncomment the redirect option and update domain"
echo "  3. Run: nginx -t && systemctl reload nginx"
echo ""
