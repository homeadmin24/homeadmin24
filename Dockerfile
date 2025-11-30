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

# Create sessions directory and set permissions
RUN mkdir -p /var/www/html/var/sessions/dev \
    && mkdir -p /var/www/html/var/sessions/prod \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 777 /var/www/html/var/sessions

# Start script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

ENTRYPOINT ["/start.sh"]