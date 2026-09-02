@echo off
setlocal
TITLE Alpha Block Solutions V14.6.2
cd /d "%~dp0"

call setup-local.bat
if errorlevel 1 exit /b 1

echo.
echo Starting Alpha Block Solutions...
echo Browser URL: http://127.0.0.1:8000
echo Press Ctrl+C to stop the local server.
echo.
php artisan serve --host=127.0.0.1 --port=8000
