<?php
// Block demo users from user management
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'demo') {
    header('Location: /admin/dashboard.php?error=Demo users cannot access user management');
    exit;
}

require_once '../src/Core/UserManager.php';
require_once '../src/Core/Logger.php';

$logger = \ZeroAI\Core\Logger::getInstance();
$userManager = new \ZeroAI\Core\UserManager();
$message = '';
$error = '';

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

$users = $userManager->getAllUsers() ?: [];

$pageTitle = 'User Management - ZeroAI';
$currentPage = 'users';
include __DIR__ . '/includes/header.php';
?>

<h1 class="mb-4">👥 User Management</h1>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">➕ Create New User</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email (Optional)</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="demo">Demo (View Only)</option>
                            <option value="frontend">Frontend User</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Permissions</label>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="view_agents" id="perm1">
                            <label class="form-check-label" for="perm1">View Agents</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_agents" id="perm2">
                            <label class="form-check-label" for="perm2">Manage Agents</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="view_crews" id="perm3">
                            <label class="form-check-label" for="perm3">View Crews</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_crews" id="perm4">
                            <label class="form-check-label" for="perm4">Manage Crews</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="view_logs" id="perm5">
                            <label class="form-check-label" for="perm5">View Logs</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="system_config" id="perm6">
                            <label class="form-check-label" for="perm6">System Config</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success">✅ Create User</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">👥 Existing Users (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email'] ?? 'No email') ?></td>
                        <td>
                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'demo' ? 'secondary' : 'primary') ?>">
                                <?= ucfirst($user['role'] ?? 'user') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= ($user['status'] ?? 'active') === 'active' ? 'success' : 'warning' ?>">
                                <?= ($user['status'] ?? 'active') === 'active' ? '✅ Active' : '❌ Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($user['username'] !== 'admin' || $_SESSION['user_name'] === 'admin'): ?>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email'] ?? '') ?>', '<?= $user['role'] ?? 'user' ?>', '<?= $user['status'] ?? 'active' ?>')" 
                                            class="btn btn-outline-warning btn-sm">✏️ Edit</button>
                                    <button onclick="changePassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                            class="btn btn-outline-secondary btn-sm">🔑 Password</button>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <em>No users found. There may be a database connection issue.</em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✏️ Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="edit_username" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="demo">Demo (View Only)</option>
                            <option value="frontend">Frontend User</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">✅ Update User</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🔑 Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="user_id" id="password_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="password_username" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">✅ Change Password</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/admin/js/users.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>