#!/bin/sh
set -e

# APP_KEY deve ser fornecida via variavel de ambiente/secret em producao.
# Gere uma vez com: php artisan key:generate --show
if [ ! -f "storage/framework/cache" ]; then
  mkdir -p storage/framework/{cache,sessions,views} storage/logs
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
