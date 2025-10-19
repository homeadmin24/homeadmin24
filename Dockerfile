FROM php:8.2-fpm

# Install minimal dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libzip-dev \
    nginx \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Configure nginx
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx/default.conf /etc/nginx/sites-available/hausman
RUN ln -s /etc/nginx/sites-available/hausman /etc/nginx/sites-enabled/hausman

# Copy application (assuming assets are pre-built)
COPY . .

# Install composer dependencies (including fixtures bundle)
RUN apt-get update && apt-get install -y wget \
    && wget https://getcomposer.org/installer -O composer-setup.php \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html/var

# Start script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

ENTRYPOINT ["/start.sh"]