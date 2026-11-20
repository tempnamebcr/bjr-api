#!/bin/sh
set -e

# api and horizon share the same code mount and vendor/node_modules volumes and start together.
# Without a lock both run `composer install` into the same vendor/ at once and break each other's
# temp files. The lock file lives in the shared vendor volume, so the second container waits,
# then sees the finished install and skips it.
exec 9>vendor/.install.lock
flock 9

# vendor/ and node_modules/ are named volumes, so the directories always exist (even empty).
# Check for a file inside them instead, otherwise installs never run on a fresh volume.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules/.bin ]; then
    npm install
fi

# A fresh clone has no .env; Laravel still needs an APP_KEY.
# Values from env_file (.env.docker) take precedence over .env.
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Runs after composer install, so artisan can boot.
php artisan migrate --force 2>/dev/null || true

# Release the lock and close the descriptor so it isn't inherited by the long-running process.
flock -u 9
exec 9>&-

exec "$@"
