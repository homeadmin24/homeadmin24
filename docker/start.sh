#!/bin/bash

# Ensure data directories exist and have correct permissions
mkdir -p /var/www/html/data/dokumente/hausgeldabrechnung
mkdir -p /var/www/html/data/dokumente/rechnungen
mkdir -p /var/www/html/data/dokumente/uploads
mkdir -p /var/www/html/data/dokumente/bank-statements
mkdir -p /var/www/html/data/dokumente/protokolle
mkdir -p /var/www/html/data/dokumente/vertraege
chown -R www-data:www-data /var/www/html/data
chmod -R 775 /var/www/html/data

# Ensure var directory has correct permissions
chown -R www-data:www-data /var/www/html/var
chmod -R 775 /var/www/html/var

# Start PHP-FPM in background
php-fpm -D

# Test nginx config
nginx -t

# Start nginx in foreground
exec nginx -g "daemon off;"