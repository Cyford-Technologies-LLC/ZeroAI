"""Portal API for ZeroAI web interface."""

from fastapi import FastAPI, HTTPException
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

@app.get("/admin")
async def admin_dashboard():
    return HTMLResponse(open("www/admin/dashboard.html").read())

@app.get("/agents")
async def get_agents():
    """Get all agents from database."""
    conn = get_db_connection()
    cursor = conn.execute("SELECT * FROM agents")
    agents = [dict(zip([col[0] for col in cursor.description], row)) for row in cursor.fetchall()]
    conn.close()
    return {"agents": agents}

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
    # TODO: Implement proper authentication
    if username == "admin" and password == "admin":
        return {"success": True, "token": "temp_token"}
    raise HTTPException(status_code=401, detail="Invalid credentials")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=333)