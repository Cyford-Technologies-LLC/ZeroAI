<?php
$pageTitle = 'Users - ZeroAI CRM';
$currentPage = 'users';
include __DIR__ . '/includes/header.php';

$companyId = $_GET['company_id'] ?? null;

// Create user_company_access table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_company_access (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        company_id INTEGER NOT NULL,
        role VARCHAR(20) DEFAULT 'viewer',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )");
} catch (Exception $e) {}

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_company_access (user_id, company_id, role) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['user_id'], $companyId, $_POST['role']]);
        $success = "User access added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get company info if specified
$company = null;
if ($companyId) {
    try {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND organization_id = ?");
            $stmt->execute([$companyId, $userOrgId]);
        }
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Company not found";
    }
}

// Get users with access to this company
try {
    if ($companyId) {
        $sql = "SELECT u.*, uca.role as company_role FROM users u 
                JOIN user_company_access uca ON u.id = uca.user_id 
                WHERE uca.company_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$companyId]);
        $companyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $companyUsers = [];
    }
    
    // Get all users for dropdown (filtered by organization for non-admins)
    if ($isAdmin) {
        $allUsers = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE organization_id = ? ORDER BY username");
        $stmt->execute([$userOrgId]);
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $companyUsers = [];
    $allUsers = [];
    $error = "Database error: " . $e->getMessage();
}

?>

    <!-- Header Section -->
    <div class="header-section">
        <div style="background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="sidebarToggle" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; display: none;">☰</button>
                <h1 style="margin: 0; font-size: 1.5rem;">👥 Users<?= $company ? ' - ' . htmlspecialchars($company['name']) : '' ?></h1>
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

        <?php if ($company): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">User Access Management</h2>
                <a href="/web/companies.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Companies
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add User Access to <?= htmlspecialchars($company['name']) ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">User *</label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="viewer">Viewer</option>
                                    <option value="editor">Editor</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add User Access</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Users with Access (<?= count($companyUsers) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($companyUsers)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No users have access to this company yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Company Role</th>
                                        <th>System Role</th>
                                        <th>Access Granted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companyUsers as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $user['company_role'] === 'admin' ? 'danger' : ($user['company_role'] === 'editor' ? 'warning' : 'info') ?>">
                                                    <?= htmlspecialchars(ucfirst($user['company_role'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?></td>
                                            <td><?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?></td>
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
                    <h5 class="mb-0">No Company Selected</h5>
                </div>
                <div class="card-body text-center py-4">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h4>Select a Company</h4>
                    <p class="text-muted">Please select a company from the Companies page to manage user access.</p>
                    <a href="/web/companies.php" class="btn btn-primary">Go to Companies</a>
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