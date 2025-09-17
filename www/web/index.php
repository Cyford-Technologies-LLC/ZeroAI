<?php
$pageTitle = 'Dashboard - ZeroAI CRM';
$currentPage = 'dashboard';
include __DIR__ . '/includes/header.php';

// Get actual counts from database
$totalCompanies = 0;
$totalProjects = 0;
$totalUsers = 0;
$totalContacts = 0;

try {
    $totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() ?: 0;
    $totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0;
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    
    // Get contacts count if table exists
    try {
        $totalContacts = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $totalContacts = 0;
    }
} catch (Exception $e) {
    // Use defaults
}
?>

<style>
.text-primary { color: #0d6efd !important; }
.text-success { color: #198754 !important; }
.text-info { color: #0dcaf0 !important; }
.text-warning { color: #ffc107 !important; }
.text-muted { color: #6c757d !important; }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <img src="/assets/frontend/images/icons/logo.svg" width="24" height="24" class="text-primary me-2"> 
                    Welcome to ZeroAI CRM
                </h1>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-2 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card text-white" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <div class="card-body text-center py-3">
                    <div style="font-size: 1.8rem; font-weight: 700;"><?= htmlspecialchars($totalCompanies) ?></div>
                    <div style="font-size: 0.9rem;">📢 Companies</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card text-white" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="card-body text-center py-3">
                    <div style="font-size: 1.8rem; font-weight: 700;"><?= htmlspecialchars($totalContacts) ?></div>
                    <div style="font-size: 0.9rem;">👥 Contacts</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card text-white" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                <div class="card-body text-center py-3">
                    <div style="font-size: 1.8rem; font-weight: 700;"><?= htmlspecialchars($totalProjects) ?></div>
                    <div style="font-size: 0.9rem;">📋 Projects</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card text-white" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="card-body text-center py-3">
                    <div style="font-size: 1.8rem; font-weight: 700;">0</div>
                    <div style="font-size: 0.9rem;">✅ Tasks</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <img src="/assets/frontend/images/icons/rocket.svg" width="20" height="20" class="me-2"> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="/web/companies.php" class="btn btn-primary w-100 py-3">
                                <img src="/assets/frontend/images/icons/building.svg" width="32" height="32" class="d-block mb-2 mx-auto">
                                <strong>Manage Companies</strong>
                                <small class="d-block">Add and manage your business clients</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/web/contacts.php" class="btn btn-success w-100 py-3">
                                <img src="/assets/frontend/images/icons/users.svg" width="32" height="32" class="d-block mb-2 mx-auto">
                                <strong>Manage Contacts</strong>
                                <small class="d-block">Track your business relationships</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/web/projects.php" class="btn btn-info w-100 py-3">
                                <img src="/assets/frontend/images/icons/project.svg" width="32" height="32" class="d-block mb-2 mx-auto">
                                <strong>Manage Projects</strong>
                                <small class="d-block">Organize your work and deliverables</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/web/tasks.php" class="btn btn-warning w-100 py-3">
                                <img src="/assets/frontend/images/icons/tasks.svg" width="32" height="32" class="d-block mb-2 mx-auto">
                                <strong>Manage Tasks</strong>
                                <small class="d-block">Track your daily activities</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog text-secondary"></i> System
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/web/init.php" class="btn btn-outline-primary">
                            <i class="fas fa-database"></i> Setup Database
                        </a>
                        <a href="/web/ai_workshop.php" class="btn btn-outline-info">
                            <i class="fas fa-robot"></i> AI Workshop
                        </a>
                        <?php if ($isAdmin): ?>
                            <a href="/admin/dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-shield-alt"></i> Admin Panel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

