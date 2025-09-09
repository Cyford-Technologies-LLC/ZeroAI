<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?? 'ZeroAI Admin' ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; transition: background 0.3s; }
        .nav a:hover { background: rgba(255,255,255,0.1); }
        .nav a.active { background: rgba(255,255,255,0.2); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .main-content { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; }
        input, select, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">ZeroAI Admin</div>
            <nav class="nav">
                <a href="/admin/dashboard" <?= ($currentPage ?? '') === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
                <a href="/admin/agents" <?= ($currentPage ?? '') === 'agents' ? 'class="active"' : '' ?>>Agents</a>
                <a href="/admin/crews" <?= ($currentPage ?? '') === 'crews' ? 'class="active"' : '' ?>>Crews</a>
                <a href="/admin/tasks" <?= ($currentPage ?? '') === 'tasks' ? 'class="active"' : '' ?>>Tasks</a>
                <a href="/admin/knowledge" <?= ($currentPage ?? '') === 'knowledge' ? 'class="active"' : '' ?>>Knowledge</a>
                <a href="/admin/monitoring" <?= ($currentPage ?? '') === 'monitoring' ? 'class="active"' : '' ?>>Monitoring</a>
                <a href="/admin/users" <?= ($currentPage ?? '') === 'users' ? 'class="active"' : '' ?>>Users</a>
                <a href="/admin/settings" <?= ($currentPage ?? '') === 'settings' ? 'class="active"' : '' ?>>Settings</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= $_SESSION['admin_user'] ?? 'Admin' ?>!</span>
                <a href="/admin/logout" style="background: #dc3545; padding: 6px 12px; border-radius: 4px;">Logout</a>
            </div>
        </div>
    </div>
    <div class="main-content">