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
<div class="container-fluid mt-3">
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
</div>
</body>
</html>