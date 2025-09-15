<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?? 'ZeroAI Admin' ?></title>
    <link rel="stylesheet" href="/www/assets/css/admin.css">
    <link rel="icon" type="image/x-icon" href="/www/assets/img/favicon.ico">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; display: flex; flex-direction: column; height: 100vh; }
        .header { background: #007bff; color: white; padding: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; transition: background 0.3s; }
        .nav a:hover { background: rgba(255,255,255,0.1); }
        .nav a.active { background: rgba(255,255,255,0.2); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .content-wrapper { display: flex; flex: 1; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px 0; overflow-y: auto; }
        .sidebar-group { margin-bottom: 20px; }
        .sidebar-group h3 { color: #adb5bd; font-size: 12px; text-transform: uppercase; margin: 0 20px 10px; font-weight: bold; }
        .sidebar a { display: block; color: #dee2e6; text-decoration: none; padding: 10px 20px; transition: background 0.3s; }
        .sidebar a:hover { background: #495057; }
        .sidebar a.active { background: #007bff; color: white; }
        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
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
                <a href="/admin/crewai" <?= in_array($currentPage ?? '', ['crews', 'agents', 'knowledge', 'tasks']) ? 'class="active"' : '' ?>>CrewAI</a>
                <a href="/admin/chat" <?= in_array($currentPage ?? '', ['crew_chat', 'claude', 'chat']) ? 'class="active"' : '' ?>>Chat</a>
                <a href="/admin/tools" <?= in_array($currentPage ?? '', ['monitoring', 'logs', 'performance', 'backup', 'restore', 'error_logs', 'diagnostics', 'tools']) ? 'class="active"' : '' ?>>Tools</a>
                <a href="/admin/system" <?= in_array($currentPage ?? '', ['localhost', 'peers']) ? 'class="active"' : '' ?>>System</a>
                <a href="/admin/settings" <?= ($currentPage ?? '') === 'settings' ? 'class="active"' : '' ?>>Settings</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= $_SESSION['admin_user'] ?? 'Admin' ?>!</span>
                <a href="/admin/logout" style="background: #dc3545; padding: 6px 12px; border-radius: 4px;">Logout</a>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="sidebar">
            <?php 
            $currentSection = 'dashboard';
            if (in_array($currentPage ?? '', ['crews', 'agents', 'tasks', 'knowledge'])) {
                $currentSection = 'crewai';
            } elseif (in_array($currentPage ?? '', ['crew_chat', 'claude', 'chat', 'claude_chat'])) {
                $currentSection = 'chat';
            } elseif (in_array($currentPage ?? '', ['monitoring', 'logs', 'performance', 'backup', 'restore', 'error_logs', 'diagnostics', 'tools'])) {
                $currentSection = 'tools';
            } elseif (in_array($currentPage ?? '', ['localhost', 'peers'])) {
                $currentSection = 'system';
            } elseif (in_array($currentPage ?? '', ['settings', 'config', 'cloud_settings', 'claude_settings', 'users'])) {
                $currentSection = 'settings';
            }
            ?>
            
            <?php if ($currentSection === 'tools' || ($currentPage ?? '') === 'tools'): ?>
                <div class="sidebar-group">
                    <h3>System Tools</h3>
                    <a href="/admin/monitoring" <?= ($currentPage ?? '') === 'monitoring' ? 'class="active"' : '' ?>>📊 Monitoring</a>
                    <a href="/admin/logs" <?= ($currentPage ?? '') === 'logs' ? 'class="active"' : '' ?>>📋 Logs</a>
                    <a href="/admin/performance" <?= ($currentPage ?? '') === 'performance' ? 'class="active"' : '' ?>>⚡ Performance</a>
                </div>
                <div class="sidebar-group">
                    <h3>Diagnostics</h3>
                    <a href="/admin/error_logs" <?= ($currentPage ?? '') === 'error_logs' ? 'class="active"' : '' ?>>🚨 Error Logs</a>
                    <a href="/admin/diagnostics" <?= ($currentPage ?? '') === 'diagnostics' ? 'class="active"' : '' ?>>🔍 System Diagnostics</a>
                </div>
                <div class="sidebar-group">
                    <h3>Data Management</h3>
                    <a href="/admin/backup" <?= ($currentPage ?? '') === 'backup' ? 'class="active"' : '' ?>>💾 Backup</a>
                    <a href="/admin/restore" <?= ($currentPage ?? '') === 'restore' ? 'class="active"' : '' ?>>🔄 Restore</a>
                </div>
                <div class="sidebar-group">
                    <h3>Development Tools</h3>
                    <a href="/admin/api_tester">🧪 API Tester</a>
                    <a href="/admin/database_viewer">🗄️ Database Viewer</a>
                    <a href="/admin/file_manager">📁 File Manager</a>
                </div>
            <?php else: ?>
                <div class="sidebar-group">
                    <h3>Quick Actions</h3>
                    <a href="/admin/crew_chat">💬 Start Crew Chat</a>
                    <a href="/admin/agents">🤖 View Agents</a>
                    <a href="/admin/peer_manager">🌐 Peer Manager</a>
                    <a href="/admin/monitoring">📊 System Status</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="main-content">