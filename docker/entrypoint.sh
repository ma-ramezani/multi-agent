#!/bin/sh
set -e
cd /var/www/html

# .env from scratch on first boot (compose mounts ./ .env or provides env vars).
if [ ! -f .env ]; then
  echo "[entrypoint] creating .env from .env.example"
  cp .env.example .env
fi

# Application key (skip if already set).
if ! grep -q '^APP_KEY=.\+' .env 2>/dev/null; then
  echo "[entrypoint] generating APP_KEY"
  php artisan key:generate --force
fi

# Writable paths (volumes may be freshly created as root).
mkdir -p database storage/app storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Database schema.
echo "[entrypoint] running migrations"
php artisan migrate --force

echo "[entrypoint] starting supervisord (web + queue worker)"
exec "$@"
