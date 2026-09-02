@echo off
cd /d "%~dp0"
echo Checking hard-fixed ABS Pulse launcher identity...
findstr /C:"android:label=\"ABS Pulse\"" android\app\src\main\AndroidManifest.xml || goto :fail
findstr /C:"<string name=\"app_name\">ABS Pulse</string>" android\app\src\main\res\values\strings.xml || goto :fail
findstr /C:"applicationId = \"com.alphablocksolutions.abs\"" android\app\build.gradle.kts || goto :fail
echo PASS: Android launcher identity is hard-fixed to ABS Pulse.
exit /b 0
:fail
echo FAIL: Launcher identity files are not correct.
exit /b 1
