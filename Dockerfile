# Use a non-root user for security
FROM python:3.11-slim

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    nano \
    git \
    gnupg \
    gosu \
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
# --- End Docker CLI and Compose Plugin Installation ---


# --- Non-root User and Environment Setup ---
# Add a non-root user and create their home directory
RUN groupadd -r appuser && useradd --no-log-init -r -m -g appuser appuser

# Add /usr/bin to the appuser's PATH
ENV PATH="/usr/bin:$PATH"

# Set the working directory
WORKDIR /app

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Install dependencies, leveraging build cache
COPY requirements.txt .

# Use a virtual environment to isolate dependencies
RUN python -m venv /opt/venv
# Grant ownership of the virtual environment to the non-root user
RUN chown -R appuser:appuser /opt/venv

# Activate the virtual environment
ENV PATH="/opt/venv/bin:$PATH"

# Switch to the non-root user
USER appuser

# Install dependencies into the virtual environment
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY --chown=appuser:appuser . .
# --- End Non-root User and Environment Setup ---

# Use the entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]

# Use Gunicorn as a process manager for Uvicorn in production
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]
