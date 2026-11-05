#!/bin/sh

set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -f vendor/autoload.php ]; then
    if [ "${APP_ENV:-production}" = "production" ]; then
        echo "Production dependencies are missing. Rebuild the application image." >&2
        exit 1
    fi

    composer install --no-interaction --prefer-dist
fi

if [ "${APP_ENV:-production}" != "production" ]; then
    chmod -R a+rwX storage bootstrap/cache
fi

if [ "${APP_ENV:-production}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required in production." >&2
    exit 1
fi

container_role="${CONTAINER_ROLE:-app}"

if [ "$container_role" = "app" ] && [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction
fi

if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
    database_path="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    database_directory="${database_path%/*}"

    mkdir -p "$database_directory"
    touch "$database_path"

    if [ "${APP_ENV:-production}" != "production" ]; then
        chmod a+rwx "$database_directory"
        chmod a+rw "$database_path"
    else
        chmod u+rwx "$database_directory"
        chmod u+rw "$database_path"
    fi
fi

if [ "$container_role" = "app" ] && [ "${APP_DB_BOOTSTRAP:-false}" = "true" ]; then
    if [ "${DB_CONNECTION:-}" != "sqlsrv" ]; then
        echo "APP_DB_BOOTSTRAP requires DB_CONNECTION=sqlsrv." >&2
        exit 1
    fi

    php artisan app:database:install --no-interaction
    php artisan migrate --force --no-interaction

    if [ "${APP_DB_SEED:-true}" = "true" ]; then
        php artisan db:seed --force --no-interaction
    fi
elif [ "$container_role" = "app" ] && [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "$container_role" = "queue" ]; then
    exec php artisan queue:work \
        "${QUEUE_CONNECTION:-redis}" \
        --sleep="${QUEUE_SLEEP:-3}" \
        --tries="${QUEUE_TRIES:-3}" \
        --timeout="${QUEUE_TIMEOUT:-120}"
fi

if [ "$container_role" = "scheduler" ]; then
    exec php artisan schedule:work --no-interaction
fi

exec "$@"
