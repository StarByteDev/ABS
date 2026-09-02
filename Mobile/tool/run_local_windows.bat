@echo off
setlocal
where flutter >nul 2>nul || (echo ERROR: Flutter is not installed or not on PATH.& exit /b 1)
echo Starting ABS Mobile against Laravel at 10.0.2.2:8000 for Android Emulator...
flutter run --dart-define=ABS_API_BASE_URL=http://10.0.2.2:8000/api/v1
