#!/usr/bin/env bash
#
# Update aplikasi di VPS setelah upload kode baru (rsync/scp).
#   sudo bash deploy/update.sh
#
set -euo pipefail

PHP_VER=8.4
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="$(command -v php${PHP_VER} || command -v php)"

cd "$APP_DIR"

"$PHP_BIN" artisan down || true

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet
"$PHP_BIN" artisan migrate --force

"$PHP_BIN" artisan config:cache --quiet
"$PHP_BIN" artisan route:cache --quiet
"$PHP_BIN" artisan view:cache --quiet

chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
systemctl restart "php${PHP_VER}-fpm"

"$PHP_BIN" artisan up

echo "Update selesai."
