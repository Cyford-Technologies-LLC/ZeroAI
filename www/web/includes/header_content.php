    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/web/index.php">
                <i class="fas fa-building"></i> ZeroAI CRM
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav me-auto">
                    <a class="nav-link <?= ($currentPage ?? '') === 'crm_dashboard' ? 'active' : '' ?>" href="/web/index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link <?= ($currentPage ?? '') === 'companies' ? 'active' : '' ?>" href="/web/companies.php">
                        <i class="fas fa-building"></i> Companies
                    </a>
                    <a class="nav-link <?= ($currentPage ?? '') === 'contacts' ? 'active' : '' ?>" href="/web/contacts.php">
                        <i class="fas fa-users"></i> Contacts
                    </a>
                    <a class="nav-link <?= ($currentPage ?? '') === 'sales' ? 'active' : '' ?>" href="/web/sales.php">
                        <i class="fas fa-chart-line"></i> Sales
                    </a>
                    <a class="nav-link <?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>" href="/web/projects.php">
                        <i class="fas fa-project-diagram"></i> Projects
                    </a>
                    <a class="nav-link <?= ($currentPage ?? '') === 'tasks' ? 'active' : '' ?>" href="/web/tasks.php">
                        <i class="fas fa-tasks"></i> Tasks
                    </a>
                </div>
                <div class="navbar-nav">
                    <span class="navbar-text me-3">
                        <i class="fas fa-user"></i> Welcome, <?= htmlspecialchars($currentUser ?? 'User') ?>!
                    </span>
                    <?php if (isset($isAdmin) && $isAdmin): ?>
                        <a href="/admin/dashboard.php" class="btn btn-secondary btn-sm me-2">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a href="/web/ai_workshop.php" class="btn btn-info btn-sm me-2">
                        <i class="fas fa-robot"></i> AI Workshop
                    </a>
                    <a href="/web/logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>