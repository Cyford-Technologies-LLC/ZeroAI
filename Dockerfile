# Use a non-root user for security
# python:3.11-slim is based on debian:trixie
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

# Use a virtual environment to isolate dependencies
RUN python -m venv /opt/venv
# Activate the virtual environment
ENV PATH="/opt/venv/bin:$PATH"
# Install dependencies into the virtual environment
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY . .

# Use Gunicorn as a process manager for Uvicorn in production
# This is a robust way to run a production-ready application
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]

# Note: The `cat /app/src/ai_crew.py` command is a debug command and has been removed.
# If you need to debug, it's better to do it during development rather than baking it into the final image.
