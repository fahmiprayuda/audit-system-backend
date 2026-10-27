FROM dunglas/frankenphp:1-php8.2

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libicu-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    bcmath \
    exif \
    intl \
    zip \
    opcache

WORKDIR /app

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js 22
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# Copy seluruh project
COPY . .

# Install PHP dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

# Install Node dependencies
RUN npm install

# Build Vite assets
RUN npm run build

# Laravel directories
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Hanya discover package
RUN php artisan package:discover --ansi

RUN chmod +x docker/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]