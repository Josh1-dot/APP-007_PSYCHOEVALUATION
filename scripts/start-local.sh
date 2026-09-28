#!/bin/sh
# Convenience launcher for a normal PHP installation, or the isolated runtime used here.
set -eu
cd "$(dirname "$0")/.."
if command -v php >/dev/null 2>&1; then
    exec php artisan serve --host=127.0.0.1 --port=8000
elif [ -x /tmp/psycho-php/php ]; then
    cd public
    exec /tmp/psycho-php/php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
else
    echo "PHP 8.3+ requis. Voir README.md pour la configuration Docker + MySQL."
    exit 1
fi
