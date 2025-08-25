#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status.

# --- SCRIPT CONFIGURATION ---
PROJECT_ROOT="/opt/ZeroAI"
PYTHON_VERSION="python3.11"
SERVICE_NAME="zeroai"
API_FILE_PATH="$PROJECT_ROOT/API/api.py"
SERVICE_FILE_PATH="/etc/systemd/system/${SERVICE_NAME}.service"

# --- HELPER FUNCTIONS ---
# Function to detect the OS and set the package manager
detect_os() {
    if grep -q "debian" /etc/os-release; then
        OS="debian"
        PM="apt-get"
    elif grep -q "rocky" /etc/os-release; then
        OS="rocky"
        PM="dnf"
    else
        echo "Unsupported OS."
        exit 1
    fi
    echo "Detected OS: $OS with package manager: $PM"
}

# Function to update packages
update_packages() {
    echo "Updating package list..."
    if [[ "$OS" == "debian" ]]; then
        sudo $PM update
    elif [[ "$OS" == "rocky" ]]; then
        sudo $PM update -y
    fi
}

# --- MAIN EXECUTION FLOW ---
echo "Starting ZeroAI API setup script..."
detect_os
update_packages

# 1. Install necessary dependencies based on OS
echo "Installing core dependencies..."
if [[ "$OS" == "debian" ]]; then
    sudo $PM install -y python3-pip python3.11
elif [[ "$OS" == "rocky" ]]; then
    # Enable CRB repo for newer python on Rocky
    sudo dnf config-manager --set-enabled crb
    sudo $PM install -y python3.11 python3.11-pip
fi

# 2. Install Python packages and overwrite API file
echo "Installing API dependencies and fixing import..."
cd "$PROJECT_ROOT"
# Install required packages
sudo "$PYTHON_VERSION" -m pip install fastapi uvicorn "uvicorn[standard]" pysqlite3-binary

# Create a temporary file with the correct API code
cat > temp_api_fix.py << EOF
import os
import sys

# Ensure the project root is in the path
project_root = os.path.dirname(os.path.abspath(__file__))
sys.path.append(os.path.join(project_root, 'src'))

# Overwrite the API file with the correct import
with open('$API_FILE_PATH', 'w') as f:
    f.write('''
import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any

from ai_crew import LatestAiDevelopmentCrew

app = FastAPI(
    title="CrewAI Endpoint API",
    description="API to expose CrewAI crews as endpoints.",
    version="1.0.0",
)

class CrewRequest(BaseModel):
    inputs: Dict[str, Any]

@app.post("/run_crew_ai/")
def run_crew_ai(request: CrewRequest):
    try:
        crew_instance = LatestAiDevelopmentCrew().crew()
        result = crew_instance.kickoff(inputs=request.inputs)
        return {"result": result}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run("API.api:app", host="0.0.0.0", port=3939)
''')
EOF
sudo "$PYTHON_VERSION" temp_api_fix.py
rm temp_api_fix.py

# 3. Create a systemd service file
echo "Creating systemd service file..."
cat > "$SERVICE_FILE_PATH" << EOF
[Unit]
Description=ZeroAI FastAPI Service
After=network.target

[Service]
User=root
WorkingDirectory=$PROJECT_ROOT
ExecStart=$PYTHON_VERSION -m uvicorn API.api:app --host 0.0.0.0 --port 3939 --workers 1 --app-dir $PROJECT_ROOT
Restart=always

[Install]
WantedBy=multi-user.target
EOF

# 4. Enable and start the service
echo "Enabling and starting service..."
sudo systemctl daemon-reload
sudo systemctl enable "$SERVICE_NAME"
sudo systemctl start "$SERVICE_NAME"
sudo systemctl status "$SERVICE_NAME"

echo "Setup complete. The ZeroAI API should now be running in the background."
