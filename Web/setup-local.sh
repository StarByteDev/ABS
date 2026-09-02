#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "====================================================="
echo "Alpha Block Solutions V14.6.2 - MySQL Local Setup"
echo "====================================================="

command -v php >/dev/null || { echo "ERROR: PHP was not found in PATH."; exit 1; }
php -m | grep -qi '^pdo_mysql$' || { echo "ERROR: PHP extension pdo_mysql is not enabled."; exit 1; }
command -v composer >/dev/null || { echo "ERROR: Composer was not found in PATH."; exit 1; }

php cleanup-legacy-migrations.php
composer install
php configure-mysql.php

grep -q '^APP_KEY=base64:' .env || php artisan key:generate
php artisan optimize:clear
php artisan abs:test-doctor
php artisan abs:repair --seed
php artisan storage:link >/dev/null 2>&1 || true
php artisan abs:doctor

echo "Preloading public market dashboard data..."
php artisan abs:cache-market || echo "WARNING: Market providers are temporarily unavailable. The homepage will retry automatically in the browser."

echo
echo "ABS V14.6.2 MySQL setup completed successfully."
echo "Run: php artisan serve"
echo "Open: http://127.0.0.1:8000"
