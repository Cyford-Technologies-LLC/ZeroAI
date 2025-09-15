<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

// Authentication check
if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'ZeroAI CRM Dashboard';
$currentPage = 'crm_dashboard';

include __DIR__ . '/includes/header.php';
?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM</div>
            <nav class="nav">
                <a href="/web/index.php" class="active">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/projects.php">Projects</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser) ?>!</span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="sidebar-group">
                <h3>CRM</h3>
                <a href="/web/index.php" <?= ($currentPage ?? '') === 'crm_dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
                <a href="/web/companies.php" <?= ($currentPage ?? '') === 'companies' ? 'class="active"' : '' ?>>Companies</a>
                <?php if (isset($_GET['company_id'])): ?>
                    <a href="/web/users.php?company_id=<?= $_GET['company_id'] ?>" style="padding-left: 40px;">👥 Users</a>
                    <a href="/web/contacts.php?company_id=<?= $_GET['company_id'] ?>" style="padding-left: 40px;">📞 Contacts</a>
                <?php else: ?>
                    <a href="/web/contacts.php" <?= ($currentPage ?? '') === 'contacts' ? 'class="active"' : '' ?>>Contacts</a>
                <?php endif; ?>
                <a href="/web/projects.php" <?= ($currentPage ?? '') === 'projects' ? 'class="active"' : '' ?>>Projects</a>
                <?php if (isset($_GET['project_id'])): ?>
                    <a href="/web/tasks.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">📋 Tasks</a>
                    <a href="/web/bugs.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">🐛 Bugs</a>
                    <a href="/web/features.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">✨ Features</a>
                <?php else: ?>
                    <a href="/web/tasks.php" <?= ($currentPage ?? '') === 'tasks' ? 'class="active"' : '' ?>>Tasks</a>
                <?php endif; ?>
            </div>
            <div class="sidebar-group">
                <h3>Tools</h3>
                <a href="/web/init.php">Setup Database</a>
                <a href="/web/cleanup.php">Cleanup Data</a>
            </div>
        </div>
        <div class="main-content">
            <div class="container">
        <div class="card">
            <h3>Quick Actions</h3>
            <a href="/web/companies.php" class="btn">Companies</a>
            <a href="/web/contacts.php" class="btn">Contacts</a>
            <a href="/web/projects.php" class="btn">Projects</a>
            <a href="/web/tasks.php" class="btn">Tasks</a>
            <a href="/web/init.php" class="btn btn-success">Setup Database</a>
        </div>
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
            <h3>Welcome to ZeroAI CRM</h3>
            <p>Your customer relationship management system is ready. Use the navigation above to manage your business data.</p>
            <p><strong>Logged in as:</strong> <?= htmlspecialchars($currentUser) ?></p>
<?php include __DIR__ . '/includes/footer.php'; ?>