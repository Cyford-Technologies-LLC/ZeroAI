# Use a non-root user for security
FROM python:3.11-slim

# Install system dependencies, including gosu for user switching
# and tools for installing Docker and Composer
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

# --- Docker CLI and Compose Plugin Installation ---
# Add Docker's official GPG key
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg

# Add the Docker repository to Apt sources
RUN echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker CLI and Compose plugin
RUN apt-get update && apt-get install -y --no-install-recommends \
    docker-ce-cli \
    docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*

# Create symbolic link for docker binary to ensure it's in the PATH
RUN ln -s /usr/bin/docker /usr/local/bin/docker
# --- End Docker CLI and Compose Plugin Installation ---


# --- PHP Composer Installation ---
# Install PHP Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# --- End PHP Composer Installation ---

# --- Non-root User and Environment Setup ---
# Add a non-root user and create their home directory
# Use a consistent UID, e.g., 1000, to match the host UID
RUN groupadd -r appuser -g 1000 && useradd --no-log-init -r -m -u 1000 -g 1000 appuser

# Set the working directory
WORKDIR /app

# Copy and setup entrypoint script as root
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Install dependencies, leveraging build cache
COPY requirements.txt .

# Use a virtual environment to isolate dependencies inside the working directory
# Perform this as root, then change ownership to appuser.
RUN python -m venv /app/venv
RUN chown -R appuser:appuser /app/venv

# Activate the virtual environment
ENV PATH="/app/venv/bin:$PATH"

# --- FIX: Move COPY after user switch to ensure correct permissions ---
# Switch to the non-root user
USER appuser

# Install dependencies into the virtual environment
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY . .
# --- END FIX ---


# Set the USER to root to ensure the entrypoint script runs with correct permissions
USER root
# Use the entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]

# Use Gunicorn as a process manager for Uvicorn in production
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]
