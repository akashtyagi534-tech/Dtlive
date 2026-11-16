FROM php:8.2-apache

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
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Point Apache DocumentRoot to Laravel's public/ directory and enable .htaccess
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Increase PHP upload limits for OTT media assets
RUN printf "upload_max_filesize=200M\npost_max_size=200M\nmemory_limit=512M\nmax_execution_time=300\n" \
    > /usr/local/etc/php/conf.d/dtlive-uploads.ini

WORKDIR /var/www/html

# Copy Laravel admin_panel source
COPY admin_panel/ /var/www/html/

# Install Composer dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Ensure required Laravel storage & cache directories exist and are writable
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && touch .env \
    && chown -R www-data:www-data storage bootstrap/cache .env \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

# Bind Railway dynamic $PORT, create storage symlink, and start Apache
CMD sh -c '\
    PORT_VAL="${PORT:-8080}"; \
    sed -i "s/Listen 80/Listen ${PORT_VAL}/g" /etc/apache2/ports.conf; \
    sed -i "s/:80>/:${PORT_VAL}>/g" /etc/apache2/sites-available/000-default.conf; \
    php artisan storage:link || true; \
    php artisan config:clear || true; \
    chown -R www-data:www-data storage bootstrap/cache; \
    exec apache2-foreground'
