#!/usr/bin/env python3
"""Simple ZeroAI Portal - Single Python script with embedded HTML"""

from http.server import HTTPServer, SimpleHTTPRequestHandler
import json
import os

class ZeroAIHandler(SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/' or self.path == '/admin':
            self.send_html(self.admin_login_html())
        elif self.path == '/web':
            self.send_html(self.web_login_html())
        elif self.path == '/dashboard':
            self.send_html(self.dashboard_html())
        elif self.path == '/users':
            self.send_html(self.users_html())
        else:
            self.send_response(404)
            self.end_headers()
    
    def send_html(self, html):
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        self.wfile.write(html.encode())
    
    def admin_login_html(self):
        return """<!DOCTYPE html>
<html><head><title>Admin Login</title></head>
<body>
<h2>ZeroAI Admin Login</h2>
<form onsubmit="login(event)">
<input type="text" id="user" placeholder="Username" required>
<input type="password" id="pass" placeholder="Password" required>
<button type="submit">Login</button>
</form>
<script>
function login(e) {
    e.preventDefault();
    const u = document.getElementById('user').value;
    const p = document.getElementById('pass').value;
    if (u === 'admin' && p === 'admin123') {
        localStorage.setItem('auth', 'true');
        window.location.href = '/dashboard';
    } else alert('Invalid credentials');
}
</script>
</body></html>"""
    
    def web_login_html(self):
        return """<!DOCTYPE html>
<html><head><title>Web Login</title></head>
<body>
<h2>ZeroAI Web Portal</h2>
<form onsubmit="login(event)">
<input type="text" id="user" placeholder="Username" required>
<input type="password" id="pass" placeholder="Password" required>
<button type="submit">Login</button>
</form>
<script>
function login(e) {
    e.preventDefault();
    const u = document.getElementById('user').value;
    const p = document.getElementById('pass').value;
    if (u === 'user' && p === 'user123') {
        localStorage.setItem('webauth', 'true');
        alert('Web portal access granted');
    } else alert('Invalid credentials');
}
</script>
</body></html>"""
    
    def dashboard_html(self):
        return """<!DOCTYPE html>
<html><head><title>Dashboard</title></head>
<body>
<h1>ZeroAI Admin Dashboard</h1>
<nav>
<a href="/users">Users</a> | 
<a href="/agents">Agents</a> | 
<button onclick="logout()">Logout</button>
</nav>
<p>Welcome to ZeroAI Admin Portal</p>
<script>
if (!localStorage.getItem('auth')) window.location.href = '/admin';
function logout() { localStorage.removeItem('auth'); window.location.href = '/admin'; }
</script>
</body></html>"""
    
    def users_html(self):
        return """<!DOCTYPE html>
<html><head><title>Users</title></head>
<body>
<h1>User Management</h1>
<button onclick="addUser()">Add User</button>
<div id="users">Loading users...</div>
<script>
if (!localStorage.getItem('auth')) window.location.href = '/admin';
function addUser() { alert('Add user functionality'); }
</script>
</body></html>"""

if __name__ == '__main__':
    server = HTTPServer(('0.0.0.0', 333), ZeroAIHandler)
    print("ZeroAI Portal running on http://localhost:333")
    print("Admin: /admin (admin/admin123)")
    print("Web: /web (user/user123)")
    server.serve_forever()