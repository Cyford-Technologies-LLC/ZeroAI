# Use a non-root user for security
FROM python:3.11-slim

# Set the working directory
WORKDIR /app

# Install dependencies, leveraging build cache
COPY requirements.txt .

RUN apt-get update && apt-get install -y curl
# Use a virtual environment to isolate dependencies
RUN python -m venv /opt/venv
# Activate the virtual environment
ENV PATH="/opt/venv/bin:$PATH"
# Install dependencies into the virtual environment
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY . .


RUN cat /app/src/ai_crew.py

# Use Gunicorn as a process manager for Uvicorn in production
# This is a robust way to run a production-ready application
CMD ["gunicorn", "API.api:app", "--bind", "0.0.0.0:3939", "--worker-class", "uvicorn.workers.UvicornWorker", "--workers", "2", "--preload"]

# Alternatively, for development, you can stick with the simpler Uvicorn command
# CMD ["python3.11", "-m", "uvicorn", "API.api:app", "--host", "0.0.0.0", "--port", "3939"]
