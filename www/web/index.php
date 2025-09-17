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
                <h1 class="h2 mb-0"><img src="/assets/frontend/images/icons/dashboard.svg" width="24" height="24" class="text-primary me-2"> Welcome to ZeroAI CRM</h1>
                <div class="d-flex gap-2">
                    <a href="/web/companies.php" class="btn btn-primary">
                        <img src="/assets/frontend/images/icons/building.svg" width="16" height="16" class="me-1"> Add Company
                    </a>
                    <a href="/web/projects.php" class="btn btn-success">
                        <img src="/assets/frontend/images/icons/project.svg" width="16" height="16" class="me-1"> New Project
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Compact Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2 bg-primary text-white">
                <div class="card-body p-2">
                    <h4 class="mb-1"><?= htmlspecialchars($totalCompanies) ?></h4>
                    <small>Companies</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2 bg-success text-white">
                <div class="card-body p-2">
                    <h4 class="mb-1"><?= htmlspecialchars($totalProjects) ?></h4>
                    <small>Projects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2 bg-info text-white">
                <div class="card-body p-2">
                    <h4 class="mb-1"><?= htmlspecialchars($totalUsers) ?></h4>
                    <small>Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2 bg-warning text-white">
                <div class="card-body p-2">
                    <h4 class="mb-1">0</h4>
                    <small>Tasks</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">🚀 Quick Actions</h5>
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
                    <a href="/web/employees.php" class="btn btn-outline-warning w-100 py-3">
                        <img src="/assets/frontend/images/icons/users.svg" width="48" height="48" class="d-block mb-2 mx-auto">
                        Add Employee
                    </a>
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

