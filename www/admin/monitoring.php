<!DOCTYPE html>
<html>
<head>
    <title>System Monitoring - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .metric { background: white; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #007bff; }
        .metric.warning { border-left-color: #ffc107; }
        .metric.danger { border-left-color: #dc3545; }
        .metric.success { border-left-color: #28a745; }
        .log-item { padding: 10px; border-bottom: 1px solid #eee; font-family: monospace; font-size: 12px; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .success { color: #28a745; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .chart-placeholder { height: 200px; background: #f8f9fa; border: 1px dashed #dee2e6; display: flex; align-items: center; justify-content: center; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Monitoring</h1>
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
    
    <div class="metrics">
        <div class="metric success">
            <h3>98.5%</h3>
            <p>System Uptime</p>
        </div>
        <div class="metric">
            <h3>1,247</h3>
            <p>Total Tokens Used</p>
        </div>
        <div class="metric warning">
            <h3>2.3GB</h3>
            <p>Memory Usage</p>
        </div>
        <div class="metric">
            <h3>15.2s</h3>
            <p>Avg Response Time</p>
        </div>
        <div class="metric success">
            <h3>3</h3>
            <p>Active Agents</p>
        </div>
        <div class="metric">
            <h3>2</h3>
            <p>Running Crews</p>
        </div>
    </div>
    
    <div class="card">
        <h3>Performance Charts</h3>
        <div class="chart-placeholder">
            Token Usage Over Time (Chart placeholder - integrate with Chart.js)
        </div>
    </div>
    
    <div class="card">
        <h3>Agent Learning Progress</h3>
        <div style="margin-bottom: 15px;">
            <strong>Team Manager:</strong> 
            <div style="background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;">
                <div style="background: #28a745; height: 100%; width: 75%; border-radius: 10px;"></div>
            </div>
            <small>Learning Progress: 75% | Strengths: Task coordination | Weaknesses: Resource estimation</small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>Project Manager:</strong>
            <div style="background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;">
                <div style="background: #007bff; height: 100%; width: 60%; border-radius: 10px;"></div>
            </div>
            <small>Learning Progress: 60% | Strengths: Planning | Weaknesses: Risk assessment</small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>Prompt Refinement Agent:</strong>
            <div style="background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;">
                <div style="background: #ffc107; height: 100%; width: 45%; border-radius: 10px;"></div>
            </div>
            <small>Learning Progress: 45% | Strengths: Prompt analysis | Weaknesses: Context understanding</small>
        </div>
    </div>
    
    <div class="card">
        <h3>System Logs</h3>
        <button>Clear Logs</button>
        <button>Export Logs</button>
        <button>Refresh</button>
        
        <div style="max-height: 300px; overflow-y: auto; margin-top: 10px;">
            <div class="log-item success">[2025-09-09 02:58:48] INFO: PHP-FPM started successfully</div>
            <div class="log-item info">[2025-09-09 02:58:47] INFO: Nginx configuration loaded</div>
            <div class="log-item success">[2025-09-09 02:58:45] INFO: Database connection established</div>
            <div class="log-item info">[2025-09-09 02:58:44] INFO: ZeroAI portal initialized</div>
            <div class="log-item warning">[2025-09-09 02:55:30] WARN: High memory usage detected</div>
            <div class="log-item error">[2025-09-09 02:53:50] ERROR: Task execution failed - Agent timeout</div>
            <div class="log-item info">[2025-09-09 02:50:15] INFO: New task created: Analyze project requirements</div>
        </div>
    </div>
</body>
</html>