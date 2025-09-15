<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    session_start();
} catch (Exception $e) {
    die("Session error: " . $e->getMessage());
}

// Simple authentication check
if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZeroAI CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; margin-bottom: 20px; }
        .nav { display: flex; gap: 20px; margin-top: 10px; }
        .nav a { color: white; text-decoration: none; padding: 5px 10px; border-radius: 3px; }
        .nav a:hover { background: rgba(255,255,255,0.2); }
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #3498db; color: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 3px; margin: 5px; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏢 ZeroAI CRM</h1>
        <div class="nav">
            <a href="/web/">Dashboard</a>
            <a href="/web/companies.php">Companies</a>
            <a href="/web/contacts.php">Contacts</a>
            <a href="/web/projects.php">Projects</a>
            <a href="/web/tasks.php">Tasks</a>
            <a href="/web/logout.php">Logout (<?= htmlspecialchars($currentUser) ?>)</a>
        </div>
    </div>

    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div>Companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div>Contacts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div>Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div>Tasks</div>
            </div>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <a href="/web/companies.php" class="btn">Manage Companies</a>
            <a href="/web/contacts.php" class="btn">Manage Contacts</a>
            <a href="/web/projects.php" class="btn">Manage Projects</a>
            <a href="/web/tasks.php" class="btn">Manage Tasks</a>
            <a href="/web/setup_crm.php" class="btn btn-success">Setup Database</a>
        </div>

        <div class="card">
            <h3>Welcome to ZeroAI CRM</h3>
            <p>Your customer relationship management system is ready. Use the navigation above to manage your business data.</p>
            <p><strong>Logged in as:</strong> <?= htmlspecialchars($currentUser) ?></p>
        </div>
    </div>
</body>
</html>