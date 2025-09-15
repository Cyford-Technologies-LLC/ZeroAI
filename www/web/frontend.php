<!DOCTYPE html>
<html>
<head>
    <title>ZeroAI Frontend Portal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #28a745; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #007bff; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ZeroAI Frontend Portal</h1>
        <p>Welcome, <?= $_SESSION['web_user'] ?>!</p>
        <div class="nav">
            <a href="/web/frontend">Dashboard</a>
            <a href="/web/logout">Logout</a>
        </div>
    </div>
    
    <div class="grid">
        <div class="card">
            <h3>Project Dashboard</h3>
            <p>Status: Active</p>
            <p>User: <?= $_SESSION['web_user'] ?></p>
            <button class="btn-primary">Manage Projects</button>
        </div>
        
        <div class="card">
            <h3>AI Crew Management</h3>
            <p>Available Crews: 3</p>
            <button class="btn-primary">Execute Task</button>
        </div>
        
        <div class="card">
            <h3>Task History</h3>
            <p>Recent Tasks: 0</p>
            <button class="btn-primary">View History</button>
        </div>
    </div>
</body>
</html>

