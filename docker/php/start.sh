#!/bin/sh
set -eu

ENV_FILE="${APP_ENV_FILE:-/var/www/html/docker/environment/app.env}"

normalize_env_value() {
    value="$(printf '%s' "$1" | tr -d '\r')"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"

    printf '%s' "$value"
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
                printf 'env[%s] = %s\n' "$key" "$value"
            fi
        done < "$ENV_FILE"
    } > "$fpm_env_file"
}

case "${1:-app}" in
    app)
        rm -f storage/app/docker-installed
        load_environment_file
        php artisan app:install
        load_environment_file
        write_php_fpm_environment
        touch storage/app/docker-installed
        exec php-fpm
        ;;
    horizon)
        load_environment_file
        exec php artisan horizon
        ;;
    *)
        load_environment_file
        exec "$@"
        ;;
esac
