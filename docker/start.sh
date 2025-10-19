#!/bin/bash

# Start PHP-FPM in background
php-fpm -D

# Test nginx config
nginx -t

# Start nginx in foreground
exec nginx -g "daemon off;"