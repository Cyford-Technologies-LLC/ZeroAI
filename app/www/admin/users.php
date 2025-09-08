<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([
                    $_POST['username'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $_POST['role']
                ]);
                $success = "User created successfully";
                break;
                
            case 'delete':
                if ($_POST['username'] !== 'admin') {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
                    $stmt->execute([$_POST['username']]);
                    $success = "User deleted successfully";
                }
                break;
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY role DESC, username");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .user-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .admin-user { background: #e3f2fd; }
        button { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        input, select { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        .success { color: green; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Management</h1>
        <div class="nav">
            <a href="/admin/dashboard">Dashboard</a>
            <a href="/admin/users">Users</a>
            <a href="/admin/agents">Agents</a>
        </div>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Create New User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role">
                <option value="user">Web User</option>
                <option value="admin">Admin User</option>
            </select>
            <button type="submit" class="btn-success">Create User</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Existing Users</h3>
        <?php foreach ($users as $user): ?>
            <div class="user-item <?= $user['role'] === 'admin' ? 'admin-user' : '' ?>">
                <div>
                    <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                    <small>Role: <?= htmlspecialchars($user['role']) ?></small>
                    <?= $user['role'] === 'admin' ? '<span style="color: #007bff;"> (Admin)</span>' : '' ?>
                </div>
                <div>
                    <?php if ($user['username'] !== 'admin'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete user?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="username" value="<?= $user['username'] ?>">
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>