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


# --- Stage 2: Final image with correct user and permissions ---
FROM python:3.11-slim

# Copy the gosu binary from the builder stage
COPY --from=builder /usr/local/bin/gosu /usr/local/bin/gosu

# Copy necessary files from the builder stage
COPY --from=builder /usr/local/bin/docker* /usr/local/bin/
COPY --from=builder /app /app

# Create a non-root user with UID 1000 and GID 1000
RUN groupadd -r appuser -g 1000 && useradd --no-log-init -r -m -u 1000 -g 1000 appuser

# Set the PATH to include the virtual environment's bin directory
ENV PATH="/app/venv/bin:$PATH"

# Switch to the non-root user
USER appuser

# Set entrypoint and command
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]

