<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Contacts - ZeroAI CRM';
$currentPage = 'contacts';

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO contacts (company_id, first_name, middle_name, last_name, email, phone, mobile, position, department, address, city, state, zip_code, country, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([
            $_POST['company_id'], $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'],
            $_POST['email'], $_POST['phone'], $_POST['mobile'], $_POST['position'], $_POST['department'],
            $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip_code'], $_POST['country'], $currentUser
        ]);
        $success = "Contact added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get contacts with company info
try {
    $sql = "SELECT c.*, comp.name as company_name FROM contacts c 
            LEFT JOIN companies comp ON c.company_id = comp.id 
            ORDER BY c.last_name, c.first_name";
    $contacts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for dropdown
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $contacts = [];
    $companies = [];
    $error = "Database error: " . $e->getMessage();
}

include __DIR__ . '/includes/header.php';
?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Contacts</div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/projects.php">Projects</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser) ?>!</span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/ai_workshop.php" class="header-btn">🤖 AI Workshop</a>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="sidebar-group">
                <h3>CRM</h3>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/contacts.php" class="active">Contacts</a>
                <a href="/web/projects.php">Projects</a>
                <a href="/web/tasks.php">Tasks</a>
            </div>
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
                <h3>Add New Contact</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Company</label>
                        <select name="company_id">
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <label>Mobile</label>
                        <input type="text" name="mobile">
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address"></textarea>
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
                        <input type="text" name="zip_code">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" value="USA">
                    </div>
                    <button type="submit" class="btn">Add Contact</button>
                </form>
            </div>

            <div class="card">
                <h3>Contacts List</h3>
                <?php if (empty($contacts)): ?>
                    <p>No contacts found. Add your first contact above.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Email</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
                                    <td><?= htmlspecialchars($contact['company_name'] ?? 'No Company') ?></td>
                                    <td><?= htmlspecialchars($contact['position']) ?></td>
                                    <td><?= htmlspecialchars($contact['email']) ?></td>
                                    <td><?= htmlspecialchars($contact['phone']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
</body>
</html>