<?php
include __DIR__ . '/includes/header.php';


$pageTitle = 'Users - ZeroAI CRM';
$currentPage = 'users';
$companyId = $_GET['company_id'] ?? null;

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

// Get users with access to this company
try {
    $sql = "SELECT u.*, uca.role as company_role FROM users u 
            JOIN user_company_access uca ON u.id = uca.user_id 
            WHERE uca.company_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    $companyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all users for dropdown
    $allUsers = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $companyUsers = [];
    $allUsers = [];
    $error = "Database error: " . $e->getMessage();
}

?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Users<?= $company ? ' - ' . htmlspecialchars($company['name']) : '' ?></div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/contacts.php">Contacts</a>
                <a href="/web/projects.php">Projects</a>
                <a href="/web/tasks.php">Tasks</a>
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
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <?php if ($companyId): ?>
                    <a href="/web/users.php?company_id=<?= $companyId ?>" class="active" style="padding-left: 40px;">👥 Users</a>
                    <a href="/web/contacts.php?company_id=<?= $companyId ?>" style="padding-left: 40px;">📞 Contacts</a>
                <?php else: ?>
                    <a href="/web/contacts.php">Contacts</a>
                <?php endif; ?>
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

                <?php if ($company): ?>
                    <div class="card">
                        <h3>Add User Access to <?= htmlspecialchars($company['name']) ?></h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label>User</label>
                                <select name="user_id" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" required>
                                    <option value="viewer">Viewer</option>
                                    <option value="editor">Editor</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" class="btn">Add User Access</button>
                        </form>
                    </div>

                    <div class="card">
                        <h3>Users with Access</h3>
                        <?php if (empty($companyUsers)): ?>
                            <p>No users have access to this company yet.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>User Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companyUsers as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><?= htmlspecialchars($user['company_role']) ?></td>
                                            <td><?= htmlspecialchars($user['role']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3>No Company Selected</h3>
                        <p>Please select a company from the <a href="/web/companies.php">Companies</a> page to manage users.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>