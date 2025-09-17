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
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;"><?= htmlspecialchars($totalCompanies) ?></div>
                    <div>📢 Companies</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;"><?= htmlspecialchars($totalContacts) ?></div>
                    <div>👥 Contacts</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;"><?= htmlspecialchars($totalProjects) ?></div>
                    <div>📋 Projects</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;">0</div>
                    <div>✅ Tasks</div>
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

