#!/bin/sh
set -eu
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

if [ -n "${AIVEN_CA_BASE64:-}" ]; then
    umask 077
    printf '%s' "$AIVEN_CA_BASE64" | base64 --decode > storage/app/private/aiven-ca.pem
    openssl x509 -in storage/app/private/aiven-ca.pem -noout > /dev/null
    export MYSQL_ATTR_SSL_CA="$(pwd)/storage/app/private/aiven-ca.pem"
fi

if [ -n "${RENDER_EXTERNAL_URL:-}" ] && [ -z "${APP_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

if [ "${1:-}" = "apache2-foreground" ]; then
    server_port="${PORT:-80}"
    case "$server_port" in
        ''|*[!0-9]*) echo "PORT doit être un nombre." >&2; exit 1 ;;
    esac
    if [ "$server_port" -lt 1 ] || [ "$server_port" -gt 65535 ]; then
        echo "PORT hors plage." >&2
        exit 1
    fi
    sed -ri "s/^Listen [0-9]+$/Listen $server_port/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:$server_port>/" /etc/apache2/sites-available/000-default.conf
fi

chown -R www-data:www-data storage bootstrap/cache
exec docker-php-entrypoint "$@"
