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

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <i class="fas fa-tachometer-alt text-primary"></i> 
                    Welcome to ZeroAI CRM
                </h1>
            </div>
        </div>
    </div>
    
    <!-- Compact Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2">
                <div class="card-body p-2">
                    <h4 class="text-primary mb-1"><?= htmlspecialchars($totalCompanies) ?></h4>
                    <small class="text-muted">Companies</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2">
                <div class="card-body p-2">
                    <h4 class="text-success mb-1"><?= htmlspecialchars($totalContacts) ?></h4>
                    <small class="text-muted">Contacts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2">
                <div class="card-body p-2">
                    <h4 class="text-info mb-1"><?= htmlspecialchars($totalProjects) ?></h4>
                    <small class="text-muted">Projects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2">
                <div class="card-body p-2">
                    <h4 class="text-warning mb-1">0</h4>
                    <small class="text-muted">Tasks</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <img src="/assets/frontend/images/icons/rocket.svg" width="20" height="20" class="me-2"> Quick Actions
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/companies.php" class="btn btn-outline-primary w-100 py-3">
                        <img src="/assets/frontend/images/icons/building.svg" width="48" height="48" class="d-block mb-2 mx-auto">
                        Add Company
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/projects.php" class="btn btn-outline-success w-100 py-3">
                        <img src="/assets/frontend/images/icons/project.svg" width="48" height="48" class="d-block mb-2 mx-auto">
                        New Project
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/contacts.php" class="btn btn-outline-info w-100 py-3">
                        <img src="/assets/frontend/images/icons/users.svg" width="48" height="48" class="d-block mb-2 mx-auto">
                        Add Contact
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/tasks.php" class="btn btn-outline-warning w-100 py-3">
                        <img src="/assets/frontend/images/icons/tasks.svg" width="48" height="48" class="d-block mb-2 mx-auto">
                        Manage Tasks
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

