# Use a non-root user for security
FROM python:3.11-slim

# Install dependencies for downloading Docker Compose, build essentials, and your utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    nano \
    git \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# --- Docker Compose Plugin Installation ---
# Add Docker's official GPG key
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg

# Add the Docker repository to Apt sources
RUN echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Compose plugin
RUN apt-get update && apt-get install -y --no-install-recommends docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*
# --- End Docker Compose Plugin Installation ---


# --- Non-root User and Environment Setup ---
# Add a non-root user and create their home directory
# The -m flag is crucial for creating the /home/appuser directory
RUN groupadd -r appuser && useradd --no-log-init -r -m -g appuser appuser

# Set the working directory
WORKDIR /app


# Copy the entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
# Make the script executable
RUN chmod +x /usr/local/bin/docker-entrypoint.sh



# Temporarily switch to root to create the virtual environment
USER root
RUN python -m venv /opt/venv
# Grant ownership of the virtual environment to the non-root user
RUN chown -R appuser:appuser /opt/venv
# Switch back to the non-root user for subsequent commands
USER appuser
# --- End Non-root User and Environment Setup ---


# Install dependencies, leveraging build cache
COPY requirements.txt .

# Activate the virtual environment
ENV PATH="/opt/venv/bin:$PATH"

# Install dependencies into the virtual environment
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY . .


# Use the entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]


# Use Gunicorn as a process manager for Uvicorn in production
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]
