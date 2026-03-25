#!/usr/bin/env bash

set -euo pipefail

cd /opt/render/project/src

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export APP_URL="${APP_URL:-https://your-app.onrender.com}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-/opt/render/project/src/database/database.sqlite}"

mkdir -p database storage bootstrap/cache
touch "$DB_DATABASE"
chmod -R ug+rwX storage bootstrap/cache database

composer install --no-dev --optimize-autoloader

if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi

php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php -S 0.0.0.0:10000 -t public
