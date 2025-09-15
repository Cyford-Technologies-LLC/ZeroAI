<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Bugs - ZeroAI CRM';
$currentPage = 'bugs';
$projectId = $_GET['project_id'] ?? null;

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Get project info
$project = null;
if ($projectId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Project not found";
    }
}

include __DIR__ . '/includes/header.php';
?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Bugs<?= $project ? ' - ' . htmlspecialchars($project['name']) : '' ?></div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/contacts.php">Contacts</a>
                <a href="/web/projects.php">Projects</a>
                <a href="/web/tasks.php">Tasks</a>
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
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/contacts.php">Contacts</a>
                <a href="/web/projects.php">Projects</a>
                <?php if ($projectId): ?>
                    <a href="/web/tasks.php?project_id=<?= $projectId ?>" style="padding-left: 40px;">📋 Tasks</a>
                    <a href="/web/bugs.php?project_id=<?= $projectId ?>" class="active" style="padding-left: 40px;">🐛 Bugs</a>
                    <a href="/web/features.php?project_id=<?= $projectId ?>" style="padding-left: 40px;">✨ Features</a>
                <?php else: ?>
                    <a href="/web/tasks.php">Tasks</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="main-content">
            <div class="container">
                <?php if ($project): ?>
                    <div class="card">
                        <h3>Bugs for <?= htmlspecialchars($project['name']) ?></h3>
                        <p>Bug tracking functionality coming soon...</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3>No Project Selected</h3>
                        <p>Please select a project from the <a href="/web/projects.php">Projects</a> page to view bugs.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>