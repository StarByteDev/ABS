@echo off
cd /d "%~dp0"
echo Checking hard-fixed Pulse launcher identity...
findstr /C:"android:label=\"Pulse\"" android\app\src\main\AndroidManifest.xml || goto :fail
findstr /C:"<string name=\"app_name\">Pulse</string>" android\app\src\main\res\values\strings.xml || goto :fail
findstr /C:"applicationId = \"com.alphablocksolutions.abs\"" android\app\build.gradle.kts || goto :fail
echo PASS: Android launcher identity is hard-fixed to Pulse.
exit /b 0
:fail
echo FAIL: Launcher identity files are not correct.
exit /b 1
