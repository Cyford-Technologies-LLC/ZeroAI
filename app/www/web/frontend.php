<?php
session_start();
if (!isset($_SESSION['web_logged_in'])) {
    header('Location: /web');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZeroAI Frontend Portal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #28a745; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ZeroAI Frontend Portal</h1>
        <p>Welcome, <?= $_SESSION['web_user'] ?>!</p>
        <a href="/web/logout.php" style="background: #dc3545; color: white; text-decoration: none; padding: 5px 10px; border-radius: 3px;">Logout</a>
    </div>
    
    <div class="card">
        <h3>Project Dashboard</h3>
        <p>Status: Active</p>
        <p>User: <?= $_SESSION['web_user'] ?></p>
        <button>Manage Projects</button>
    </div>
</body>
</html>