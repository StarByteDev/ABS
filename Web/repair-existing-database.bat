@echo off
setlocal
TITLE ABS V14.6.1 MySQL Repair
cd /d "%~dp0"

echo Alpha Block Solutions V14.6.1 - Non-destructive MySQL Repair
php cleanup-legacy-migrations.php
if errorlevel 1 exit /b 1
php artisan optimize:clear
if errorlevel 1 exit /b 1
php artisan abs:test-doctor
if errorlevel 1 exit /b 1
php artisan abs:repair --seed
if errorlevel 1 exit /b 1
php artisan abs:doctor
