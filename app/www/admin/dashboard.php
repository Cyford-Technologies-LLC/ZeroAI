<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ZeroAI Admin Dashboard</h1>
        <p>Welcome, <?= $_SESSION['admin_user'] ?>!</p>
        <div class="nav">
            <a href="/admin/dashboard">Dashboard</a>
            <a href="/admin/agents">Agents</a>
            <a href="/admin/crews">Crews</a>
            <a href="/admin/tasks">Tasks</a>
            <a href="/admin/knowledge">Knowledge</a>
            <a href="/admin/monitoring">Monitoring</a>
            <a href="/admin/users">Users</a>
            <a href="/admin/settings">Settings</a>
            <a href="/admin/logout">Logout</a>
        </div>
    </div>
    
    <div class="card">
        <h3>System Status</h3>
        <p>🟢 Database: Connected</p>
        <p>🟢 Portal: Active</p>
        <p>👤 User: <?= $_SESSION['admin_user'] ?></p>
    </div>
</body>
</html>