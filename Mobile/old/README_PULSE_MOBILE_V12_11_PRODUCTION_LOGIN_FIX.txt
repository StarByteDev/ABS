Pulse Trading Intelligence Mobile V12.11 - Production Login Cleanup

Changes in this package:
- Login page feature boxes below the login card removed as requested.
- Pulse logo and headline content remain aligned with the website branding.
- Production API remains: https://pulse.alphablocksolutions.com/api/mobile
- Root package format preserved. No extra wrapper/subfolder added.

Important production backend requirement:
Your production server error shows Laravel Sanctum token table is missing:
SQLSTATE[42S02]: Table '...personal_access_tokens' doesn't exist

This is not a Flutter UI issue. The mobile app can reach the live API, but Laravel cannot create login tokens until Sanctum migrations are installed on the production database.

Run on HostGator/cPanel terminal inside your Laravel project root:

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan route:list | grep mobile

If the personal_access_tokens migration file is missing, run locally or on server:

php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate --force

After migration succeeds, mobile login should work on production.
