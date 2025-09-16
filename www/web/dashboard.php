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
    // Use same logic as header for company count
    if ($isAdmin) {
        $totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() ?: 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE organization_id = ?");
        $stmt->execute([$userOrgId]);
        $totalCompanies = $stmt->fetchColumn() ?: 0;
    }
    $totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0;
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    
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
                <h1 class="h2 mb-0"><i class="fas fa-chart-line text-primary"></i> Welcome to ZeroAI CRM</h1>
                <div class="d-flex gap-2">
                    <a href="/web/companies.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Company
                    </a>
                    <a href="/web/projects.php" class="btn btn-success">
                        <i class="fas fa-project-diagram"></i> New Project
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
                    <h4 class="text-warning mb-1"><?= htmlspecialchars(count($tenants)) ?></h4>
                    <small class="text-muted">Tenants</small>
                </div>
            </div>
        </div>
    </div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3>🏢 Tenants</h3>
        <a href="tenant_create.php" class="btn btn-primary">Add Tenant</a>
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Name</th>
                <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Domain</th>
                <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Companies</th>
                <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($tenants)): ?>
                <?php foreach ($tenants as $t): ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($t['name']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($t['domain'] ?? '') ?></td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">0</td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <a href="tenant_view.php?id=<?= $t['id'] ?>">View</a> |
                            <a href="company_list.php?tenant=<?= $t['id'] ?>">Companies</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="padding: 20px; text-align: center; color: #666;">No tenants found. <a href="setup_tenant_db.php">Setup Database</a></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">🚀 Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/companies.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-building fa-2x d-block mb-2"></i>
                        Add Company
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/projects.php" class="btn btn-outline-success w-100 py-3">
                        <i class="fas fa-project-diagram fa-2x d-block mb-2"></i>
                        New Project
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/contacts.php" class="btn btn-outline-info w-100 py-3">
                        <i class="fas fa-users fa-2x d-block mb-2"></i>
                        Add Contact
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="/web/employees.php" class="btn btn-outline-warning w-100 py-3">
                        <i class="fas fa-user-tie fa-2x d-block mb-2"></i>
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

