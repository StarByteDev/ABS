#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
php cleanup-legacy-migrations.php
php configure-mysql.php
php artisan optimize:clear
php artisan abs:doctor
