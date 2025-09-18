<?php
$pageTitle = 'Marketing - ZeroAI CRM';
$currentPage = 'marketing';
include __DIR__ . '/includes/header.php';

// Create marketing tables if not exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(50) DEFAULT 'email',
        status VARCHAR(20) DEFAULT 'draft',
        budget DECIMAL(10,2),
        start_date DATE,
        end_date DATE,
        target_audience TEXT,
        description TEXT,
        organization_id VARCHAR(10),
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add organization_id column if not exists
    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN organization_id VARCHAR(10)");
    } catch (Exception $e) {}
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get marketing statistics
try {
    if ($isAdmin) {
        $totalCampaigns = $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
        $activeCampaigns = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'active'")->fetchColumn();
        $totalBudget = $pdo->query("SELECT SUM(budget) FROM campaigns")->fetchColumn() ?: 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE organization_id = ?");
        $stmt->execute([$userOrgId]);
        $totalCampaigns = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE organization_id = ? AND status = 'active'");
        $stmt->execute([$userOrgId]);
        $activeCampaigns = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT SUM(budget) FROM campaigns WHERE organization_id = ?");
        $stmt->execute([$userOrgId]);
        $totalBudget = $stmt->fetchColumn() ?: 0;
    }
} catch (Exception $e) {
    $totalCampaigns = 0;
    $activeCampaigns = 0;
    $totalBudget = 0;
}

?>

    <!-- Header Section -->
    <div class="header-section">
        <div style="background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="sidebarToggle" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; display: none;">☰</button>
                <h1 style="margin: 0; font-size: 1.5rem;">📊 Marketing Dashboard</h1>
            </div>
            <?= $menuSystem->renderHeaderMenu() ?>
            <div class="profile-dropdown">
                <span style="cursor: pointer; padding: 8px 12px; border-radius: 4px; background: rgba(255,255,255,0.1);">
                    <?= htmlspecialchars($currentUser) ?> (<?= htmlspecialchars($userOrgId) ?>) ▼
                </span>
                <div class="profile-dropdown-content">
                    <?php if ($isAdmin): ?>
                        <a href="/admin/dashboard.php">⚙️ Admin Panel</a>
                    <?php endif; ?>
                    <a href="/web/logout.php">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Section -->
    <div class="sidebar-section">
        <?= $menuSystem->renderSidebar($currentPage) ?>
    </div>

    <!-- Main Content Section -->
    <div class="main-section">
        <!-- Marketing Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?= $totalCampaigns ?></h5>
                        <p class="card-text">Total Campaigns</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $activeCampaigns ?></h5>
                        <p class="card-text">Active Campaigns</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">$<?= number_format($totalBudget, 2) ?></h5>
                        <p class="card-text">Total Budget</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning">0</h5>
                        <p class="card-text">Leads Generated</p>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="mb-4">Marketing Tools</h2>

        <!-- Marketing Tools Grid -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">📢 Campaign Management</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Create and manage marketing campaigns across multiple channels.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Email campaigns</li>
                            <li><i class="fas fa-check text-success me-2"></i>Social media campaigns</li>
                            <li><i class="fas fa-check text-success me-2"></i>Budget tracking</li>
                            <li><i class="fas fa-check text-success me-2"></i>Performance analytics</li>
                        </ul>
                        <a href="/web/campaigns.php" class="btn btn-primary">Manage Campaigns</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">📧 Email Marketing</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Design and send professional email campaigns to your contacts.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Email templates</li>
                            <li><i class="fas fa-check text-success me-2"></i>Contact segmentation</li>
                            <li><i class="fas fa-check text-success me-2"></i>A/B testing</li>
                            <li><i class="fas fa-check text-success me-2"></i>Delivery tracking</li>
                        </ul>
                        <a href="/web/email_marketing.php" class="btn btn-primary">Email Marketing</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">📈 Analytics & Reports</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Track campaign performance and ROI with detailed analytics.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Campaign metrics</li>
                            <li><i class="fas fa-check text-success me-2"></i>Conversion tracking</li>
                            <li><i class="fas fa-check text-success me-2"></i>ROI analysis</li>
                            <li><i class="fas fa-check text-success me-2"></i>Custom reports</li>
                        </ul>
                        <a href="/web/analytics.php" class="btn btn-primary">View Analytics</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">🎯 Lead Generation</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Generate and nurture leads through automated marketing workflows.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Landing pages</li>
                            <li><i class="fas fa-check text-success me-2"></i>Lead scoring</li>
                            <li><i class="fas fa-check text-success me-2"></i>Automated nurturing</li>
                            <li><i class="fas fa-check text-success me-2"></i>Lead qualification</li>
                        </ul>
                        <a href="/web/lead_generation.php" class="btn btn-primary">Lead Generation</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-primary w-100" disabled>
                            <i class="fas fa-plus me-2"></i>New Campaign
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-success w-100" disabled>
                            <i class="fas fa-envelope me-2"></i>Send Email
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-info w-100" disabled>
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-warning w-100" disabled>
                            <i class="fas fa-users me-2"></i>Manage Leads
                        </button>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <strong>Coming Soon:</strong> Full marketing automation features are under development.
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <div class="footer-section">
        <div style="padding: 15px 20px; text-align: center; color: #666;">
            © 2024 ZeroAI CRM. All rights reserved.
        </div>
    </div>
</div>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const container = document.getElementById('layoutContainer');
    const sidebar = document.querySelector('.sidebar-section');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
    } else {
        container.classList.toggle('sidebar-closed');
    }
});

// Show mobile toggle on small screens
function updateSidebarToggle() {
    const toggle = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.style.display = window.innerWidth <= 768 ? 'block' : 'none';
    }
}

window.addEventListener('resize', updateSidebarToggle);
updateSidebarToggle();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>