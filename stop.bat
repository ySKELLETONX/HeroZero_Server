@echo off
setlocal
title Hero Zero - Parando servicos
chcp 65001 >nul

REM ============================================================
REM  Encerra os servicos abertos pelo start.bat:
REM    - janelas HZ-Jogo / HZ-Admin / HZ-Socket (e os processos
REM      php.exe / node.exe que rodam dentro delas)
REM    - o banco de dados (Docker) fica de fora por padrao;
REM      passe o argumento "db" para tambem para-lo:
REM        stop.bat db
REM ============================================================

set "ROOT=%~dp0"

echo ============================================
echo   Hero Zero - encerrando servicos
echo ============================================
echo.

echo [1/3] Fechando o servidor do jogo (HZ-Jogo, porta 8000)...
taskkill /FI "WINDOWTITLE eq HZ-Jogo*" /T /F >nul 2>&1

echo [2/3] Fechando o painel admin (HZ-Admin, porta 8001)...
taskkill /FI "WINDOWTITLE eq HZ-Admin*" /T /F >nul 2>&1

echo [3/3] Fechando o servidor de socket (HZ-Socket, porta 8090)...
taskkill /FI "WINDOWTITLE eq HZ-Socket*" /T /F >nul 2>&1

if /i "%~1"=="db" (
  echo.
  echo Parando o banco de dados ^(Docker^)...
  docker compose -f "%ROOT%server-laravel\docker\docker-compose.yml" stop
) else (
  echo.
  echo O banco de dados ^(Docker^) continua rodando.
  echo Para para-lo tambem, rode: stop.bat db
)

echo.
echo Servicos encerrados.
pause
endlocal
