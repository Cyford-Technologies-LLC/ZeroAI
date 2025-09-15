<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

// Initialize with safe defaults
$tenants = [];
$totalCompanies = 0;
$totalProjects = 0;

try {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get tenants
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY name");
    $tenants = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Get totals
    $totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() ?: 0;
    $totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    error_log('CRM Dashboard Error: ' . $e->getMessage());
    $tenants = [];
}

$pageTitle = 'CRM Dashboard - ZeroAI';
$currentPage = 'crm_dashboard';
$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0"><i class="fas fa-tachometer-alt text-primary"></i> CRM Dashboard</h1>
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

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($tenants) ?></div>
        <div class="stat-label">Tenants</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalCompanies ?></div>
        <div class="stat-label">Companies</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalProjects ?></div>
        <div class="stat-label">Projects</div>
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
    <h3>🚀 Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
        <a href="tenant_create.php" style="padding: 15px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; text-align: center;">
            🏢 Create Tenant
        </a>
        <a href="company_create.php" style="padding: 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; text-align: center;">
            🏭 Add Company
        </a>
        <a href="project_create.php" style="padding: 15px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px; text-align: center;">
            📋 New Project
        </a>
        <a href="employee_create.php" style="padding: 15px; background: #6f42c1; color: white; text-decoration: none; border-radius: 4px; text-align: center;">
            👥 Add Employee
        </a>
    </div>
</div>

        </div>
    </div>
</div>
</body>
</html>