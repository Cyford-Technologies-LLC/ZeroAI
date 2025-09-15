    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM<?= isset($pageTitle) ? ' - ' . str_replace(' - ZeroAI CRM', '', $pageTitle) : '' ?></div>
            <nav class="nav">
                <a href="/web/index.php" <?= ($currentPage ?? '') === 'crm_dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
                <a href="/web/companies.php" <?= ($currentPage ?? '') === 'companies' ? 'class="active"' : '' ?>>Companies</a>
                <a href="/web/marketing.php" <?= ($currentPage ?? '') === 'marketing' ? 'class="active"' : '' ?>>Marketing</a>
                <a href="/web/sales.php" <?= ($currentPage ?? '') === 'sales' ? 'class="active"' : '' ?>>Sales</a>
                <a href="/web/projects.php" <?= ($currentPage ?? '') === 'projects' ? 'class="active"' : '' ?>>Projects</a>
                <a href="/web/integrations.php" <?= ($currentPage ?? '') === 'integrations' ? 'class="active"' : '' ?>>Integrations</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser ?? 'User') ?>!</span>
                <?php if (isset($isAdmin) && $isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/ai_workshop.php" class="header-btn">🤖 AI Workshop</a>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>