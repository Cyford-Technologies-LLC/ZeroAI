<!DOCTYPE html>
<html>
<head>
    <title>Knowledge Management - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .knowledge-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
        .document { border-left: 4px solid #17a2b8; }
        .guide { border-left: 4px solid #28a745; }
        .url { border-left: 4px solid #ffc107; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        input, select, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        .access-controls { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Knowledge Management</h1>
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
        <h3>Add Knowledge</h3>
        <form method="POST">
            <input type="text" name="title" placeholder="Knowledge Title" required>
            <select name="type">
                <option value="document">Document</option>
                <option value="guide">Guide</option>
                <option value="url">URL/Link</option>
                <option value="api">API Documentation</option>
            </select>
            <textarea name="content" placeholder="Content or URL" rows="4" required></textarea>
            
            <h4>Access Controls</h4>
            <div class="access-controls">
                <div>
                    <label>Access Level:</label>
                    <select name="access_level">
                        <option value="all">All Agents</option>
                        <option value="crew">Specific Crews</option>
                        <option value="agent">Specific Agents</option>
                    </select>
                </div>
                <div>
                    <label>Agent Access:</label>
                    <select name="agent_access" multiple>
                        <option value="1">Team Manager</option>
                        <option value="2">Project Manager</option>
                        <option value="3">Prompt Refinement Agent</option>
                    </select>
                </div>
                <div>
                    <label>Crew Access:</label>
                    <select name="crew_access" multiple>
                        <option value="1">Development Crew</option>
                        <option value="2">Research Crew</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="action" value="add_knowledge" class="btn-success">Add Knowledge</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Knowledge Base</h3>
        
        <div class="knowledge-item document">
            <strong>ZeroAI Overview</strong> - Document<br>
            <small>ZeroAI is a zero-cost AI workforce platform that runs entirely on your hardware.</small><br>
            <small>Access: All Agents | Created: System</small><br>
            <button>Edit</button>
            <button class="btn-warning">Configure Access</button>
        </div>
        
        <div class="knowledge-item guide">
            <strong>CrewAI Best Practices</strong> - Guide<br>
            <small>Guidelines for creating effective AI crews and task management.</small><br>
            <small>Access: All Agents | Created: System</small><br>
            <button>Edit</button>
            <button class="btn-warning">Configure Access</button>
        </div>
        
        <div class="knowledge-item url">
            <strong>CrewAI Documentation</strong> - URL<br>
            <small>https://docs.crewai.com/</small><br>
            <small>Access: Development Crew | Created: Admin</small><br>
            <button>Edit</button>
            <button class="btn-warning">Configure Access</button>
        </div>
    </div>
</body>
</html>