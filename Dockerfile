FROM php:8.4-fpm

# Install minimal dependencies including Node.js for Webpack Encore
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libzip-dev \
    nginx \
    curl \
    && docker-php-ext-install pdo_mysql zip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Configure nginx
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx/default.conf /etc/nginx/sites-available/hausman
RUN ln -s /etc/nginx/sites-available/hausman /etc/nginx/sites-enabled/hausman

# Disable OPcache for development
COPY docker/php/opcache-dev.ini /usr/local/etc/php/conf.d/opcache-dev.ini

# Copy application (assuming assets are pre-built)
COPY . .

# Install composer dependencies (skip auto-scripts during build)
RUN apt-get update && apt-get install -y wget \
    && wget https://getcomposer.org/installer -O composer-setup.php \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --optimize-autoloader --no-scripts

# Build frontend assets with Webpack Encore
RUN npm install && npm run build

# Create directories and set permissions
RUN mkdir -p /var/www/html/var/sessions/dev \
    && mkdir -p /var/www/html/var/sessions/prod \
    && mkdir -p /var/www/html/data/dokumente/hausgeldabrechnung \
    && mkdir -p /var/www/html/data/dokumente/rechnungen \
    && mkdir -p /var/www/html/data/dokumente/uploads \
    && mkdir -p /var/www/html/data/dokumente/bank-statements \
    && mkdir -p /var/www/html/data/dokumente/protokolle \
    && mkdir -p /var/www/html/data/dokumente/vertraege \
    && chown -R www-data:www-data /var/www/html/var \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/var \
    && chmod -R 775 /var/www/html/data

# Start script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

ENTRYPOINT ["/start.sh"]