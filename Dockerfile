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

# Fix ownership and git wrapper after copying code
RUN mkdir -p /app/data /app/logs /app/knowledge \
    && chown -R www-data:www-data /app \
    && chmod -R 775 /app/www /app/data /app/logs /app/knowledge \
    && chmod +x /app/git

# Configure PHP-FPM and PHP optimizations
RUN PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") \
    && sed -i 's/listen = \/run\/php\/php.*-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/$PHP_VERSION/fpm/pool.d/www.conf \
    && echo 'opcache.enable=1' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'opcache.memory_consumption=128' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'opcache.max_accelerated_files=4000' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'opcache.revalidate_freq=2' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'apc.enabled=1' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'apc.shm_size=64M' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'session.save_handler=redis' >> /etc/php/$PHP_VERSION/fpm/php.ini \
    && echo 'session.save_path="tcp://127.0.0.1:6379"' >> /etc/php/$PHP_VERSION/fpm/php.ini

# Copy nginx config
COPY nginx.conf /etc/nginx/sites-available/zeroai
RUN ln -sf /etc/nginx/sites-available/zeroai /etc/nginx/sites-enabled/default

# Create necessary directories and set permissions
RUN mkdir -p /var/lib/nginx/body /var/lib/nginx/fastcgi /var/lib/nginx/proxy /var/lib/nginx/scgi /var/lib/nginx/uwsgi \
    && mkdir -p /var/log/nginx /var/lib/nginx /run /tmp/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run /tmp/nginx

# Configure git and prevent read-only filesystem issues
RUN git config --global --add safe.directory /app \
    && git config --global user.name "www-data" \
    && git config --global user.email "www-data@zeroai.local" \
    && echo 'tmpfs /tmp tmpfs rw,nodev,nosuid,size=1G 0 0' >> /etc/fstab

# Git wrapper will be installed after code copy

# Add virtual environment to PATH
ENV PATH="/app/venv/bin:$PATH"

# Copy and setup startup script
COPY start_services.sh /app/start_services.sh
RUN chmod +x /app/start_services.sh

CMD ["/app/start_services.sh"]
