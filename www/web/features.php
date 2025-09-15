<?php
include __DIR__ . '/includes/header.php';

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


?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Features<?= $project ? ' - ' . htmlspecialchars($project['name']) : '' ?></div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
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
        <div class="main-content">
            <div class="container">
                <?php if ($project): ?>
                    <div class="card">
                        <h3>Features for <?= htmlspecialchars($project['name']) ?></h3>
                        <p>Feature management functionality coming soon...</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3>No Project Selected</h3>
                        <p>Please select a project from the <a href="/web/projects.php">Projects</a> page to view features.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>