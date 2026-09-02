@echo off
setlocal
TITLE ABS V14.6.2 Laragon MySQL Local Setup

cd /d "%~dp0"

echo =====================================================
echo Alpha Block Solutions V14.6.2 - Laragon MySQL Setup
echo =====================================================
echo.
echo Start Laragon and make sure MySQL is running before continuing.
echo Default local database: abs
echo Default local account: root with no password.
echo XAMPP/MySQL is also supported if you prefer it.
echo.

where php >nul 2>nul
if errorlevel 1 (
    echo ERROR: PHP was not found in PATH.
    echo Open Laragon Terminal, or add Laragon PHP to PATH, then rerun this file.
    goto error
)

php -m | findstr /I /C:"pdo_mysql" >nul
if errorlevel 1 (
    echo ERROR: PHP extension pdo_mysql is not enabled in the active PHP runtime.
    echo Enable pdo_mysql in Laragon PHP, restart the terminal, and retry.
    goto error
)

where composer >nul 2>nul
if errorlevel 1 (
    echo ERROR: Composer was not found in PATH.
    echo Install/enable Composer in Laragon or open a Laragon Terminal where Composer is available.
    goto error
)

php cleanup-legacy-migrations.php
if errorlevel 1 goto error

composer install
if errorlevel 1 goto error

php configure-mysql.php
if errorlevel 1 goto error

findstr /B "APP_KEY=base64:" .env >nul
if errorlevel 1 (
    php artisan key:generate
    if errorlevel 1 goto error
)

php artisan optimize:clear
if errorlevel 1 goto error

php artisan abs:test-doctor
if errorlevel 1 goto error

php artisan abs:repair --seed
if errorlevel 1 goto error

php artisan storage:link >nul 2>nul
php artisan abs:doctor
if errorlevel 1 goto error

echo.
echo Preloading public market dashboard data...
php artisan abs:cache-market
if errorlevel 1 echo WARNING: Market providers are temporarily unavailable. The homepage will retry automatically in the browser.

echo.
echo ABS V14.6.2 MySQL setup completed successfully.
echo Run: php artisan serve
echo Open: http://127.0.0.1:8000
exit /b 0

:error
echo.
echo Setup stopped because a command failed.
echo Review the Laragon/MySQL quick-start section at the beginning of README.md.
exit /b 1
