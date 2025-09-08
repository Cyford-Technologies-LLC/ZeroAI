"""Portal API for ZeroAI web interface."""

from fastapi import FastAPI, HTTPException, Request
from fastapi.staticfiles import StaticFiles
from fastapi.responses import HTMLResponse
import sqlite3
import json
from pathlib import Path

app = FastAPI(title="ZeroAI Portal API")
db_path = Path("/app/data/zeroai.db")

def get_db_connection():
    return sqlite3.connect(db_path)

@app.get("/")
async def root():
    return HTMLResponse(open("/app/www/web/login.html").read())

@app.get("/admin")
async def admin_dashboard():
    return HTMLResponse(open("/app/www/admin/dashboard.html").read())

@app.get("/admin/agents")
async def admin_agents():
    return HTMLResponse(open("/app/www/admin/agents-manager.html").read())

@app.get("/admin/chat")
async def admin_chat():
    return HTMLResponse(open("/app/www/admin/chat.html").read())

@app.get("/frontend")
async def frontend():
    return HTMLResponse(open("/app/www/web/frontend.html").read())

@app.get("/admin/knowledge")
async def admin_knowledge():
    return HTMLResponse(open("/app/www/admin/knowledge.html").read())

@app.get("/admin/crews")
async def admin_crews():
    return HTMLResponse(open("/app/www/admin/crews.html").read())

@app.get("/admin/training")
async def admin_training():
    return HTMLResponse(open("/app/www/admin/training.html").read())

@app.get("/admin/backup")
async def admin_backup():
    return HTMLResponse(open("/app/www/admin/backup.html").read())

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
async def login(request: Request):
    """Basic login endpoint."""
    data = await request.json()
    username = data.get("username", "")
    password = data.get("password", "")
    if username == "admin" and password == "admin":
        return {"success": True, "token": "temp_token"}
    raise HTTPException(status_code=401, detail="Invalid credentials")

@app.post("/chat")
async def chat_with_ai(request: Request):
    """Chat with AI agents."""
    data = await request.json()
    message = data.get("message", "")
    agent = data.get("agent", "team_manager")
    
    responses = {
        "team_manager": f"Team Manager: I understand you want to '{message}'. I'll coordinate the team to handle this task.",
        "project_manager": f"Project Manager: Regarding '{message}', I'll create a project plan and track progress.",
        "prompt_refinement": f"Prompt Refinement Agent: I can help optimize '{message}' for better AI responses."
    }
    
    return {"response": responses.get(agent, "AI Agent: I received your message and will process it.")}

@app.get("/knowledge")
async def get_knowledge():
    """Get all knowledge items."""
    conn = get_db_connection()
    cursor = conn.execute("SELECT * FROM knowledge ORDER BY name")
    knowledge = [dict(zip([col[0] for col in cursor.description], row)) for row in cursor.fetchall()]
    conn.close()
    return {"knowledge": knowledge}

@app.post("/knowledge")
async def create_knowledge(request: Request):
    """Create new knowledge item."""
    data = await request.json()
    conn = get_db_connection()
    conn.execute("""
        INSERT INTO knowledge (name, content, type, access_level)
        VALUES (?, ?, ?, ?)
    """, (data["name"], data["content"], data["type"], "all"))
    conn.commit()
    conn.close()
    return {"success": True}

@app.delete("/knowledge/{knowledge_id}")
async def delete_knowledge(knowledge_id: int):
    """Delete knowledge item."""
    conn = get_db_connection()
    conn.execute("DELETE FROM knowledge WHERE id = ?", (knowledge_id,))
    conn.commit()
    conn.close()
    return {"success": True}

@app.post("/crews/process-type")
async def update_crew_process_type(request: Request):
    """Update crew process type."""
    data = await request.json()
    conn = get_db_connection()
    conn.execute("""
        INSERT OR REPLACE INTO crews (name, process_type, config)
        VALUES (?, ?, ?)
    """, (data["crew"], data["processType"], "{}"))
    conn.commit()
    conn.close()
    return {"success": True}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=333)