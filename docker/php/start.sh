#!/bin/sh
set -eu

ENV_FILE="${APP_ENV_FILE:-/var/www/html/docker/environment/app.env}"

load_app_key() {
    if [ ! -f "$ENV_FILE" ]; then
        return
    fi

    file_app_key="$(sed -n 's/^APP_KEY=//p' "$ENV_FILE" | tail -n 1 | sed 's/^"//; s/"$//')"

    if [ -n "$file_app_key" ]; then
        export APP_KEY="$file_app_key"
    fi
}

case "${1:-app}" in
    app)
        rm -f storage/app/docker-installed
        load_app_key
        php artisan app:install
        load_app_key
        touch storage/app/docker-installed
        exec php-fpm
        ;;
    horizon)
        load_app_key
        exec php artisan horizon
        ;;
    *)
        load_app_key
        exec "$@"
        ;;
esac
