<?php
$pageTitle = $pageTitle ?? 'ZeroAI CRM';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/web/index.php">🏢 ZeroAI CRM</a>
            <div class="navbar-nav me-auto">
                <a class="nav-link <?= ($currentPage ?? '') === 'crm_dashboard' ? 'active' : '' ?>" href="/web/index.php">Dashboard</a>
                <a class="nav-link <?= ($currentPage ?? '') === 'companies' ? 'active' : '' ?>" href="/web/companies.php">Companies</a>
                <a class="nav-link <?= ($currentPage ?? '') === 'marketing' ? 'active' : '' ?>" href="/web/marketing.php">Marketing</a>
                <a class="nav-link <?= ($currentPage ?? '') === 'sales' ? 'active' : '' ?>" href="/web/sales.php">Sales</a>
                <a class="nav-link <?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>" href="/web/projects.php">Projects</a>
                <a class="nav-link <?= ($currentPage ?? '') === 'integrations' ? 'active' : '' ?>" href="/web/integrations.php">Integrations</a>
            </div>
            <div class="navbar-nav">
                <span class="navbar-text me-3">Welcome, <?= htmlspecialchars($currentUser ?? 'User') ?>!</span>
                <?php if (isset($isAdmin) && $isAdmin): ?><a href="/admin/dashboard.php" class="btn btn-secondary btn-sm me-2">⚙️ Admin</a><?php endif; ?>
                <a href="/web/ai_workshop.php" class="btn btn-info btn-sm me-2">🤖 AI Workshop</a>
                <a href="/web/logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>