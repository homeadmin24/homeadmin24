#!/bin/bash

# Ensure data directories exist and have correct permissions
mkdir -p /var/www/html/data/dokumente/hausgeldabrechnung
mkdir -p /var/www/html/data/dokumente/rechnungen
mkdir -p /var/www/html/data/dokumente/uploads
mkdir -p /var/www/html/data/dokumente/bank-statements
mkdir -p /var/www/html/data/dokumente/protokolle
mkdir -p /var/www/html/data/dokumente/vertraege
chown -R www-data:www-data /var/www/html/data
find /var/www/html/data -type d -exec chmod 700 {} +
find /var/www/html/data -type f -exec chmod 600 {} +

# Ensure var directory has correct permissions
chown -R www-data:www-data /var/www/html/var
find /var/www/html/var -type d -exec chmod 700 {} +
find /var/www/html/var -type f -exec chmod 600 {} +

# Start PHP-FPM in background
php-fpm -D

# Test nginx config
nginx -t

# Start nginx in foreground
exec nginx -g "daemon off;"
