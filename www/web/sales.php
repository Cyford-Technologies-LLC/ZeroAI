<?php
$pageTitle = 'Sales - ZeroAI CRM';
$currentPage = 'sales';
include __DIR__ . '/includes/header.php';

// Create sales table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER,
        contact_id INTEGER,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        amount DECIMAL(10,2),
        status VARCHAR(20) DEFAULT 'lead',
        probability INTEGER DEFAULT 0,
        expected_close_date DATE,
        actual_close_date DATE,
        organization_id VARCHAR(10),
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id),
        FOREIGN KEY (contact_id) REFERENCES contacts(id)
    )");
    
    // Add organization_id column if not exists
    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN organization_id VARCHAR(10)");
    } catch (Exception $e) {}
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO sales (company_id, contact_id, title, description, amount, status, probability, expected_close_date, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['company_id'] ?: null,
                $_POST['contact_id'] ?: null,
                $_POST['title'],
                $_POST['description'],
                $_POST['amount'] ?: null,
                $_POST['status'],
                $_POST['probability'],
                $_POST['expected_close_date'] ?: null,
                $userOrgId,
                $currentUser
            ]);
            $success = "Sales opportunity created successfully!";
        }
        
        if ($_POST['action'] === 'update_status') {
            $actual_close_date = $_POST['status'] === 'won' || $_POST['status'] === 'lost' ? "datetime('now')" : 'NULL';
            $stmt = $pdo->prepare("UPDATE sales SET status = ?, actual_close_date = {$actual_close_date}, updated_at = datetime('now') WHERE id = ? AND organization_id = ?");
            $stmt->execute([$_POST['status'], $_POST['sale_id'], $userOrgId]);
            $success = "Sales status updated!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get sales with multi-tenant filtering
try {
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT s.*, c.name as company_name, ct.first_name, ct.last_name FROM sales s 
                            LEFT JOIN companies c ON s.company_id = c.id 
                            LEFT JOIN contacts ct ON s.contact_id = ct.id 
                            ORDER BY s.created_at DESC");
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT s.*, c.name as company_name, ct.first_name, ct.last_name FROM sales s 
                              LEFT JOIN companies c ON s.company_id = c.id 
                              LEFT JOIN contacts ct ON s.contact_id = ct.id 
                              WHERE s.organization_id = ? ORDER BY s.created_at DESC");
        $stmt->execute([$userOrgId]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get companies and contacts for dropdowns
    if ($isAdmin) {
        $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $contacts = $pdo->query("SELECT id, first_name, last_name, company_id FROM contacts ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name");
        $stmt->execute([$userOrgId]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, company_id FROM contacts WHERE organization_id = ? ORDER BY first_name, last_name");
        $stmt->execute([$userOrgId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $sales = [];
    $companies = [];
    $contacts = [];
    $error = "Database error: " . $e->getMessage();
}

// Calculate statistics
$totalValue = array_sum(array_column($sales, 'amount'));
$wonDeals = array_filter($sales, fn($s) => $s['status'] === 'won');
$wonValue = array_sum(array_column($wonDeals, 'amount'));

?>

    <!-- Header Section -->
    <div class="header-section">
        <div style="background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="sidebarToggle" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; display: none;">☰</button>
                <h1 style="margin: 0; font-size: 1.5rem;">💰 Sales Pipeline</h1>
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

        <!-- Sales Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?= count($sales) ?></h5>
                        <p class="card-text">Total Opportunities</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">$<?= number_format($totalValue, 2) ?></h5>
                        <p class="card-text">Pipeline Value</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= count($wonDeals) ?></h5>
                        <p class="card-text">Won Deals</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success">$<?= number_format($wonValue, 2) ?></h5>
                        <p class="card-text">Won Value</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Sales Opportunities</h2>
            <button class="btn btn-primary" onclick="toggleCollapse('addSaleForm')">
                <i class="fas fa-plus"></i> Add Opportunity
            </button>
        </div>

        <!-- Add Sale Form -->
        <div class="card mb-4 collapse" id="addSaleForm" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">Add New Sales Opportunity</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" required placeholder="Opportunity title">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-control" name="amount" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company</label>
                            <select class="form-select" name="company_id">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact</label>
                            <select class="form-select" name="contact_id">
                                <option value="">Select Contact</option>
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?= $contact['id'] ?>"><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="lead">Lead</option>
                                <option value="qualified">Qualified</option>
                                <option value="proposal">Proposal</option>
                                <option value="negotiation">Negotiation</option>
                                <option value="won">Won</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Probability (%)</label>
                            <input type="number" class="form-control" name="probability" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Close Date</label>
                            <input type="date" class="form-control" name="expected_close_date">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Opportunity details..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Opportunity</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleCollapse('addSaleForm')">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Sales List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Sales Pipeline (<?= count($sales) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sales)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No sales opportunities found. Add your first opportunity above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Probability</th>
                                    <th>Expected Close</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sale['title']) ?></td>
                                        <td><?= htmlspecialchars($sale['company_name'] ?? 'No Company') ?></td>
                                        <td><?= htmlspecialchars(($sale['first_name'] ?? '') . ' ' . ($sale['last_name'] ?? '')) ?></td>
                                        <td>$<?= number_format($sale['amount'] ?? 0, 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $sale['status'] === 'won' ? 'success' : ($sale['status'] === 'lost' ? 'danger' : ($sale['status'] === 'negotiation' ? 'warning' : 'info')) ?>">
                                                <?= htmlspecialchars(ucfirst($sale['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= $sale['probability'] ?>%</td>
                                        <td><?= $sale['expected_close_date'] ? date('M j, Y', strtotime($sale['expected_close_date'])) : '' ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                                                    <option value="lead" <?= $sale['status'] === 'lead' ? 'selected' : '' ?>>Lead</option>
                                                    <option value="qualified" <?= $sale['status'] === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                                                    <option value="proposal" <?= $sale['status'] === 'proposal' ? 'selected' : '' ?>>Proposal</option>
                                                    <option value="negotiation" <?= $sale['status'] === 'negotiation' ? 'selected' : '' ?>>Negotiation</option>
                                                    <option value="won" <?= $sale['status'] === 'won' ? 'selected' : '' ?>>Won</option>
                                                    <option value="lost" <?= $sale['status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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