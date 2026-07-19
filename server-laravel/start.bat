@echo off
setlocal EnableDelayedExpansion
title Hero Zero - Servidor (Laravel)
chcp 65001 >nul

REM ============================================================
REM  Liga TODOS os servicos do Hero Zero (versao Laravel):
REM    1) Banco de dados MySQL (Docker, porta 3308)
REM    2) Servidor do jogo (Laravel via artisan serve, porta 8000)
REM  Basta dar duplo-clique neste arquivo.
REM ============================================================

set "ROOT=%~dp0"
set "PHP=%ROOT%..\tools\php83\php.exe"

echo ============================================
echo   Hero Zero - subindo servicos (Laravel)
echo ============================================
echo.

if not exist "%PHP%" (
  echo ERRO: PHP 8.3 nao encontrado em "%PHP%".
  echo Ajuste a variavel PHP no topo deste .bat.
  pause & exit /b 1
)
where docker >nul 2>&1
if errorlevel 1 (
  echo ERRO: comando "docker" nao encontrado. O Docker Desktop esta instalado/aberto?
  pause & exit /b 1
)

REM --- 1) Banco de dados (Docker) ----------------------------
echo [1/2] Subindo o banco (MySQL em Docker)...
docker compose -f "%ROOT%docker\docker-compose.yml" up -d
if errorlevel 1 (
  echo.
  echo ERRO: falha ao subir o Docker. O Docker Desktop esta aberto?
  pause & exit /b 1
)

echo       aguardando o banco ficar pronto...
set /a tries=0
:waitdb
set "HS="
for /f "tokens=*" %%s in ('docker inspect -f "{{.State.Health.Status}}" herozero-mysql 2^>nul') do set "HS=%%s"
if /i "!HS!"=="healthy" goto dbok
set /a tries+=1
if !tries! geq 40 (
  echo       AVISO: o banco demorou a responder; seguindo mesmo assim.
  goto dbok
)
timeout /t 2 /nobreak >nul
goto waitdb
:dbok
echo       banco pronto ^(porta 3308^).
echo.

REM --- 2) Servidor do jogo (Laravel) -------------------------
echo [2/2] Subindo o servidor do jogo (Laravel na porta 8000)...
echo       jogar:       http://127.0.0.1:8000/
echo       criar conta: http://127.0.0.1:8000/beta.html
echo.
cd /d "%ROOT%"
set PHP_CLI_SERVER_WORKERS=8
"%PHP%" artisan serve --host=0.0.0.0 --port=8000

echo.
echo Servidor encerrado. O banco (Docker) continua rodando.
pause
endlocal
