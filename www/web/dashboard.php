<?php
require_once __DIR__ . '/../src/bootstrap_secure.php';
use ZeroAI\Core\InputValidator;
use ZeroAI\Core\AuthorizationException;

if (!isset($_SESSION['frontend_logged_in']) || !$_SESSION['frontend_logged_in']) {
    throw new AuthorizationException('Frontend authentication required');
}

// Sanitize session data
$username = InputValidator::sanitizeForOutput($_SESSION['frontend_user'] ?? 'Unknown');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Frontend Dashboard - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #28a745; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌐 Frontend Dashboard</h1>
        <div>
            <span>Welcome, <?= $username ?>!</span>
            <a href="/frontend_logout.php" class="btn btn-danger" style="margin-left: 15px;">Logout</a>
        </div>
    </div>

    <div class="card">
        <h2>Frontend User Portal</h2>
        <p>Welcome to the ZeroAI frontend user portal. You have access to public-facing features.</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
            <div style="border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center;">
                <h4 style="margin: 0 0 10px 0; color: #007bff;">📊 Analytics</h4>
                <p style="margin: 0; color: #666;">View system analytics</p>
            </div>
            
            <div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; text-align: center;">
                <h4 style="margin: 0 0 10px 0; color: #28a745;">💬 Public Chat</h4>
                <p style="margin: 0; color: #666;">Access public AI chat</p>
            </div>
            
            <div style="border: 2px solid #ffc107; border-radius: 8px; padding: 20px; text-align: center;">
                <h4 style="margin: 0 0 10px 0; color: #f57c00;">📈 Reports</h4>
                <p style="margin: 0; color: #666;">Generate reports</p>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>User Information</h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <strong>Username:</strong> <?= $username ?>
            </div>
            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <strong>Role:</strong> Frontend User
            </div>
            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <strong>Access Level:</strong> Public Features Only
            </div>
            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <strong>Session:</strong> Active
            </div>
        </div>
    </div>
</body>
</html>