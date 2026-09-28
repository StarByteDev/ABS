@echo off
setlocal
TITLE Alpha Block Solutions V15.3.0
cd /d "%~dp0"

call setup-local.bat
if errorlevel 1 exit /b 1

echo.
echo Starting ABS Pulse background engine...
echo The background window runs market refresh, strategy research and validation.
echo Change 5s / 30s / 1m / 2m / 5m from Admin ^> Pulse Strategy Lab.
start "ABS Pulse Background Engine" /D "%~dp0" cmd /k php artisan schedule:work

echo.
echo Starting Alpha Block Solutions V15.3.0...
echo Browser URL: http://127.0.0.1:8000
echo Keep the background engine window open while testing.
echo Press Ctrl+C here to stop the local web server.
echo.
php artisan serve --host=127.0.0.1 --port=8000
