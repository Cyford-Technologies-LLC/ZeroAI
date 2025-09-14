#!/bin/bash
set -e
echo "Starting ZeroAI services..."

# Install git wrapper and fix permissions
chmod +x /app/git
cp /app/git /usr/local/bin/git
chmod +x /usr/local/bin/git
mkdir -p /app/data && chown -R www-data:www-data /app/data /app/www /app/logs && chmod -R 775 /app/data /app/logs

# Start Redis
echo "Starting Redis..."
redis-server --daemonize yes --logfile /var/log/redis.log
sleep 2
if ! redis-cli ping > /dev/null 2>&1; then
    echo "ERROR: Redis failed to start"
    exit 1
fi
echo "Redis started successfully"

# Setup queue processing cron
echo "Setting up queue processing..."
echo "* * * * * /usr/bin/php /app/scripts/process_queue.php >> /app/logs/queue.log 2>&1" | crontab -
service cron start
echo "Queue processing enabled"

# Start PHP-FPM
echo "Starting PHP-FPM..."
service php8.4-fpm start
if ! pgrep php-fpm > /dev/null; then
    echo "ERROR: PHP-FPM failed to start"
    exit 1
fi
echo "PHP-FPM started successfully"

# Start background services
echo "Starting background services..."
python3 /app/src/agents/peer_monitor_agent.py &
python3 /app/scripts/cron_runner.py &

# Start Gunicorn API
echo "Starting API server..."
/app/venv/bin/gunicorn API.api:app --bind 0.0.0.0:3939 --worker-class uvicorn.workers.UvicornWorker --workers 2 --preload &

# Start Nginx (foreground)
echo "Starting Nginx..."
echo "All services started successfully"
nginx -g "daemon off;"