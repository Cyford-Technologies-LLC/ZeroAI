<?php 
$pageTitle = 'User Management - ZeroAI';
$currentPage = 'users';

use ZeroAI\Services\UserService;

$userService = new UserService();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Create user
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? 'user';
        
        $userData = ['username' => $username, 'password' => $password, 'role' => $role];
        if ($userService->createUser($userData)) {
            $message = "User '$username' created successfully!";
        } else {
            $error = "Failed to create user. Username may already exist.";
        }
    } elseif (isset($_POST['delete_id'])) {
        // Delete user
        $id = (int)$_POST['delete_id'];
        if ($userService->deleteUser($id)) {
            $message = "User deleted successfully!";
        } else {
            $error = "Failed to delete user.";
        }
    }
}

// Get all users
$users = $userService->getAllUsers();

include __DIR__ . '/includes/header.php';
?>

<h1>User Management</h1>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
    
<div class="card">
    <h3>Create New User</h3>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role">
            <option value="user">Frontend Portal User</option>
            <option value="admin">Admin User</option>
        </select>
        <button type="submit" class="btn-success">Create User</button>
    </form>
</div>
    
<div class="card">
    <h3>User List</h3>
    <?php if (!empty($users)): ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 10px; border: 1px solid #ddd;">ID</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Username</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Role</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Created</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= $user['id'] ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($user['username']) ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">
                        <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?')">
                            <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn-danger" style="padding: 4px 8px; font-size: 12px;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No users found. Create the first user above.</p>
    <?php endif; ?>
</div>

<style>
.alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
.badge-admin { background: #dc3545; color: white; }
.badge-user { background: #007bff; color: white; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>