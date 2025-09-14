@echo off
cd /d "%~dp0..\..\..\..\.."

echo Pruning Docker system...
docker system prune -f
docker volume prune -f

echo Building and starting test containers...
cd knowledge\internal_crew\cyford\zeroai\resources
docker-compose -f docker-compose.testing.yml down
docker-compose -f docker-compose.testing.yml build --no-cache
docker-compose -f docker-compose.testing.yml up -d

echo.
echo Test URLs:
echo - API: http://localhost:4949
echo - Web: http://localhost:444
echo - Peer: http://localhost:9090
echo - Ollama: http://localhost:12434

docker ps --filter "name=zeroai_.*-test"