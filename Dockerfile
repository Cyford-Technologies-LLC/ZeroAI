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
    curl gnupg gosu git nano \
    && rm -rf /var/lib/apt/lists/*
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg
RUN echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
RUN apt-get update && apt-get install -y --no-install-recommends docker-ce-cli docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*


# Copy virtual environment and application code from the builder stage
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

# Use the entrypoint script to run the final command
ENTRYPOINT ["docker-entrypoint.sh"]

# Install PHP and Apache
RUN apt-get update && apt-get install -y apache2 php libapache2-mod-php php-sqlite3 \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

# Configure Apache
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# The CMD is the command that gets executed by 'gosu' inside the entrypoint
CMD ["apache2ctl", "-D", "FOREGROUND"]
