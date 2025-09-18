<?php
$pageTitle = 'Releases - ZeroAI CRM';
$currentPage = 'releases';
include __DIR__ . '/includes/header.php';

$projectId = $_GET['project_id'] ?? null;

// Get project info
$project = null;
if ($projectId) {
    try {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND organization_id = ?");
            $stmt->execute([$projectId, $userOrgId]);
        }
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Project not found";
    }
}

?>

    <!-- Header Section -->
    <div class="header-section">
        <div style="background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="sidebarToggle" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; display: none;">☰</button>
                <h1 style="margin: 0; font-size: 1.5rem;">🚀 Releases<?= $project ? ' - ' . htmlspecialchars($project['name']) : '' ?></h1>
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
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($project): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Release Management</h2>
                <div>
                    <a href="/web/projects.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                    <button class="btn btn-primary" disabled>
                        <i class="fas fa-plus"></i> New Release
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Releases for <?= htmlspecialchars($project['name']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Coming Soon:</strong> Release management functionality is under development.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Planned Features:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Version tracking</li>
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Release notes</li>
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Deployment status</li>
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Rollback capabilities</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Release Pipeline:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-arrow-right text-primary me-2"></i>Development → Staging → Production</li>
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Automated testing integration</li>
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Approval workflows</li>
                                <li><i class="fas fa-check-circle text-muted me-2"></i>Release scheduling</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">No Project Selected</h5>
                </div>
                <div class="card-body text-center py-4">
                    <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                    <h4>Select a Project</h4>
                    <p class="text-muted">Please select a project from the Projects page to manage releases.</p>
                    <a href="/web/projects.php" class="btn btn-primary">Go to Projects</a>
                </div>
            </div>
        <?php endif; ?>
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