#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
php cleanup-legacy-migrations.php
php artisan optimize:clear
php artisan abs:test-doctor
php artisan abs:repair --seed
php artisan abs:doctor
