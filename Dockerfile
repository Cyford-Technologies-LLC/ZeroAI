# Stage 1: Build dependencies as root
FROM python:3.11-slim as builder

# Install system dependencies, including gosu
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    nano \
    git \
    gnupg \
    gosu \
    php-cli \
    php-zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Add Docker's official GPG key and repository for installing the client
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg
RUN echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker CLI and Compose plugin
RUN apt-get update && apt-get install -y --no-install-recommends \
    docker-ce-cli \
    docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*
RUN ln -s /usr/bin/docker /usr/local/bin/docker

# Install PHP Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Use a virtual environment to isolate dependencies
WORKDIR /app
COPY requirements.txt .
RUN python -m venv /app/venv && \
    /app/venv/bin/pip install --no-cache-dir -r requirements.txt


# --- Stage 2: Final image ---
FROM python:3.11-slim

# Copy gosu and virtual environment from the builder stage
COPY --from=builder /usr/bin/gosu /usr/local/bin/gosu  # Correct path for gosu
COPY --from=builder /app /app

# Add virtual environment's bin to PATH
ENV PATH="/app/venv/bin:$PATH"

# Copy entrypoint script and make it executable
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# All setup done. The container MUST start as root for the entrypoint script
# to be able to create the user and group based on host UID/GID.
USER root

# Use the entrypoint script to run the final command
ENTRYPOINT ["docker-entrypoint.sh"]

# The CMD is the command that gets executed by 'gosu' inside the entrypoint
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]
