@echo off
chcp 65001 >nul
echo ==========================================================
echo    Goc Nho - Laravel Framework Development Server
echo ==========================================================
echo.
set PHP_CLI_SERVER_WORKERS=4
php artisan serve --host=127.0.0.1 --port=8000
pause
