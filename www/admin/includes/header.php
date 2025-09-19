<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/csp.php';

require_once __DIR__ . '/../auth_check.php';


if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}
// Check for 404 errors and log them
if (http_response_code() == 404 || !file_exists($_SERVER['SCRIPT_FILENAME'])) {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->error('404 Not Found', [
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ]);
}

$pageTitle = $pageTitle ?? 'ZeroAI Admin';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="ZeroAI Admin Portal - Manage your AI agents, crews, and system configuration. Zero Cost, Zero Cloud, Zero Limits.">
    <meta name="keywords" content="ZeroAI, Admin, AI Agents, CrewAI, Claude, System Management, Dashboard">
    <meta name="author" content="ZeroAI">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#007bff">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="ZeroAI Admin Portal - Manage your AI workforce with zero cost and zero limits.">
    <meta property="og:image" content="/assets/admin/images/admin-og.png">
    <meta property="og:site_name" content="ZeroAI Admin">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="twitter:description" content="ZeroAI Admin Portal - Manage your AI workforce with zero cost and zero limits.">
    <meta property="twitter:image" content="/assets/admin/images/admin-twitter.png">
    
    <!-- Mobile App Meta -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZeroAI Admin">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ZeroAI Admin Styles -->
    <link rel="stylesheet" href="/assets/admin/css/admin-styles.css">
    <?php if ($currentPage === 'claude_chat'): ?>
    <link rel="stylesheet" href="/assets/css/claude.css">
    <?php endif; ?>
</head>
<body>
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
        .header-btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-frontend { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .btn-logout { background: linear-gradient(135deg, #dc3545, #e74c3c); color: white; }
        .content-wrapper { display: flex; flex: 1; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px 0; overflow-y: auto; }
        .sidebar-group { margin-bottom: 20px; }
        .sidebar-group h3 { color: #adb5bd; font-size: 12px; text-transform: uppercase; margin: 0 20px 10px; font-weight: bold; }
        .sidebar a { display: block; color: #dee2e6; text-decoration: none; padding: 10px 20px; transition: background 0.3s; }
        .sidebar a:hover { background: #495057; }
        .sidebar a.active { background: #007bff; color: white; }
        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
    </style>
    <script>
    // Universal modal and form functions
    function toggleCollapse(id) {
        const element = document.getElementById(id);
        if (!element) {
            console.error('Element not found:', id);
            return;
        }
        const isHidden = element.style.display === 'none' || window.getComputedStyle(element).display === 'none';
        element.style.display = isHidden ? 'block' : 'none';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
    <div class="header">
        <div class="header-content">
            <div class="logo">ZeroAI Admin</div>
            <nav class="nav">
                <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/web/') === 0): ?>
                    <a href="/web/index.php" <?= ($currentPage ?? '') === 'crm_dashboard' ? 'class="active"' : '' ?>>CRM Dashboard</a>
                    <a href="/web/companies.php" <?= ($currentPage ?? '') === 'companies' ? 'class="active"' : '' ?>>Companies</a>
                    <a href="/web/contacts.php" <?= ($currentPage ?? '') === 'contacts' ? 'class="active"' : '' ?>>Contacts</a>
                    <a href="/web/projects.php" <?= ($currentPage ?? '') === 'projects' ? 'class="active"' : '' ?>>Projects</a>
                    <a href="/web/tasks.php" <?= ($currentPage ?? '') === 'tasks' ? 'class="active"' : '' ?>>Tasks</a>
                    <a href="/admin/dashboard.php">Admin</a>
                <?php else: ?>
                    <a href="/admin/dashboard.php" <?= ($currentPage ?? '') === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
                    <a href="/admin/crewai.php" <?= in_array($currentPage ?? '', ['crews', 'agents', 'knowledge', 'tasks']) ? 'class="active"' : '' ?>>CrewAI</a>
                    <a href="/admin/chat.php" <?= in_array($currentPage ?? '', ['crew_chat', 'claude', 'chat']) ? 'class="active"' : '' ?>>Chat</a>
                    <a href="/admin/tools.php" <?= in_array($currentPage ?? '', ['monitoring', 'logs', 'performance', 'backup', 'restore', 'error_logs', 'diagnostics', 'tools']) ? 'class="active"' : '' ?>>Tools</a>
                    <a href="/admin/system.php" <?= in_array($currentPage ?? '', ['localhost', 'peers']) ? 'class="active"' : '' ?>>System</a>
                    <a href="/admin/settings.php" <?= ($currentPage ?? '') === 'settings' ? 'class="active"' : '' ?>>Settings</a>
                <?php endif; ?>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= $_SESSION['admin_user'] ?? 'Admin' ?>!</span>
                <a href="/" target="_blank" class="header-btn btn-frontend">🌐 Frontend</a>
                <a href="/admin/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <?php 
        $currentSection = 'dashboard';
        if (in_array($currentPage ?? '', ['crews', 'agents', 'tasks', 'knowledge'])) {
            $currentSection = 'crewai';
        } elseif (in_array($currentPage ?? '', ['crew_chat', 'claude', 'chat', 'claude_chat'])) {
            $currentSection = 'chat';
        } elseif (in_array($currentPage ?? '', ['tools', 'monitoring', 'error_logs', 'performance', 'backup', 'restore', 'diagnostics', 'db_tools'])) {
            $currentSection = 'tools';
        } elseif (in_array($currentPage ?? '', ['localhost', 'peers'])) {
            $currentSection = 'system';
        } elseif (in_array($currentPage ?? '', ['settings', 'config', 'cloud_settings', 'claude_settings', 'users'])) {
            $currentSection = 'settings';
        }
        ?>
        
        <?php if ($currentSection !== 'dashboard'): ?>
        <div class="sidebar">
            
            <?php if ($currentSection === 'crewai'): ?>
                <div class="sidebar-group">
                    <h3>Crew Management</h3>
                    <a href="/admin/crews.php" <?= ($currentPage ?? '') === 'crews' ? 'class="active"' : '' ?>>Crews</a>
                    <a href="/admin/agents.php" <?= ($currentPage ?? '') === 'agents' ? 'class="active"' : '' ?>>Agents</a>
                    <a href="/admin/test_dynamic_agents.php" <?= ($currentPage ?? '') === 'agents' ? 'class="active"' : '' ?>>🧪 Test Dynamic Agents</a>
                    <a href="/admin/tasks.php" <?= ($currentPage ?? '') === 'tasks' ? 'class="active"' : '' ?>>Tasks</a>
                </div>
                <div class="sidebar-group">
                    <h3>AI Models</h3>
                    <a href="/admin/ollama.php" <?= ($currentPage ?? '') === 'ollama' ? 'class="active"' : '' ?>>Ollama</a>
                </div>
                <div class="sidebar-group">
                    <h3>Resources</h3>
                    <a href="/admin/knowledge.php" <?= ($currentPage ?? '') === 'knowledge' ? 'class="active"' : '' ?>>Knowledge Base</a>
                    <a href="/admin/examples.php" <?= ($currentPage ?? '') === 'examples' ? 'class="active"' : '' ?>>Examples</a>
                </div>
            <?php elseif ($currentSection === 'chat'): ?>
                <div class="sidebar-group">
                    <h3>Team Chat</h3>
                    <a href="/admin/crew_chat.php" <?= ($currentPage ?? '') === 'crew_chat' ? 'class="active"' : '' ?>>👥 Crew Chat</a>
                </div>
                <div class="sidebar-group">
                    <h3>Individual Agents</h3>
                    <a href="/admin/chat?agent=team_manager">Team Manager</a>
                    <a href="/admin/chat?agent=project_manager">Project Manager</a>
                    <a href="/admin/chat?agent=senior_dev">Senior Developer</a>
                    <a href="/admin/chat?agent=junior_dev">Junior Developer</a>
                    <a href="/admin/chat?agent=code_researcher">Code Researcher</a>
                </div>
                <div class="sidebar-group">
                    <h3>Cloud AI</h3>
                    <?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'demo'): ?>
                        <a href="/admin/claude_chat.php" <?= ($currentPage ?? '') === 'claude_chat' ? 'class="active"' : '' ?>>🔮 Claude</a>
                    <?php else: ?>
                        <span style="color: #999; padding: 10px 20px; display: block;">🔒 Claude (Demo Restricted)</span>
                    <?php endif; ?>
                </div>
            <?php elseif ($currentSection === 'tools'): ?>
                <div class="sidebar-group">
                    <h3>System Tools</h3>
                    <a href="/admin/monitoring.php" <?= ($currentPage ?? '') === 'monitoring' ? 'class="active"' : '' ?>>📊 Monitoring</a>
                    <a href="/admin/error_logs.php" <?= ($currentPage ?? '') === 'error_logs' ? 'class="active"' : '' ?>>📋 Logs</a>
                    <a href="/admin/system_stats.php" <?= ($currentPage ?? '') === 'performance' ? 'class="active"' : '' ?>>⚡ Performance</a>
                </div>
                <div class="sidebar-group">
                    <h3>Data Management</h3>
                    <a href="/admin/backup.php" <?= ($currentPage ?? '') === 'backup' ? 'class="active"' : '' ?>>💾 Backup</a>
                    <a href="/admin/restore.php" <?= ($currentPage ?? '') === 'restore' ? 'class="active"' : '' ?>>🔄 Restore</a>
                </div>
                <div class="sidebar-group">
                    <h3>Diagnostics</h3>
                    <a href="/admin/error_logs.php" <?= ($currentPage ?? '') === 'error_logs' ? 'class="active"' : '' ?>>🚨 Error Logs</a>
                    <a href="/admin/diagnostics" <?= ($currentPage ?? '') === 'diagnostics' ? 'class="active"' : '' ?>>🔍 System Diagnostics</a>
                </div>
                <div class="sidebar-group">
                    <h3>Development Tools</h3>
                    <a href="/admin/api_tester">🧪 API Tester</a>
                    <a href="/admin/database.php" <?= ($currentPage ?? '') === 'db_tools' ? 'class="active"' : '' ?>>🛠️ DB Tools</a>
                    <a href="/admin/file_manager">📁 File Manager</a>
                </div>

            <?php elseif ($currentSection === 'settings'): ?>
                <div class="sidebar-group">
                    <h3>Configuration</h3>
                    <a href="/admin/settings.php" <?= ($currentPage ?? '') === 'settings' ? 'class="active"' : '' ?>>General Settings</a>
                    <a href="/admin/peers.php" <?= ($currentPage ?? '') === 'peers' ? 'class="active"' : '' ?>>Peer Management</a>
                    <a href="/admin/settings/menu_manager.php" <?= ($currentPage ?? '') === 'menu_manager' ? 'class="active"' : '' ?>>🍔 Menu Manager</a>
                    <a href="/admin/settings/subscription_plans.php" <?= ($currentPage ?? '') === 'subscription_plans' ? 'class="active"' : '' ?>>💰 Subscription Plans</a>
                    <a href="/admin/config.php" <?= ($currentPage ?? '') === 'config' ? 'class="active"' : '' ?>>System Config</a>
                    <a href="/admin/api" <?= ($currentPage ?? '') === 'api' ? 'class="active"' : '' ?>>API Settings</a>
                </div>
                <?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'demo'): ?>
                <div class="sidebar-group">
                    <h3>Cloud AI Settings</h3>
                    <a href="/admin/cloud_settings.php" <?= ($currentPage ?? '') === 'cloud_settings' ? 'class="active"' : '' ?>>Cloud Providers</a>
                    <a href="/admin/claude_settings.php" <?= ($currentPage ?? '') === 'claude_settings' ? 'class="active"' : '' ?>>Claude AI</a>
                </div>
                <?php endif; ?>
                <?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'demo'): ?>
                <div class="sidebar-group">
                    <h3>User Management</h3>
                    <a href="/admin/users.php" <?= ($currentPage ?? '') === 'users' ? 'class="active"' : '' ?>>All Users</a>
                    <a href="/admin/roles.php" <?= ($currentPage ?? '') === 'roles' ? 'class="active"' : '' ?>>Roles & Permissions</a>
                    <a href="/admin/visitor_analytics.php" <?= ($currentPage ?? '') === 'analytics' ? 'class="active"' : '' ?>>📊 Visitor Analytics</a>
                    <a href="/admin/sessions.php" <?= ($currentPage ?? '') === 'sessions' ? 'class="active"' : '' ?>>Active Sessions</a>
                </div>
                <?php endif; ?>
            <?php elseif ($currentSection === 'system'): ?>
                <div class="sidebar-group">
                    <h3>System Resources</h3>
                    <a href="/admin/localhost.php" <?= ($currentPage ?? '') === 'localhost' ? 'class="active"' : '' ?>>🖥️ Local Host</a>
                    <a href="/admin/peers.php" <?= ($currentPage ?? '') === 'peers' ? 'class="active"' : '' ?>>🌐 Peers</a>
                </div>
                <div class="sidebar-group">
                    <h3>System Info</h3>
                    <a href="/admin/system_health">💚 Health Check</a>
                    <a href="/admin/resource_usage">📊 Resource Usage</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="main-content" <?= $currentSection === 'dashboard' ? 'style="margin-left: 0; width: 100%;"' : '' ?>>


