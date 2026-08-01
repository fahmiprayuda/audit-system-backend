FROM dunglas/frankenphp:1-php8.2

RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    bcmath \
    exif \
    pcntl \
    intl \
    zip \
    opcache

WORKDIR /app

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install dependency PHP
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs

COPY package*.json ./
RUN npm install

# Copy source
COPY . .

# Build Vite
RUN npm run build

# Laravel cache
RUN mkdir -p storage/framework/{cache,sessions,views,testing} \
    storage/logs \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan event:cache

EXPOSE 8080

CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public"]