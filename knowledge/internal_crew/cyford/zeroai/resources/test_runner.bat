@echo off
setlocal enabledelayedexpansion

echo ========================================
echo ZeroAI Test Environment Manager
echo ========================================

set "SCRIPT_DIR=%~dp0"
set "ROOT_DIR=%SCRIPT_DIR%..\..\..\..\.."

cd /d "%ROOT_DIR%"

:menu
echo.
echo Select an option:
echo 1. Start Test Environment (CPU)
echo 2. Start Test Environment (GPU)
echo 3. Stop Test Environment
echo 4. Rebuild and Start (CPU)
echo 5. Rebuild and Start (GPU)
echo 6. Prune and Fresh Build (CPU)
echo 7. Prune and Fresh Build (GPU)
echo 8. View Test Logs
echo 9. Exit
echo.
set /p choice="Enter choice (1-9): "

if "%choice%"=="1" goto start_cpu
if "%choice%"=="2" goto start_gpu
if "%choice%"=="3" goto stop
if "%choice%"=="4" goto rebuild_cpu
if "%choice%"=="5" goto rebuild_gpu
if "%choice%"=="6" goto prune_cpu
if "%choice%"=="7" goto prune_gpu
if "%choice%"=="8" goto logs
if "%choice%"=="9" goto exit
goto menu

:start_cpu
echo Starting Test Environment (CPU)...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml up -d
goto show_urls

:start_gpu
echo Starting Test Environment (GPU)...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
goto show_urls

:stop
echo Stopping Test Environment...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml down
echo Test environment stopped.
goto menu

:rebuild_cpu
echo Rebuilding and Starting (CPU)...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml down
docker-compose -f docker-compose.testing.yml build --no-cache
docker-compose -f docker-compose.testing.yml up -d
goto show_urls

:rebuild_gpu
echo Rebuilding and Starting (GPU)...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml down
docker-compose -f docker-compose.testing.yml build --no-cache
docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
goto show_urls

:prune_cpu
echo Pruning Docker and Fresh Build (CPU)...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml down
docker system prune -f
docker volume prune -f
docker-compose -f docker-compose.testing.yml build --no-cache
docker-compose -f docker-compose.testing.yml up -d
goto show_urls

:prune_gpu
echo Pruning Docker and Fresh Build (GPU)...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml down
docker system prune -f
docker volume prune -f
docker-compose -f docker-compose.testing.yml build --no-cache
docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
goto show_urls

:logs
echo Showing Test Container Logs...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml logs -f
goto menu

:show_urls
echo.
echo ========================================
echo Test Environment Started Successfully!
echo ========================================
echo.
echo Test URLs:
echo - API:           http://localhost:4949
echo - Web Interface: http://localhost:444
echo - Peer Service:  http://localhost:9090
echo - Ollama:        http://localhost:12434
echo.
echo Container Status:
docker ps --filter "name=zeroai_.*-test"
echo.
goto menu

:exit
echo Exiting...
exit /b 0