<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav { margin-top: 10px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ZeroAI Admin Dashboard</h1>
        <p>Welcome, <?= $_SESSION['admin_user'] ?>!</p>
        <div class="nav">
            <a href="/admin/dashboard">Dashboard</a>
            <a href="/admin/users">Users</a>
            <a href="/admin/agents">Agents</a>
            <a href="/web/frontend">Frontend</a>
            <a href="/admin/logout.php" class="btn-danger" style="padding: 5px 10px; border-radius: 3px;">Logout</a>
        </div>
    </div>
    
    <div class="grid">
        <div class="card">
            <h3>System Status</h3>
            <p>🟢 Database: Connected</p>
            <p>🟢 AI Agents: Active</p>
            <p>👤 Logged in as: <?= $_SESSION['admin_user'] ?></p>
        </div>
        
        <div class="card">
            <h3>Quick Actions</h3>
            <button onclick="location.href='/admin/users'">Manage Users</button>
            <button onclick="location.href='/admin/agents'">Manage Agents</button>
            <button onclick="location.href='/web/frontend'">Frontend Portal</button>
        </div>
    </div>
</body>
</html>