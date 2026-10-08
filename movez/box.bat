@echo off
:: Use bundled PHP from electron-app resources (no system PHP required)
set BUNDLED_PHP=%~dp0..\electron-app\resources\php\php.exe
if exist "%BUNDLED_PHP%" (
    "%BUNDLED_PHP%" -d phar.readonly=0 -d extension=fileinfo "%~dp0box.phar" %*
) else (
    php -d phar.readonly=0 "%~dp0box.phar" %*
)
