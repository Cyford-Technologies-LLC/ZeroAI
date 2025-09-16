<?php
// Handle AJAX requests first
if (isset($_POST['action']) && in_array($_POST['action'], ['edit_user', 'delete_user'])) {
    header('Content-Type: application/json');
    require_once '../src/Core/UserManager.php';
    
    $userManager = new \ZeroAI\Core\UserManager();
    
    if ($_POST['action'] === 'delete_user') {
        $userId = (int)$_POST['user_id'];
        if ($userManager->deleteUser($userId)) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'edit_user') {
        $userId = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']) ?: null;
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        if ($userManager->updateUser($userId, $username, $email, $role, $status)) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'toggle_status') {
        $userId = (int)$_POST['user_id'];
        $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
        
        if ($userManager->updateUserStatus($userId, $newStatus)) {
            echo json_encode(['success' => true, 'status' => $newStatus, 'message' => 'User status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'reset_password') {
        $userId = (int)$_POST['user_id'];
        $newPassword = $_POST['new_password'];
        
        if (empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Password cannot be empty']);
            exit;
        }
        
        if ($userManager->resetPassword($userId, $newPassword)) {
            echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
        }
        exit;
    }
}

$pageTitle = 'User Management - ZeroAI';
$currentPage = 'users';
include __DIR__ . '/includes/header.php';

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

if ($_POST && $_POST['action'] === 'create_user') {
    try {
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
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get admin users
$users = $userManager->getAllUsers() ?: [];
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
        <h5 class="mb-0">
            <button class="btn btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#createUserForm" aria-expanded="false">
                ➕ Create New User
            </button>
        </h5>
    </div>
    <div class="collapse" id="createUserForm">
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
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">👥 Existing Users (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
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
                    <tr id="user-<?= $user['id'] ?>">
                        <td><?= htmlspecialchars($user['id'] ?? '') ?></td>
                        <td><strong><?= htmlspecialchars($user['username'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($user['email'] ?? 'No email') ?></td>
                        <td><span class="badge bg-primary"><?= ucfirst($user['role'] ?? 'user') ?></span></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="status-<?= $user['id'] ?>" 
                                       <?= ($user['status'] ?? 'active') === 'active' ? 'checked' : '' ?>
                                       onchange="toggleUserStatus(<?= $user['id'] ?>, '<?= $user['status'] ?? 'active' ?>')">
                                <label class="form-check-label" for="status-<?= $user['id'] ?>">
                                    <span class="badge <?= ($user['status'] ?? 'active') === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ($user['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive' ?>
                                    </span>
                                </label>
                            </div>
                        </td>
                        <td><small><?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></small></td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>', '<?= $user['role'] ?>', '<?= $user['status'] ?? 'active' ?>')">
                                    ✏️ Edit
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                                    🔑 Reset
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                                    🗑️ Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <em>No users found.</em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✏️ Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="editUsername" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" id="editEmail" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select id="editRole" name="role" class="form-select" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="demo">Demo (View Only)</option>
                            <option value="frontend">Frontend User</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="editStatus" name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🔑 Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reset password for user: <strong id="resetUsername"></strong></p>
                <form id="resetPasswordForm">
                    <input type="hidden" id="resetUserId" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" id="newPassword" name="new_password" class="form-control" required minlength="6">
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" id="confirmPassword" class="form-control" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="saveNewPassword()">Reset Password</button>
            </div>
        </div>
    </div>
</div>

<script>
// Simple collapse functionality
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('[data-bs-toggle="collapse"]');
    const target = document.querySelector('#createUserForm');
    
    if (toggleBtn && target) {
        target.style.display = 'none';
        
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (target.style.display === 'none') {
                target.style.display = 'block';
                toggleBtn.setAttribute('aria-expanded', 'true');
            } else {
                target.style.display = 'none';
                toggleBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    
    // Alert dismiss functionality
    const alertCloses = document.querySelectorAll('.btn-close');
    alertCloses.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        });
    });
});

function editUser(id, username, email, role, status) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = role;
    document.getElementById('editStatus').value = status;
    
    // Show modal
    document.getElementById('editUserModal').style.display = 'block';
    document.getElementById('editUserModal').classList.add('show');
}

function saveUser() {
    const formData = new FormData(document.getElementById('editUserForm'));
    formData.append('action', 'edit_user');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('User updated successfully');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        alert('Error updating user: ' + error);
    });
}

function deleteUser(id, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.getElementById('user-' + id).remove();
            alert('User deleted successfully');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        alert('Error deleting user: ' + error);
    });
}

function toggleUserStatus(id, currentStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('user_id', id);
    formData.append('status', currentStatus);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Update the badge
            const label = document.querySelector(`label[for="status-${id}"] .badge`);
            if (result.status === 'active') {
                label.className = 'badge bg-success';
                label.textContent = 'Active';
            } else {
                label.className = 'badge bg-secondary';
                label.textContent = 'Inactive';
            }
        } else {
            // Revert the switch if failed
            const checkbox = document.getElementById(`status-${id}`);
            checkbox.checked = !checkbox.checked;
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        // Revert the switch if failed
        const checkbox = document.getElementById(`status-${id}`);
        checkbox.checked = !checkbox.checked;
        alert('Error updating status: ' + error);
    });
}

function resetPassword(id, username) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUsername').textContent = username;
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    
    // Show modal
    document.getElementById('resetPasswordModal').style.display = 'block';
    document.getElementById('resetPasswordModal').classList.add('show');
}

function saveNewPassword() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const userId = document.getElementById('resetUserId').value;
    
    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters long');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert('Passwords do not match');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reset_password');
    formData.append('user_id', userId);
    formData.append('new_password', newPassword);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Password reset successfully');
            // Close modal
            document.getElementById('resetPasswordModal').style.display = 'none';
            document.getElementById('resetPasswordModal').classList.remove('show');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        alert('Error resetting password: ' + error);
    });
}

// Simple modal functionality
document.addEventListener('click', function(e) {
    if (e.target.matches('[data-bs-dismiss="modal"]')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
        }
    }
});
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal.show {
    display: block;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
}

.modal-content {
    background-color: #fff;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}

.modal-header {
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1rem;
}

.modal-footer {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.form-switch .form-check-input {
    width: 2em;
    height: 1em;
    margin-top: 0.25em;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280,0,0,.25%29'/%3e%3c/svg%3e");
    background-position: left center;
    background-repeat: no-repeat;
    background-size: contain;
    border: 1px solid rgba(0,0,0,.25);
    border-radius: 2em;
    transition: background-position .15s ease-in-out;
}

.form-switch .form-check-input:checked {
    background-position: right center;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%28255,255,255,1.0%29'/%3e%3c/svg%3e");
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.form-check-label {
    margin-left: 0.5rem;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>