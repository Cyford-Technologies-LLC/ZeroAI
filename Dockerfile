FROM python:3.11-slim

# Install ALL system dependencies in one layer
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl gnupg git nano sudo cron \
    nginx php-fpm php-sqlite3 php-curl php-json php-mbstring php-xml php-zip php-gd php-intl \
    php-apcu php-opcache php-redis \
    redis-server \
    && rm -rf /var/lib/apt/lists/*

# Install Docker CLI
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update && apt-get install -y --no-install-recommends docker-ce-cli \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy and install Python dependencies
COPY requirements.txt .
RUN python -m venv /app/venv && \
    /app/venv/bin/pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Configure PHP-FPM and PHP optimizations
RUN sed -i 's/listen = \/run\/php\/php.*-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/*/fpm/pool.d/www.conf \
    && echo 'opcache.enable=1' >> /etc/php/*/fpm/php.ini \
    && echo 'opcache.memory_consumption=128' >> /etc/php/*/fpm/php.ini \
    && echo 'opcache.max_accelerated_files=4000' >> /etc/php/*/fpm/php.ini \
    && echo 'opcache.revalidate_freq=2' >> /etc/php/*/fpm/php.ini \
    && echo 'apc.enabled=1' >> /etc/php/*/fpm/php.ini \
    && echo 'apc.shm_size=64M' >> /etc/php/*/fpm/php.ini \
    && echo 'redis.session.save_handler=redis' >> /etc/php/*/fpm/php.ini \
    && echo 'redis.session.save_path="tcp://127.0.0.1:6379"' >> /etc/php/*/fpm/php.ini

# Copy nginx config
COPY nginx.conf /etc/nginx/sites-available/zeroai
RUN ln -sf /etc/nginx/sites-available/zeroai /etc/nginx/sites-enabled/default

# Create necessary directories and set permissions
RUN mkdir -p /var/lib/nginx/body /var/lib/nginx/fastcgi /var/lib/nginx/proxy /var/lib/nginx/scgi /var/lib/nginx/uwsgi \
    && mkdir -p /var/log/nginx /var/lib/nginx /run /tmp/nginx \
    && mkdir -p /app/data /app/logs \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run /tmp/nginx /app/data /app/logs \
    && chmod -R 775 /app/data /app/logs

# Configure git
RUN git config --global --add safe.directory /app \
    && git config --global user.name "www-data" \
    && git config --global user.email "www-data@zeroai.local"

# Install git wrapper
RUN chmod +x /app/git && ln -sf /app/git /usr/local/bin/git

# Add virtual environment to PATH
ENV PATH="/app/venv/bin:$PATH"

# Create comprehensive startup script
RUN echo '#!/bin/bash\n\
set -e\n\
echo "Starting ZeroAI services..."\n\
\n\
# Ensure data directory permissions\n\
mkdir -p /app/data && chown -R www-data:www-data /app/data /app/www && chmod -R 775 /app/data\n\
\n\
# Start Redis\n\
echo "Starting Redis..."\n\
redis-server --daemonize yes --logfile /var/log/redis.log\n\
sleep 2\n\
if ! redis-cli ping > /dev/null 2>&1; then\n\
    echo "ERROR: Redis failed to start"\n\
    exit 1\n\
fi\n\
echo "Redis started successfully"\n\
\n\
# Setup queue processing cron\n\
echo "Setting up queue processing..."\n\
echo "* * * * * /usr/bin/php /app/scripts/process_queue.php >> /app/logs/queue.log 2>&1" | crontab -\n\
service cron start\n\
echo "Queue processing enabled"\n\
\n\
# Start PHP-FPM\n\
echo "Starting PHP-FPM..."\n\
service php8.4-fpm start\n\
if ! pgrep php-fpm > /dev/null; then\n\
    echo "ERROR: PHP-FPM failed to start"\n\
    exit 1\n\
fi\n\
echo "PHP-FPM started successfully"\n\
\n\
# Start background services\n\
echo "Starting background services..."\n\
python3 /app/src/agents/peer_monitor_agent.py &\n\
python3 /app/scripts/cron_runner.py &\n\
\n\
# Start Gunicorn API\n\
echo "Starting API server..."\n\
/app/venv/bin/gunicorn API.api:app --bind 0.0.0.0:3939 --worker-class uvicorn.workers.UvicornWorker --workers 2 --preload &\n\
\n\
# Start Nginx (foreground)\n\
echo "Starting Nginx..."\n\
echo "All services started successfully"\n\
nginx -g "daemon off;"\n\
' > /app/start.sh && chmod +x /app/start.sh

CMD ["/app/start.sh"]
