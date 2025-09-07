# Use a non-root user for security
FROM python:3.11-slim

# Install dependencies for downloading and verifying Docker Compose
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# Add Docker's official GPG key
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg

# Add the Docker repository to Apt sources
RUN echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian trixie stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Compose plugin
RUN apt-get update && apt-get install -y --no-install-recommends docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*

# Add a non-root user to adhere to security best practices.
RUN groupadd -r appuser && useradd --no-log-init -r -g appuser appuser

# Set the working directory
WORKDIR /app

# Switch to the non-root user
USER appuser

# Install dependencies, leveraging build cache
COPY requirements.txt .

# --- FIX: Move virtual environment creation here, before switching users ---
# You need to be root to create the virtual environment in /opt
# Reverting to root temporarily for venv creation
USER root
RUN python -m venv /opt/venv
# Grant ownership of the virtual environment to the non-root user
RUN chown -R appuser:appuser /opt/venv
# Switch back to the non-root user
USER appuser
# --- END FIX ---

# Activate the virtual environment
ENV PATH="/opt/venv/bin:$PATH"
# Install dependencies into the virtual environment
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY . .

# Use Gunicorn as a process manager for Uvicorn in production
# This is a robust way to run a production-ready application
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]
