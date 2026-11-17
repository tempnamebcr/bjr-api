set -e

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

chmod -R 777 storage bootstrap/cache 2>/dev/null || true

php artisan migrate --force 2>/dev/null || true

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
    npm install
fi

exec "$@"
