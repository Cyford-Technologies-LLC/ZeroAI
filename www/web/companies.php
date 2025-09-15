<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Companies - ZeroAI CRM';
$currentPage = 'companies';

// Get user's organization_id
$userOrgId = 1; // Default
try {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT organization_id FROM users WHERE username = ?");
    $stmt->execute([$currentUser]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userOrgId = $user['organization_id'] ?? 1;
    }
} catch (Exception $e) {
    // Use default
}

// Handle form submission
if ($_POST && isset($_POST['name'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO companies (name, ein, business_id, email, phone, address, industry, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['ein'], $_POST['business_id'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['industry'], $userOrgId, $currentUser]);
        $success = "Company added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Use existing Company model
try {
    require_once __DIR__ . '/../src/Models/Company.php';
    $companyModel = new \ZeroAI\Models\Company();
    
    if ($isAdmin) {
        $companies = $companyModel->getAll();
    } else {
        $companies = $companyModel->findByTenant($userOrgId);
    }
} catch (Exception $e) {
    $companies = [];
    $error = "Database error: " . $e->getMessage();
}

include __DIR__ . '/includes/header.php';
?>

<nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="container-fluid">
        <a class="navbar-brand" href="/web/index.php" style="color: white; font-weight: bold;">🏢 ZeroAI CRM</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'crm_dashboard' ? 'active' : '' ?>" href="/web/index.php" style="color: rgba(255,255,255,0.9);">📊 Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'companies' ? 'active' : '' ?>" href="/web/companies.php" style="color: rgba(255,255,255,0.9);">🏢 Companies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'contacts' ? 'active' : '' ?>" href="/web/contacts.php" style="color: rgba(255,255,255,0.9);">👥 Contacts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'sales' ? 'active' : '' ?>" href="/web/sales.php" style="color: rgba(255,255,255,0.9);">💰 Sales</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>" href="/web/projects.php" style="color: rgba(255,255,255,0.9);">📋 Projects</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'tasks' ? 'active' : '' ?>" href="/web/tasks.php" style="color: rgba(255,255,255,0.9);">✅ Tasks</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <span class="navbar-text" style="color: rgba(255,255,255,0.9);">👤 <?= htmlspecialchars($currentUser) ?></span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="btn btn-secondary btn-sm">⚙️ Admin</a><?php endif; ?>
                <a href="/web/ai_workshop.php" class="btn btn-info btn-sm">🤖 AI</a>
                <a href="/web/logout.php" class="btn btn-danger btn-sm">🚪 Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Add New Company</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">EIN</label>
                                <input type="text" class="form-control" name="ein" placeholder="XX-XXXXXXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Business ID</label>
                                <input type="text" class="form-control" name="business_id">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="website" placeholder="https://">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">LinkedIn</label>
                                <input type="url" class="form-control" name="linkedin" placeholder="https://linkedin.com/company/">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Industry</label>
                                <select class="form-select" name="industry">
                                    <option value="">Select Industry</option>
                                    <option value="Technology">Technology</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Manufacturing">Manufacturing</option>
                                    <option value="Retail">Retail</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">About the Company</label>
                                <textarea class="form-control" name="about" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Street Address</label>
                                <input type="text" class="form-control" name="street">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Street Address 2</label>
                                <input type="text" class="form-control" name="street2">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" name="zip">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="USA">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Company</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Companies List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($companies)): ?>
                        <p>No companies found. Add your first company above.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Industry</th>
                                        <?php if ($isAdmin): ?><th>Created By</th><th>Org ID</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companies as $company): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($company['name']) ?></td>
                                            <td><?= htmlspecialchars($company['email']) ?></td>
                                            <td><?= htmlspecialchars($company['phone']) ?></td>
                                            <td><?= htmlspecialchars($company['industry']) ?></td>
                                            <?php if ($isAdmin): ?>
                                                <td><?= htmlspecialchars($company['created_by'] ?? 'Unknown') ?></td>
                                                <td><?= htmlspecialchars($company['organization_id'] ?? '1') ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>