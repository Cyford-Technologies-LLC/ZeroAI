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