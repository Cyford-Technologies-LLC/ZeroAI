#!/bin/bash
set -e

PROJECT_ROOT="/opt/ZeroAI"
PYTHON_VERSION="python3.11"
SERVICE_NAME="zeroai"
API_FILE_PATH="$PROJECT_ROOT/API/api.py"
SERVICE_FILE_PATH="/etc/systemd/system/${SERVICE_NAME}.service"
AI_CREW_FILE_PATH="$PROJECT_ROOT/src/ai_crew.py"

# --- HELPER FUNCTIONS ---
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
}

# Overwrite the ai_crew.py file
fix_ai_crew_file() {
    echo "Overwriting ai_crew.py to ensure the correct class exists."
    cat > "$AI_CREW_FILE_PATH" << EOF
from crewai import Agent, Task, Crew, Process

class LatestAiDevelopmentCrew:
    def crew(self):
        researcher = Agent(
            role="Senior Researcher",
            goal="Identify the latest AI trends and developments",
            backstory="A seasoned professional who is an expert in AI."
        )

        writer = Agent(
            role="AI Content Writer",
            goal="Compose compelling and informative content about AI.",
            backstory="A creative writer with a passion for explaining AI topics."
        )

        research_task = Task(
            description="Investigate the most recent advancements in AI technology.",
            agent=researcher,
            expected_output="A list of 5-10 key AI developments from the past month."
        )

        writing_task = Task(
            description="Write a concise blog post based on the research.",
            agent=writer,
            expected_output="A 500-word blog post in markdown format."
        )

        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            process=Process.sequential,
        )

if __name__ == '__main__':
    print("Running a test of the CrewAI crew...")
    crew = LatestAiDevelopmentCrew().crew()
    result = crew.kickoff(inputs={'topic': 'latest AI developments'})
    print("Crew run complete.")
EOF
}

# Overwrite the API file
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

# Main execution flow
echo "Starting ZeroAI API setup script..."
detect_os

echo "Installing Python packages..."
cd "$PROJECT_ROOT"
sudo "$PYTHON_VERSION" -m pip install --upgrade pip
sudo "$PYTHON_VERSION" -m pip install fastapi uvicorn "uvicorn[standard]" pysqlite3-binary

fix_ai_crew_file
fix_api_file

echo "Managing systemd service..."
sudo systemctl stop "$SERVICE_NAME" || true
sudo systemctl disable "$SERVICE_NAME" || true

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

echo "Setup complete. The ZeroAI API should now be running in the background."
