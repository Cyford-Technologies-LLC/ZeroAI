<?php
$pageTitle = 'CRM Dashboard - ZeroAI';
$currentPage = 'crm_dashboard';
include __DIR__ . '/includes/header.php';

// Initialize with safe defaults
$tenants = [];
$totalCompanies = 0;
$totalProjects = 0;
$totalUsers = 0;

try {
    // Use existing $pdo from header
    if ($isAdmin) {
        $totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() ?: 0;
        $totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0;
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE created_by = ?");
        $stmt->execute([$currentUser]);
        $totalCompanies = $stmt->fetchColumn() ?: 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ?");
        $stmt->execute([$currentUser]);
        $totalProjects = $stmt->fetchColumn() ?: 0;
        
        $totalUsers = 1; // Non-admin users only see themselves
    }
    
    // Get tenants if table exists
    try {
        $stmt = $pdo->query("SELECT * FROM tenants ORDER BY name");
        $tenants = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $tenants = [];
    }
} catch (Exception $e) {
    // Use defaults
}







?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0"><img src="/assets/frontend/images/icons/dashboard.svg" width="24" height="24" class="text-primary me-2"> Welcome to ZeroAI CRM</h1>
                <div class="d-flex gap-2">
                    <a href="/web/companies.php" class="btn btn-primary">
                        <img src="/assets/frontend/images/icons/plus.svg" width="16" height="16" class="me-1"> Add Company
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
                    <h4 class="text-success mb-1"><?= htmlspecialchars($totalProjects) ?></h4>
                    <small class="text-muted">Projects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card text-center py-2">
                <div class="card-body p-2">
                    <h4 class="text-info mb-1"><?= htmlspecialchars($totalUsers) ?></h4>
                    <small class="text-muted">Users</small>
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
                        <img src="/assets/frontend/images/icons/user-tie.svg" width="48" height="48" class="d-block mb-2 mx-auto">
                        Add Employee
                    </a>
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>
</div>
</body>
</html>

