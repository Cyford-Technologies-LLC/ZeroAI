<?php
session_start();

// Check if user is logged in (from admin session)
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    // Redirect to login
    header('Location: /admin/login.php?redirect=/web');
    exit;
}

$pageTitle = 'ZeroAI User Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem 0; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav a { color: white; text-decoration: none; margin: 0 15px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 10px 10px 0; }
        .btn:hover { background: #0056b3; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 30px; }
        .feature-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; }
        .feature-icon { font-size: 3rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">💰 ZeroAI Portal</div>
            <nav class="nav">
                <a href="/web">Dashboard</a>
                <a href="/web/ai_center.php">AI Center</a>
                <a href="/admin/">Admin</a>
                <a href="/admin/logout.php">Logout</a>
            </nav>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h1>Welcome to ZeroAI User Portal</h1>
            <p>Manage your AI workforce and projects from this central dashboard.</p>
            
            <div>
                <a href="/web/ai_center.php" class="btn">🤖 AI Community Center</a>
                <a href="/admin/agents.php" class="btn">⚙️ Manage Agents</a>
                <a href="/admin/dashboard.php" class="btn">📊 System Dashboard</a>
            </div>
        </div>
        
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">🤖</div>
                <h3>AI Agents</h3>
                <p>Browse and assign AI agents from the community pool to your projects.</p>
                <a href="/web/ai_center.php" class="btn">Browse Agents</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Analytics</h3>
                <p>Monitor your AI workforce performance and resource usage.</p>
                <a href="/admin/dashboard.php" class="btn">View Analytics</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">⚙️</div>
                <h3>Configuration</h3>
                <p>Configure your AI agents and system settings.</p>
                <a href="/admin/agents.php" class="btn">Configure</a>
            </div>
        </div>
    </div>
</body>
</html>