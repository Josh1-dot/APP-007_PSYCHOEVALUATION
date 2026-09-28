#!/bin/sh
set -eu
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
exec docker-php-entrypoint "$@"
