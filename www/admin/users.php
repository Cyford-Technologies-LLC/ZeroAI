<?php
require_once '../src/Core/AuthMiddleware.php';
require_once '../src/Core/UserManager.php';

$auth = new \ZeroAI\Core\AuthMiddleware();
$auth->requireAuth();
$auth->requireRole('admin'); // Only admins can manage users

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
            $permissions = $_POST['permissions'] ?? [];
            
            if (empty($username) || empty($password)) {
                throw new Exception('Username and password are required');
            }
            
            if ($userManager->createUser($username, $password, $role, $permissions)) {
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

use ZeroAI\Services\UserService;
use ZeroAI\Services\GroupService;

$userService = new UserService();
$groupService = new GroupService();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Create user
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? 'user';
        
        $userData = [
            'username' => $username, 
            'password' => $password, 
            'role' => $role,
            'group' => $_POST['group'] ?? null
        ];
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
    } elseif (isset($_POST['add_to_group'])) {
        // Add user to group
        $userId = (int)$_POST['user_id'];
        $groupId = (int)$_POST['group_id'];
        if ($groupService->addUserToGroup($userId, $groupId)) {
            $message = "User added to group successfully!";
        } else {
            $error = "Failed to add user to group.";
        }
    } elseif (isset($_POST['remove_from_group'])) {
        // Remove user from group
        $userId = (int)$_POST['user_id'];
        $groupId = (int)$_POST['group_id'];
        if ($groupService->removeUserFromGroup($userId, $groupId)) {
            $message = "User removed from group successfully!";
        } else {
            $error = "Failed to remove user from group.";
        }
    }
}

// Get all users and groups
$users = $userService->getAllUsers();
$groups = $groupService->getAllGroups();

include __DIR__ . '/includes/header.php';
?>

<<<<<<< HEAD
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
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>
        <select name="group">
            <option value="">Select Group (Optional)</option>
            <?php foreach ($groups as $group): ?>
                <option value="<?= $group['id'] ?>"><?= $group['name'] ?> - <?= $group['description'] ?></option>
            <?php endforeach; ?>
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
                    <th style="padding: 10px; border: 1px solid #ddd;">Groups</th>
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
                        <?php 
                        $userGroups = $groupService->getUserGroups($user['id']);
                        foreach ($userGroups as $group): ?>
                            <span class="badge badge-<?= $group['name'] === 'admin' ? 'admin' : 'user' ?>"><?= $group['name'] ?></span>
                        <?php endforeach; ?>
                        <br><small>
                            <form method="POST" style="display: inline; margin-top: 5px;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="group_id" style="font-size: 11px;">
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= $group['id'] ?>"><?= $group['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="add_to_group" style="font-size: 10px; padding: 2px 4px;">Add</button>
                            </form>
                        </small>
                    </td>
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
=======
<h1>👥 User Management</h1>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <h3>Create New User</h3>
    <form method="POST">
        <input type="hidden" name="action" value="create_user">
        
        <label>Username:</label>
        <input type="text" name="username" required>
        
        <label>Password:</label>
        <input type="password" name="password" required>
        
        <label>Role:</label>
        <select name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="demo">Demo (View Only)</option>
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
                        <button onclick="editUser(<?= $user['id'] ?>, '<?= $user['username'] ?>', '<?= $user['role'] ?>', '<?= $user['status'] ?? 'active' ?>')" 
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
            
            <label>Username:</label>
            <input type="text" id="edit_username" readonly style="background: #f8f9fa;">
            
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
>>>>>>> bd72799 (Added  better user management)

<?php include __DIR__ . '/includes/footer.php'; ?>