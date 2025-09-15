<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

// Authentication check
if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$pageTitle = 'ZeroAI CRM Dashboard';
$currentPage = 'crm_dashboard';
include __DIR__ . '/../admin/includes/header.php';
?>
<div class="card">
    <h1>🏢 ZeroAI CRM Dashboard</h1>
    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <a href="/web/companies.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Companies</a>
        <a href="/web/contacts.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;">Contacts</a>
        <a href="/web/projects.php" style="padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;">Projects</a>
        <a href="/web/tasks.php" style="padding: 10px 20px; background: #ffc107; color: #212529; text-decoration: none; border-radius: 4px;">Tasks</a>
    </div>
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