#!/bin/sh
set -eu

ENV_FILE="${APP_ENV_FILE:-/var/www/html/docker/environment/app.env}"
DOTENV_FILE="${APP_DOTENV_FILE:-/var/www/html/.env}"

normalize_env_value() {
    value="$(printf '%s' "$1" | tr -d '\r')"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"

    printf '%s' "$value"
}

escape_php_fpm_value() {
    printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

sync_dotenv_file() {
    if [ ! -f "$ENV_FILE" ]; then
        return
    fi

    cp "$ENV_FILE" "$DOTENV_FILE"
}

prepare_runtime_directories() {
    mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs

    chown -R www-data:www-data bootstrap/cache storage
    chmod -R ug+rwX bootstrap/cache storage
}

clear_runtime_cache() {
    php artisan optimize:clear
}

load_environment_file() {
    if [ ! -f "$ENV_FILE" ]; then
        return
    fi

    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in
            ''|\#*)
                continue
                ;;
        esac

        key="${line%%=*}"
        value="${line#*=}"

        if printf '%s' "$key" | grep -Eq '^[A-Za-z_][A-Za-z0-9_]*$'; then
            value="$(normalize_env_value "$value")"
            export "$key=$value"
        fi
    done < "$ENV_FILE"
}

write_php_fpm_environment() {
    if [ ! -f "$ENV_FILE" ]; then
        return
    fi

    fpm_env_file="/usr/local/etc/php-fpm.d/zz-app-env.conf"

    {
        printf '[www]\n'

        while IFS= read -r line || [ -n "$line" ]; do
            case "$line" in
                ''|\#*)
                    continue
                    ;;
            esac

            key="${line%%=*}"
            value="${line#*=}"

            if printf '%s' "$key" | grep -Eq '^[A-Za-z_][A-Za-z0-9_]*$'; then
                value="$(normalize_env_value "$value")"
                value="$(escape_php_fpm_value "$value")"
                printf 'env[%s] = "%s"\n' "$key" "$value"
            fi
        done < "$ENV_FILE"
    } > "$fpm_env_file"
}

case "${1:-app}" in
    app)
        rm -f storage/app/docker-installed
        sync_dotenv_file
        prepare_runtime_directories
        load_environment_file
        clear_runtime_cache
        php artisan app:install
        sync_dotenv_file
        prepare_runtime_directories
        load_environment_file
        clear_runtime_cache
        write_php_fpm_environment
        touch storage/app/docker-installed
        exec php-fpm
        ;;
    horizon)
        sync_dotenv_file
        prepare_runtime_directories
        load_environment_file
        clear_runtime_cache
        exec php artisan horizon
        ;;
    *)
        sync_dotenv_file
        prepare_runtime_directories
        load_environment_file
        exec "$@"
        ;;
esac
