@echo off
setlocal EnableDelayedExpansion

echo ============================================
echo  MoveZ - Full Build
echo ============================================
echo.

:: Use bundled PHP (no system PHP required)
set BUNDLED_PHP=%~dp0electron-app\resources\php\php.exe
set COMPOSER_BAT=%~dp0movez\composer.bat
set PHAR_SRC=%~dp0movez
set PHAR_DEST=%~dp0electron-app\resources\movez.phar
set ELECTRON_APP=%~dp0electron-app

:: -----------------------------------------------
:: Step 1 - Build the PHAR
:: -----------------------------------------------
echo [1/3] Building movez.phar ...
cd /d "%PHAR_SRC%"
"%BUNDLED_PHP%" -d phar.readonly=0 box.phar compile --composer-bin "%COMPOSER_BAT%"
if errorlevel 1 (
    echo [ERROR] PHAR build failed.
    pause
    exit /b 1
)

:: -----------------------------------------------
:: Step 2 - Copy PHAR to Electron resources
:: -----------------------------------------------
echo [2/3] Copying PHAR to electron-app\resources\ ...
copy /y "%PHAR_SRC%\movez.phar" "%PHAR_DEST%"
if errorlevel 1 (
    echo [ERROR] Could not copy PHAR.
    pause
    exit /b 1
)

:: -----------------------------------------------
:: Step 3 - Build Electron installer
:: -----------------------------------------------
echo [3/3] Building Electron installer (npm run package) ...
cd /d "%ELECTRON_APP%"
call npm run package
if errorlevel 1 (
    echo [ERROR] Electron build failed.
    pause
    exit /b 1
)

echo.
echo ============================================
echo  Done! Installer is in:
echo  electron-app\dist\
echo ============================================
pause
