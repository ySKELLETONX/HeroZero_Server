@echo off
setlocal
title Hero Zero - Painel Admin
chcp 65001 >nul

set "ROOT=%~dp0"
set "PHP=%ROOT%..\tools\php83\php.exe"

if not exist "%PHP%" (
  echo ERRO: PHP 8.3 nao encontrado em "%PHP%".
  pause & exit /b 1
)

echo ============================================
echo   Hero Zero - Painel Admin
echo   http://127.0.0.1:8001/  (senha no .env: ADMIN_PASSWORD)
echo ============================================
echo.
echo OBS: o banco (Docker) e o servidor do jogo sobem pelo
echo      server-laravel\start.bat — este aqui e so o painel.
echo.
cd /d "%ROOT%"
"%PHP%" artisan serve --host=0.0.0.0 --port=8001

pause
endlocal
