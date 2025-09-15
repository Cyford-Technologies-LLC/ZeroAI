<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

// Use existing classes
require_once __DIR__ . '/../src/Models/Tenant.php';
require_once __DIR__ . '/../src/Core/Company.php';
require_once __DIR__ . '/../src/Core/Project.php';

$tenant = new \ZeroAI\Core\Tenant();
$company = new \ZeroAI\Core\Company();
$project = new \ZeroAI\Core\Project();

$tenants = $tenant->getAll();
$totalCompanies = 0;
$totalProjects = 0;

foreach ($tenants as $t) {
    $companies = $company->findByTenant($t['id']);
    $totalCompanies += count($companies);
    foreach ($companies as $c) {
        $projects = $project->findByCompany($c['id']);
        $totalProjects += count($projects);
    }
}

$pageTitle = 'CRM Dashboard - ZeroAI';
include __DIR__ . '/../admin/includes/header.php';
?>

<h1>🏢 Multi-Tenant CRM Dashboard</h1>

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
            <?php foreach ($tenants as $t): ?>
                <?php $companies = $company->findByTenant($t['id']); ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($t['name']) ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($t['domain']) ?></td>
                    <td style="padding: 8px; text-align: center; border: 1px solid #ddd;"><?= count($companies) ?></td>
                    <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                        <a href="tenant_view.php?id=<?= $t['id'] ?>">View</a> |
                        <a href="company_list.php?tenant=<?= $t['id'] ?>">Companies</a>
                    </td>
                </tr>
            <?php endforeach; ?>
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

<?php include __DIR__ . '/../admin/includes/footer.php'; ?>