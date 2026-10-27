#!/bin/sh
set -e

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Seeding Enterprise Audit data..."
php artisan db:seed --class=EnterpriseAuditSeeder --force

echo "==> Optimizing configuration cache..."
php artisan config:cache || true
php artisan route:cache || true

echo "==> Starting FrankenPHP server on port 8080..."
exec frankenphp php-server --root public --listen :8080
