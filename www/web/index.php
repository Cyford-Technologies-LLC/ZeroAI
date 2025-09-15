<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

// Authentication check
if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'ZeroAI CRM Dashboard';
$currentPage = 'crm_dashboard';

include __DIR__ . '/includes/header.php';
?>

<div style="background: #2563eb; color: white; padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
        <div style="font-size: 1.5rem; font-weight: bold;">🏢 ZeroAI CRM</div>
        <div style="display: flex; gap: 20px;">
            <a href="/web/index.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'crm_dashboard' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📊 Dashboard</a>
            <a href="/web/companies.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'companies' ? 'background: rgba(255,255,255,0.2);' : '' ?>">🏢 Companies</a>
            <a href="/web/contacts.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'contacts' ? 'background: rgba(255,255,255,0.2);' : '' ?>">👥 Contacts</a>
            <a href="/web/sales.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'sales' ? 'background: rgba(255,255,255,0.2);' : '' ?>">💰 Sales</a>
            <a href="/web/projects.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'projects' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📋 Projects</a>
            <a href="/web/tasks.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'tasks' ? 'background: rgba(255,255,255,0.2);' : '' ?>">✅ Tasks</a>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: rgba(255,255,255,0.9);">👤 <?= htmlspecialchars($currentUser) ?></span>
            <?php if ($isAdmin): ?><a href="/admin/dashboard.php" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">⚙️ Admin</a><?php endif; ?>
            <a href="/web/ai_workshop.php" style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🤖 AI</a>
            <a href="/web/logout.php" style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🚪 Logout</a>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <i class="fas fa-tachometer-alt text-primary"></i> 
                    Welcome to ZeroAI CRM
                </h1>
                <div class="badge bg-primary fs-6">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($currentUser) ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;">0</div>
                    <div>📢 Companies</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;">0</div>
                    <div>👥 Contacts</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                <div class="card-body text-center">
                    <div style="font-size: 2.5rem; font-weight: 700;">0</div>
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

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-rocket text-primary"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="/web/companies.php" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-building fa-2x d-block mb-2"></i>
                                <strong>Manage Companies</strong>
                                <small class="d-block">Add and manage your business clients</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/web/contacts.php" class="btn btn-success w-100 py-3">
                                <i class="fas fa-users fa-2x d-block mb-2"></i>
                                <strong>Manage Contacts</strong>
                                <small class="d-block">Track your business relationships</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/web/projects.php" class="btn btn-info w-100 py-3">
                                <i class="fas fa-project-diagram fa-2x d-block mb-2"></i>
                                <strong>Manage Projects</strong>
                                <small class="d-block">Organize your work and deliverables</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/web/tasks.php" class="btn btn-warning w-100 py-3">
                                <i class="fas fa-tasks fa-2x d-block mb-2"></i>
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
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle text-info"></i> About ZeroAI CRM
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        <strong>Zero Cost.</strong> No API fees or subscriptions.
                    </p>
                    <p class="small mb-2">
                        <strong>Zero Cloud.</strong> Your data stays on your machine.
                    </p>
                    <p class="small mb-0">
                        <strong>Zero Limits.</strong> Scale on your terms.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>