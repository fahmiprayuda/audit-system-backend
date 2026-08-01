FROM dunglas/frankenphp:php8.2

RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    tokenizer \
    xml \
    ctype \
    fileinfo \
    session \
    dom \
    curl

WORKDIR /app

COPY . .

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

RUN npm install && npm run build

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD php artisan migrate --force && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan serve --host=0.0.0.0 --port=${PORT}