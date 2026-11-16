FROM php:8.2-apache

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=600

# Install system dependencies & PHP extensions required by Laravel 10 + DTLive
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath \
        intl \
    && a2dismod mpm_event mpm_worker || true \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork rewrite headers alias \
    && rm -rf /var/lib/apt/lists/*

# Point Apache DocumentRoot to Laravel's public/ directory and enable .htaccess + /storage/app/public alias
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\nAlias /storage/app/public /var/www/html/storage/app/public\n<Directory /var/www/html/storage/app/public>\n    Options FollowSymLinks\n    AllowOverride None\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/dtlive-storage.conf \
    && a2enconf dtlive-storage

# Increase PHP upload limits for OTT media assets
RUN printf "upload_max_filesize=200M\npost_max_size=200M\nmemory_limit=512M\nmax_execution_time=300\n" \
    > /usr/local/etc/php/conf.d/dtlive-uploads.ini

WORKDIR /var/www/html

# Copy Laravel admin_panel source
COPY admin_panel/ /var/www/html/

# Create required Laravel directories and .env BEFORE composer install / package:discover
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && touch .env \
    && chmod -R 775 storage bootstrap/cache

# Install Composer dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts \
    && APP_URL=http://localhost php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache .env

EXPOSE 8080

# Ensure single Apache MPM, bind Railway dynamic $PORT idempotently, link storage, and start Apache
CMD sh -c '\
    rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*; \
    PORT_VAL="${PORT:-8080}"; \
    printf "Listen %s\n" "${PORT_VAL}" > /etc/apache2/ports.conf; \
    sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT_VAL}>/g" /etc/apache2/sites-available/000-default.conf; \
    rm -rf public/storage && php artisan storage:link || true; \
    php artisan config:clear || true; \
    chown -R www-data:www-data storage bootstrap/cache; \
    exec apache2-foreground'
