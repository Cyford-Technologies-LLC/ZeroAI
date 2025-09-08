"""Portal API for ZeroAI web interface."""

from fastapi import FastAPI, HTTPException, Request
from fastapi.staticfiles import StaticFiles
from fastapi.responses import HTMLResponse
import sqlite3
import json
from pathlib import Path

app = FastAPI(title="ZeroAI Portal API")
db_path = Path("data/zeroai.db")

def get_db_connection():
    return sqlite3.connect(db_path)

@app.get("/")
async def root():
    return HTMLResponse(open("www/web/login.html").read())

@app.get("/frontend")
async def frontend():
    return HTMLResponse(open("www/web/frontend.html").read())

@app.get("/admin")
async def admin_dashboard():
    return HTMLResponse(open("www/admin/dashboard.html").read())

@app.get("/agents-manager")
async def agents_manager():
    return HTMLResponse(open("www/admin/agents-manager.html").read())

@app.get("/agents")
async def get_agents():
    """Get all agents from database."""
    conn = get_db_connection()
    cursor = conn.execute("SELECT * FROM agents")
    agents = [dict(zip([col[0] for col in cursor.description], row)) for row in cursor.fetchall()]
    conn.close()
    return {"agents": agents}

@app.get("/all-agents")
async def get_all_agents():
    """Get all agents including internal crew agents."""
    conn = get_db_connection()
    cursor = conn.execute("SELECT * FROM agents ORDER BY is_core DESC, name")
    agents = [dict(zip([col[0] for col in cursor.description], row)) for row in cursor.fetchall()]
    conn.close()
    return {"agents": agents}

@app.post("/agents")
async def create_agent(request: Request):
    """Create new agent."""
    data = await request.json()
    conn = get_db_connection()
    conn.execute("""
        INSERT INTO agents (name, role, goal, backstory, config, is_core)
        VALUES (?, ?, ?, ?, ?, 0)
    """, (data["name"], data["role"], data["goal"], data["backstory"], data.get("config", "{}")))
    conn.commit()
    conn.close()
    return {"success": True}

@app.delete("/agents/{agent_id}")
async def delete_agent(agent_id: int):
    """Delete agent (non-core only)."""
    conn = get_db_connection()
    cursor = conn.execute("SELECT is_core FROM agents WHERE id = ?", (agent_id,))
    agent = cursor.fetchone()
    if not agent or agent[0]:  # Core agent
        raise HTTPException(status_code=400, detail="Cannot delete core agent")
    conn.execute("DELETE FROM agents WHERE id = ?", (agent_id,))
    conn.commit()
    conn.close()
    return {"success": True}

@app.get("/crews")
async def get_crews():
    """Get all crews from database."""
    conn = get_db_connection()
    cursor = conn.execute("SELECT * FROM crews")
    crews = [dict(zip([col[0] for col in cursor.description], row)) for row in cursor.fetchall()]
    conn.close()
    return {"crews": crews}

@app.post("/login")
async def login(username: str, password: str):
    """Basic login endpoint."""
    if username == "admin" and password == "admin":
        return {"success": True, "token": "temp_token"}
    raise HTTPException(status_code=401, detail="Invalid credentials")

@app.get("/chat")
async def chat_page():
    """Serve chat interface."""
    return HTMLResponse(open("www/admin/chat.html").read())

@app.post("/chat")
async def chat_with_ai(request: Request):
    """Chat with AI agents."""
    data = await request.json()
    message = data.get("message", "")
    agent = data.get("agent", "team_manager")
    
    # Simple AI response simulation
    responses = {
        "team_manager": f"Team Manager: I understand you want to '{message}'. I'll coordinate the team to handle this task.",
        "project_manager": f"Project Manager: Regarding '{message}', I'll create a project plan and track progress.",
        "prompt_refinement": f"Prompt Refinement Agent: I can help optimize '{message}' for better AI responses."
    }
    
    return {"response": responses.get(agent, "AI Agent: I received your message and will process it.")}

def get_all_ips():
    import socket
    hostname = socket.gethostname()
    local_ip = socket.gethostbyname(hostname)
    return ["127.0.0.1", local_ip, "0.0.0.0"]

if __name__ == "__main__":
    import uvicorn
    import threading
    
    ips = get_all_ips()
    print("\n" + "="*50)
    print("ZeroAI Portal Server Started")
    print("="*50)
    print("Access URLs:")
    for ip in ips:
        if ip != "0.0.0.0":
            print(f"  http://{ip}:333")
    print("\nAdmin Login:")
    print("  Username: admin")
    print("  Password: admin")
    print("\nEndpoints:")
    print("  Login:     /")
    print("  Dashboard: /admin")
    print("  API:       /agents, /crews")
    print("="*50)
    
    def run_server():
        uvicorn.run(app, host="0.0.0.0", port=333, log_level="error")
    
    server_thread = threading.Thread(target=run_server, daemon=True)
    server_thread.start()
    
    try:
        server_thread.join()
    except KeyboardInterrupt:
        print("\nServer stopped.")