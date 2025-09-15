#!/bin/bash
set -e
echo "Starting ZeroAI services..."

# Install git wrapper and fix permissions
chmod +x /app/git
cp /app/git /usr/local/bin/git
chmod +x /usr/local/bin/git

# Create directories and fix ownership (AFTER volumes are mounted)
mkdir -p /app/data /app/logs /app/knowledge/internal_crew/agent_learning/self/claude/sessions_data
# Skip .env file (read-only) and chown specific directories
chown -R www-data:www-data /app/data /app/logs /app/knowledge /app/www /app/src /app/API /app/run /app/config /app/examples /app/scripts
chmod -R 775 /app/data /app/logs /app/knowledge /app/www

# Start Redis
echo "Starting Redis..."
redis-server --daemonize yes --logfile /var/log/redis.log --bind 127.0.0.1 --port 6379
sleep 3
if ! redis-cli ping > /dev/null 2>&1; then
    echo "WARNING: Redis not responding, attempting restart..."
    pkill redis-server || true
    sleep 1
    redis-server --daemonize yes --logfile /var/log/redis.log --bind 127.0.0.1 --port 6379
    sleep 2
    if ! redis-cli ping > /dev/null 2>&1; then
        echo "ERROR: Redis failed to start after retry"
        exit 1
    fi
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