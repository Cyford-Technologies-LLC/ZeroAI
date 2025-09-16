<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

// Block demo users from user management
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'demo') {
    header('Location: /admin/dashboard.php?error=Demo users cannot access user management');
    exit;
}

require_once '../src/Core/UserManager.php';

$userManager = new \ZeroAI\Core\UserManager();
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create_user') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $email = trim($_POST['email']) ?: null;
            $permissions = $_POST['permissions'] ?? [];
            
            if (empty($username) || empty($password)) {
                throw new Exception('Username and password are required');
            }
            
            if ($userManager->createUser($username, $password, $role, $permissions, $email)) {
                $message = "User '$username' created successfully";
            } else {
                $error = "Failed to create user";
            }
        }
        
        if ($_POST['action'] === 'update_user') {
            $userId = $_POST['user_id'];
            $data = [
                'role' => $_POST['role'],
                'status' => $_POST['status'],
                'permissions' => $_POST['permissions'] ?? []
            ];
            
            if ($userManager->updateUser($userId, $data)) {
                $message = "User updated successfully";
            } else {
                $error = "Failed to update user";
            }
        }
        
        if ($_POST['action'] === 'change_password') {
            $userId = $_POST['user_id'];
            $newPassword = $_POST['new_password'];
            
            if ($userManager->changePassword($userId, $newPassword)) {
                $message = "Password changed successfully";
            } else {
                $error = "Failed to change password";
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$users = $userManager->getAllUsers();

$pageTitle = 'User Management - ZeroAI';
$currentPage = 'users';
include __DIR__ . '/includes/header.php';
?>

<h1>👥 User Management</h1>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">
    <h3>Create New User</h3>
    <form method="POST">
        <input type="hidden" name="action" value="create_user">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8') ?>">
        
        <label>Username:</label>
        <input type="text" name="username" required>
        
        <label>Email (Optional):</label>
        <input type="email" name="email">
        
        <label>Password:</label>
        <input type="password" name="password" required>
        
        <label>Role:</label>
        <select name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="demo">Demo (View Only)</option>
            <option value="frontend">Frontend User</option>
        </select>
        
        <label>Permissions:</label>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0;">
            <label><input type="checkbox" name="permissions[]" value="view_agents"> View Agents</label>
            <label><input type="checkbox" name="permissions[]" value="manage_agents"> Manage Agents</label>
            <label><input type="checkbox" name="permissions[]" value="view_crews"> View Crews</label>
            <label><input type="checkbox" name="permissions[]" value="manage_crews"> Manage Crews</label>
            <label><input type="checkbox" name="permissions[]" value="view_logs"> View Logs</label>
            <label><input type="checkbox" name="permissions[]" value="system_config"> System Config</label>
        </div>
        
        <button type="submit" class="btn-success">Create User</button>
    </form>
</div>

<div class="card">
    <h3>Existing Users</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding: 10px; border: 1px solid #ddd;">Username</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Email</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Role</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Status</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Last Login</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($user['username']) ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($user['email'] ?? '') ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                        background: <?= $user['role'] === 'admin' ? '#dc3545' : ($user['role'] === 'demo' ? '#6c757d' : '#007bff') ?>; 
                        color: white;">
                        <?= ucfirst($user['role']) ?>
                    </span>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?= ($user['status'] ?? 'active') === 'active' ? '✅ Active' : '❌ Inactive' ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php if ($user['username'] !== 'admin' || $_SESSION['user_name'] === 'admin'): ?>
                        <button onclick="editUser(<?= $user['id'] ?>, '<?= $user['username'] ?>', '<?= htmlspecialchars($user['email'] ?? '') ?>', '<?= $user['role'] ?>', '<?= $user['status'] ?? 'active' ?>')" 
                                class="btn-warning" style="font-size: 12px; padding: 4px 8px;">Edit</button>
                        <button onclick="changePassword(<?= $user['id'] ?>, '<?= $user['username'] ?>')" 
                                class="btn-secondary" style="font-size: 12px; padding: 4px 8px;">Password</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Edit User Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 400px;">
        <h3>Edit User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8') ?>">
            
            <label>Username:</label>
            <input type="text" id="edit_username" readonly style="background: #f8f9fa;">
            
            <label>Email:</label>
            <input type="email" name="email" id="edit_email">
            
            <label>Role:</label>
            <select name="role" id="edit_role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
                <option value="demo">Demo (View Only)</option>
            </select>
            
            <label>Status:</label>
            <select name="status" id="edit_status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            
            <div style="margin-top: 15px;">
                <button type="submit" class="btn-success">Update User</button>
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 400px;">
        <h3>Change Password</h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" id="password_user_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8') ?>">
            
            <label>Username:</label>
            <input type="text" id="password_username" readonly style="background: #f8f9fa;">
            
            <label>New Password:</label>
            <input type="password" name="new_password" required>
            
            <div style="margin-top: 15px;">
                <button type="submit" class="btn-success">Change Password</button>
                <button type="button" onclick="closePasswordModal()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(id, username, email, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'block';
}

function changePassword(id, username) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('password_username').value = username;
    document.getElementById('passwordModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>         <input type="hidden" name="user_id" id="password_user_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8') ?>">
            
            <label>Username:</label>
            <input type="text" id="password_username" readonly style="background: #f8f9fa;">
            
            <label>New Password:</label>
            <input type="password" name="new_password" required>
            
            <div style="margin-top: 15px;">
                <button type="submit" class="btn-success">Change Password</button>
                <button type="button" onclick="closePasswordModal()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(id, username, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'block';
}

function changePassword(id, username) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('password_username').value = username;
    document.getElementById('passwordModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


