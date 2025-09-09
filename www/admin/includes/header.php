<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?? 'ZeroAI Admin' ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; display: flex; flex-direction: column; height: 100vh; }
        .header { background: #007bff; color: white; padding: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; transition: background 0.3s; }
        .nav a:hover { background: rgba(255,255,255,0.1); }
        .nav a.active { background: rgba(255,255,255,0.2); }
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content { display: none; position: absolute; background: #0056b3; min-width: 160px; box-shadow: 0px 8px 16px rgba(0,0,0,0.2); z-index: 1; border-radius: 4px; top: 100%; }
        .dropdown-content a { display: block; padding: 8px 16px; border-radius: 0; }
        .dropdown-content a:hover { background: rgba(255,255,255,0.1); }
        .dropdown:hover .dropdown-content { display: block; }
        .dropdown > a::after { content: ' ▼'; font-size: 10px; }
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
                <a href="/admin/tools" <?= in_array($currentPage ?? '', ['monitoring', 'logs', 'performance']) ? 'class="active"' : '' ?>>Tools</a>
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
            } elseif (in_array($currentPage ?? '', ['monitoring', 'logs', 'performance'])) {
                $currentSection = 'tools';
            } elseif (in_array($currentPage ?? '', ['localhost', 'peers'])) {
                $currentSection = 'system';
            } elseif (in_array($currentPage ?? '', ['settings', 'config', 'cloud_settings', 'claude_settings', 'users'])) {
                $currentSection = 'settings';
            }
            ?>
            
            <?php if ($currentSection === 'crewai'): ?>
                <div class="sidebar-group">
                    <h3>Crew Management</h3>
                    <a href="/admin/crews" <?= ($currentPage ?? '') === 'crews' ? 'class="active"' : '' ?>>Crews</a>
                    <a href="/admin/agents" <?= ($currentPage ?? '') === 'agents' ? 'class="active"' : '' ?>>Agents</a>
                    <a href="/admin/test_dynamic_agents" <?= ($currentPage ?? '') === 'agents' ? 'class="active"' : '' ?>>🧪 Test Dynamic Agents</a>
                    <a href="/admin/tasks" <?= ($currentPage ?? '') === 'tasks' ? 'class="active"' : '' ?>>Tasks</a>
                </div>
                <div class="sidebar-group">
                    <h3>AI Models</h3>
                    <a href="/admin/ollama" <?= ($currentPage ?? '') === 'ollama' ? 'class="active"' : '' ?>>Ollama</a>
                </div>
                <div class="sidebar-group">
                    <h3>Resources</h3>
                    <a href="/admin/knowledge" <?= ($currentPage ?? '') === 'knowledge' ? 'class="active"' : '' ?>>Knowledge Base</a>
                    <a href="/admin/examples" <?= ($currentPage ?? '') === 'examples' ? 'class="active"' : '' ?>>Examples</a>
                </div>
            <?php elseif ($currentSection === 'chat'): ?>
                <div class="sidebar-group">
                    <h3>AI Assistants</h3>
                    <a href="/admin/claude_chat" <?= ($currentPage ?? '') === 'claude_chat' ? 'class="active"' : '' ?>>💬 Claude Direct Chat</a>
                    <a href="/admin/crew_chat" <?= ($currentPage ?? '') === 'crew_chat' ? 'class="active"' : '' ?>>👥 Crew Chat</a>
                    <a href="/admin/chat" <?= ($currentPage ?? '') === 'chat' ? 'class="active"' : '' ?>>🤖 Agent Chat</a>
                </div>
                <div class="sidebar-group">
                    <h3>Settings</h3>
                    <a href="/admin/claude_settings">⚙️ Configure Claude</a>
                </div>
                <div class="sidebar-group">
                    <h3>Individual Agents</h3>
                    <a href="/admin/chat?agent=team_manager">Team Manager</a>
                    <a href="/admin/chat?agent=project_manager">Project Manager</a>
                    <a href="/admin/chat?agent=senior_dev">Senior Developer</a>
                    <a href="/admin/chat?agent=junior_dev">Junior Developer</a>
                    <a href="/admin/chat?agent=code_researcher">Code Researcher</a>
                </div>
            <?php elseif ($currentSection === 'tools'): ?>
                <div class="sidebar-group">
                    <h3>Monitoring & Analytics</h3>
                    <a href="/admin/monitoring" <?= ($currentPage ?? '') === 'monitoring' ? 'class="active"' : '' ?>>System Monitoring</a>
                    <a href="/admin/logs" <?= ($currentPage ?? '') === 'logs' ? 'class="active"' : '' ?>>Logs</a>
                    <a href="/admin/performance" <?= ($currentPage ?? '') === 'performance' ? 'class="active"' : '' ?>>Performance</a>
                </div>
                <div class="sidebar-group">
                    <h3>Development Tools</h3>
                    <a href="/admin/api_tester">🧪 API Tester</a>
                    <a href="/admin/database_viewer">🗄️ Database Viewer</a>
                    <a href="/admin/file_manager">📁 File Manager</a>
                </div>

            <?php elseif ($currentSection === 'settings'): ?>
                <div class="sidebar-group">
                    <h3>Configuration</h3>
                    <a href="/admin/settings" <?= ($currentPage ?? '') === 'settings' ? 'class="active"' : '' ?>>General Settings</a>
                    <a href="/admin/config" <?= ($currentPage ?? '') === 'config' ? 'class="active"' : '' ?>>System Config</a>
                    <a href="/admin/api" <?= ($currentPage ?? '') === 'api' ? 'class="active"' : '' ?>>API Settings</a>
                </div>
                <div class="sidebar-group">
                    <h3>Cloud AI Settings</h3>
                    <a href="/admin/cloud_settings" <?= ($currentPage ?? '') === 'cloud_settings' ? 'class="active"' : '' ?>>Cloud Providers</a>
                    <a href="/admin/claude_settings" <?= ($currentPage ?? '') === 'claude_settings' ? 'class="active"' : '' ?>>Claude AI</a>
                </div>
                <div class="sidebar-group">
                    <h3>User Management</h3>
                    <a href="/admin/users" <?= ($currentPage ?? '') === 'users' ? 'class="active"' : '' ?>>All Users</a>
                    <a href="/admin/roles" <?= ($currentPage ?? '') === 'roles' ? 'class="active"' : '' ?>>Roles & Permissions</a>
                    <a href="/admin/sessions" <?= ($currentPage ?? '') === 'sessions' ? 'class="active"' : '' ?>>Active Sessions</a>
                </div>
            <?php elseif ($currentSection === 'system'): ?>
                <div class="sidebar-group">
                    <h3>System Resources</h3>
                    <a href="/admin/localhost" <?= ($currentPage ?? '') === 'localhost' ? 'class="active"' : '' ?>>🖥️ Local Host</a>
                    <a href="/admin/peers" <?= ($currentPage ?? '') === 'peers' ? 'class="active"' : '' ?>>🌐 Peers</a>
                </div>
                <div class="sidebar-group">
                    <h3>System Info</h3>
                    <a href="/admin/system_health">💚 Health Check</a>
                    <a href="/admin/resource_usage">📊 Resource Usage</a>
                </div>
            <?php else: ?>
                <div class="sidebar-group">
                    <h3>Quick Actions</h3>
                    <a href="/admin/crew_chat">💬 Start Crew Chat</a>
                    <a href="/admin/agents">🤖 View Agents</a>
                    <a href="/admin/monitoring">📊 System Status</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="main-content">