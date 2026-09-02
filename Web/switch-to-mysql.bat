@echo off
setlocal
cd /d "%~dp0"
php cleanup-legacy-migrations.php
if errorlevel 1 exit /b 1
php configure-mysql.php
if errorlevel 1 exit /b 1
php artisan optimize:clear
php artisan abs:doctor
