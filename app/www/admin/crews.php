<!DOCTYPE html>
<html>
<head>
    <title>Crew Management - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .crew-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
        .sequential { border-left: 4px solid #28a745; }
        .hierarchical { border-left: 4px solid #007bff; }
        button { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-execute { background: #007bff; }
        input, select, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Crew Management</h1>
        <div class="nav">
            <a href="/admin/dashboard">Dashboard</a>
            <a href="/admin/agents">Agents</a>
            <a href="/admin/crews">Crews</a>
            <a href="/admin/tasks">Tasks</a>
            <a href="/admin/knowledge">Knowledge</a>
            <a href="/admin/monitoring">Monitoring</a>
            <a href="/admin/settings">Settings</a>
            <a href="/admin/logout">Logout</a>
        </div>
    </div>
    
    <div class="card">
        <h3>Create New Crew</h3>
        <form method="POST">
            <input type="text" name="name" placeholder="Crew Name" required>
            <textarea name="description" placeholder="Crew Description" rows="2" required></textarea>
            <select name="process_type">
                <option value="sequential">Sequential Process</option>
                <option value="hierarchical">Hierarchical Process</option>
            </select>
            <button type="submit" name="action" value="create_crew">Create Crew</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Active Crews</h3>
        <div class="crew-item sequential">
            <strong>Development Crew</strong> - Sequential<br>
            <small>Core development team for coding tasks</small><br>
            <button class="btn-execute">Execute Task</button>
            <button>Configure</button>
        </div>
        
        <div class="crew-item hierarchical">
            <strong>Research Crew</strong> - Hierarchical<br>
            <small>Research and analysis team</small><br>
            <button class="btn-execute">Execute Task</button>
            <button>Configure</button>
        </div>
    </div>
</body>
</html>