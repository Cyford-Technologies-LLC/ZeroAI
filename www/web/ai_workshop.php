<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'AI Workshop - ZeroAI CRM';
$currentPage = 'ai_workshop';

include __DIR__ . '/includes/header.php';
?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🤖 ZeroAI AI Workshop</div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/projects.php">Projects</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser) ?>!</span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/ai_workshop.php" class="header-btn">🤖 AI Workshop</a>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="main-content">
        <div class="container">
            <div class="card">
                <h3>AI Workshop</h3>
                <p>Welcome to the AI Workshop! This is where you can interact with AI agents and create automated workflows.</p>
                <p>AI Workshop functionality coming soon...</p>
            </div>
        </div>
    </div>
</body>
</html>