<?php
$pageTitle = 'Bugs - ZeroAI CRM';
$currentPage = 'bugs';
include __DIR__ . '/includes/header.php';

$projectId = $_GET['project_id'] ?? null;

// Create bugs table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bugs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        severity VARCHAR(20) DEFAULT 'medium',
        status VARCHAR(20) DEFAULT 'open',
        assigned_to VARCHAR(100),
        reporter VARCHAR(100),
        organization_id VARCHAR(10),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id)
    )");
    
    // Add organization_id column if not exists
    try {
        $pdo->exec("ALTER TABLE bugs ADD COLUMN organization_id VARCHAR(10)");
    } catch (Exception $e) {}
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

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

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'create') {
    try {
        $stmt = $pdo->prepare("INSERT INTO bugs (project_id, title, description, severity, assigned_to, reporter, organization_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $projectId,
            $_POST['title'],
            $_POST['description'],
            $_POST['severity'],
            $_POST['assigned_to'] ?? null,
            $currentUser,
            $userOrgId
        ]);
        $success = "Bug reported successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get bugs for this project
try {
    if ($projectId) {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT * FROM bugs WHERE project_id = ? ORDER BY created_at DESC");
            $stmt->execute([$projectId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM bugs WHERE project_id = ? AND organization_id = ? ORDER BY created_at DESC");
            $stmt->execute([$projectId, $userOrgId]);
        }
        $bugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $bugs = [];
    }
} catch (Exception $e) {
    $bugs = [];
    $error = "Database error: " . $e->getMessage();
}

?>

    <!-- Header Section -->
    <div class="header-section">
        <div style="background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="sidebarToggle" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; display: none;">☰</button>
                <h1 style="margin: 0; font-size: 1.5rem;">🐛 Bugs<?= $project ? ' - ' . htmlspecialchars($project['name']) : '' ?></h1>
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
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($project): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Bug Tracking</h2>
                <div>
                    <a href="/web/projects.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                    <button class="btn btn-primary" onclick="toggleCollapse('addBugForm')">
                        <i class="fas fa-plus"></i> Report Bug
                    </button>
                </div>
            </div>

            <!-- Add Bug Form -->
            <div class="card mb-4 collapse" id="addBugForm" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">Report New Bug</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Bug Title *</label>
                                <input type="text" class="form-control" name="title" required placeholder="Brief description of the bug">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Severity</label>
                                <select class="form-select" name="severity">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assigned To</label>
                                <input type="text" class="form-control" name="assigned_to" placeholder="Developer name">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" placeholder="Detailed description of the bug, steps to reproduce, expected vs actual behavior..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Report Bug</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleCollapse('addBugForm')">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Bugs List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Bugs for <?= htmlspecialchars($project['name']) ?> (<?= count($bugs) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($bugs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bug fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No bugs reported yet. Report your first bug above.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Reporter</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bugs as $bug): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($bug['title']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $bug['severity'] === 'critical' ? 'danger' : ($bug['severity'] === 'high' ? 'warning' : ($bug['severity'] === 'medium' ? 'info' : 'secondary')) ?>">
                                                    <?= htmlspecialchars(ucfirst($bug['severity'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $bug['status'] === 'closed' ? 'success' : ($bug['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $bug['status']))) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($bug['assigned_to'] ?? 'Unassigned') ?></td>
                                            <td><?= htmlspecialchars($bug['reporter']) ?></td>
                                            <td><?= date('M j, Y', strtotime($bug['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
                    <p class="text-muted">Please select a project from the Projects page to view and manage bugs.</p>
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
function toggleCollapse(id) {
    const element = document.getElementById(id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}

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