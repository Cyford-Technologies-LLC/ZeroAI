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
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Companies</div>
            <div class="ai-workshop">
                <a href="/web/ai_workshop.php" class="header-btn">🤖 AI Workshop</a>
            </div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php" class="active">Companies</a>
                <a href="/web/projects.php">Projects</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser) ?>!</span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="sidebar-group">
                <h3>CRM</h3>
                <a href="/web/companies.php" class="active">Companies</a>
                <?php if (isset($_GET['company_id'])): ?>
                    <a href="/web/employees.php?company_id=<?= $_GET['company_id'] ?>" style="padding-left: 40px;">👥 Employees</a>
                    <a href="/web/contacts.php?company_id=<?= $_GET['company_id'] ?>" style="padding-left: 40px;">📞 Contacts</a>
                    <a href="/web/locations.php?company_id=<?= $_GET['company_id'] ?>" style="padding-left: 40px;">📍 Locations</a>
                <?php else: ?>
                    <a href="/web/contacts.php">Contacts</a>
                <?php endif; ?>
                <a href="/web/projects.php">Projects</a>
                <?php if (isset($_GET['project_id'])): ?>
                    <a href="/web/tasks.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">📋 Tasks</a>
                    <a href="/web/features.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">✨ Features</a>
                    <a href="/web/bugs.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">🐛 Bugs</a>
                <?php else: ?>
                    <a href="/web/tasks.php">Tasks</a>
                <?php endif; ?>
            </div>
            <?php if ($isAdmin): ?>

            <?php endif; ?>
        </div>
        <div class="main-content">
            <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Add New Company</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>EIN</label>
                    <input type="text" name="ein" placeholder="XX-XXXXXXX">
                </div>
                <div class="form-group">
                    <label>Business ID</label>
                    <input type="text" name="business_id">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>Website</label>
                    <input type="url" name="website" placeholder="https://">
                </div>
                <div class="form-group">
                    <label>LinkedIn</label>
                    <input type="url" name="linkedin" placeholder="https://linkedin.com/company/">
                </div>
                <div class="form-group">
                    <label>Industry</label>
                    <select name="industry">
                        <option value="">Select Industry</option>
                        <option value="Technology">Technology</option>
                        <option value="Healthcare">Healthcare</option>
                        <option value="Finance">Finance</option>
                        <option value="Manufacturing">Manufacturing</option>
                        <option value="Retail">Retail</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>About the Company</label>
                    <textarea name="about"></textarea>
                </div>
                <div class="form-group">
                    <label>Street Address</label>
                    <input type="text" name="street">
                </div>
                <div class="form-group">
                    <label>Street Address 2</label>
                    <input type="text" name="street2">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state">
                </div>
                <div class="form-group">
                    <label>ZIP Code</label>
                    <input type="text" name="zip">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country" value="USA">
                </div>
                <button type="submit" class="btn">Add Company</button>
            </form>
        </div>

        <div class="card">
            <h3>Companies List</h3>
            <?php if (empty($companies)): ?>
                <p>No companies found. Add your first company above or <a href="/web/setup_crm.php">setup the database</a>.</p>
            <?php else: ?>
                <table>
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
            <?php endif; ?>
        </div>
        </div>
    </div>
</body>
</html>