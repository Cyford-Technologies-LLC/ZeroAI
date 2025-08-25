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
        echo "Unsupported OS. The script requires Debian or Rocky Linux."
        exit 1
    fi
    echo "Detected OS: $OS with package manager: $PM"
}

# Function to run the correct package manager command
install_packages() {
    echo "Installing core dependencies..."
    if [[ "$OS" == "debian" ]]; then
        sudo $PM update -y
        sudo $PM install -y "$PYTHON_VERSION" "$PYTHON_VERSION"-pip
    elif [[ "$OS" == "rocky" ]]; then
        sudo $PM update -y
        sudo dnf config-manager --set-enabled crb || true # CRB might be enabled already, allow it to fail
        sudo $PM install -y "$PYTHON_VERSION" "$PYTHON_VERSION"-pip
    fi
}

# Function to fix the import in the API file
fix_api_file() {
    echo "Fixing import in API file..."
    cat > "$API_FILE_PATH" << EOF
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
EOF
}

# Function to create and manage the service
manage_service() {
    echo "Managing systemd service..."
    # Stop and disable if it exists to ensure a clean state
    if sudo systemctl is-enabled --quiet "$SERVICE_NAME"; then
        sudo systemctl stop "$SERVICE_NAME" || true
        sudo systemctl disable "$SERVICE_NAME" || true
    fi

    # Create the service file
    cat > "$SERVICE_FILE_PATH" << EOF
[Unit]
Description=ZeroAI FastAPI Service
After=network.target

[Service]
User=root
WorkingDirectory=$PROJECT_ROOT
ExecStart=$PYTHON_VERSION -m uvicorn API.api:app --host 0.0.0.0 --port 3939 --workers 1
Restart=always
Environment="PYTHONPATH=$PROJECT_ROOT/src"

[Install]
WantedBy=multi-user.target
EOF

    sudo systemctl daemon-reload
    sudo systemctl enable "$SERVICE_NAME"
    sudo systemctl start "$SERVICE_NAME"
    sudo systemctl status "$SERVICE_NAME" --no-pager
}

# --- MAIN EXECUTION FLOW ---
echo "Starting ZeroAI API setup script..."
detect_os
install_packages

echo "Installing Python packages..."
cd "$PROJECT_ROOT"
sudo "$PYTHON_VERSION" -m pip install --upgrade pip
sudo "$PYTHON_VERSION" -m pip install fastapi uvicorn "uvicorn[standard]" pysqlite3-binary

fix_api_file
manage_service

echo "Setup complete. The ZeroAI API should now be running in the background."

