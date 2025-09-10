# Stage 1: Build dependencies as root
FROM python:3.11-slim as builder

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl gnupg gosu git nano \
    && rm -rf /var/lib/apt/lists/*

# Use a virtual environment to isolate dependencies
WORKDIR /app
COPY requirements.txt .
RUN python -m venv /app/venv && \
    /app/venv/bin/pip install --no-cache-dir -r requirements.txt
COPY . .


# --- Stage 2: Final image ---
FROM python:3.11-slim

# Install system dependencies (gosu and docker) needed in the final image
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl gnupg gosu git nano sudo \
    && rm -rf /var/lib/apt/lists/*
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg
RUN echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
RUN apt-get update && apt-get install -y --no-install-recommends docker-ce-cli docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*


# Copy everything from builder stage
COPY --from=builder /app /app

# Copy entrypoint script and make it executable
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Add virtual environment's bin to PATH
ENV PATH="/app/venv/bin:$PATH"

# Set working directory
WORKDIR /app

# All setup done. The container MUST start as root for the entrypoint script
# to be able to create the user and group based on host UID/GID.
USER root

# Install Nginx and PHP-FPM before entrypoint
RUN apt-get update && apt-get install -y nginx php-fpm php-sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Copy nginx config
COPY nginx.conf /etc/nginx/sites-available/zeroai
RUN ln -sf /etc/nginx/sites-available/zeroai /etc/nginx/sites-enabled/default

# Configure PHP-FPM
RUN sed -i 's/listen = \/run\/php\/php.*-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/*/fpm/pool.d/www.conf

# Create nginx directories and set permissions
RUN mkdir -p /var/lib/nginx/body /var/lib/nginx/fastcgi /var/lib/nginx/proxy /var/lib/nginx/scgi /var/lib/nginx/uwsgi \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx

# Fix core permissions issue - set /app ownership to www-data
RUN chown -R www-data:www-data /app

# Configure git to maintain www-data ownership
RUN git config --global --add safe.directory /app \
    && git config --global user.name "www-data" \
    && git config --global user.email "www-data@zeroai.local"

# Install git wrapper and configure sudo
COPY git-wrapper.sh /usr/local/bin/git-wrapper
RUN chmod +x /usr/local/bin/git-wrapper \
    && echo 'www-data ALL=(ALL) NOPASSWD: /usr/bin/git' >> /etc/sudoers \
    && echo 'alias git="/usr/local/bin/git-wrapper"' >> /etc/bash.bashrc

# Create git hook to fix ownership after pulls
RUN mkdir -p /app/.git/hooks \
    && echo '#!/bin/bash' > /app/.git/hooks/post-merge \
    && echo 'chown -R www-data:www-data /app' >> /app/.git/hooks/post-merge \
    && chmod +x /app/.git/hooks/post-merge

# Configure nginx directories and permissions
RUN mkdir -p /var/log/nginx /var/lib/nginx /run /tmp/nginx \
    && chown -R www-data:www-data /var/log/nginx /var/lib/nginx /run /tmp/nginx

# Create startup script that runs as root for chown, then starts services
RUN echo '#!/bin/bash' > /app/start_portal.sh \
    && echo 'set -e' >> /app/start_portal.sh \
    && echo '# Fix ownership silently' >> /app/start_portal.sh \
    && echo 'find /app -type f -exec chown www-data:www-data {} + 2>/dev/null || true' >> /app/start_portal.sh \
    && echo 'find /app -type d -exec chown www-data:www-data {} + 2>/dev/null || true' >> /app/start_portal.sh \
    && echo 'mkdir -p /app/data && chmod 777 /app/data' >> /app/start_portal.sh \
    && echo 'service php8.4-fpm start' >> /app/start_portal.sh \
    && echo 'nginx -g "daemon off;"' >> /app/start_portal.sh \
    && chmod +x /app/start_portal.sh

# Stay as root to run startup script
CMD ["/app/start_portal.sh"]
