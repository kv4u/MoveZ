@echo off
:: Run Composer with the PHP runtime bundled in electron-app\resources (no system PHP required)
"%~dp0..\electron-app\resources\php\php.exe" "%~dp0..\electron-app\resources\composer.phar" %*
