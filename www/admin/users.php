<?php
// Block demo users from user management
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'demo') {
    header('Location: /admin/dashboard.php?error=Demo users cannot access user management');
    exit;
}

require_once '../src/Core/UserManager.php';
require_once '../src/Core/Logger.php';
require_once '../src/Core/FormBuilder.php';

$logger = \ZeroAI\Core\Logger::getInstance();
$userManager = new \ZeroAI\Core\UserManager();
$formBuilder = new \ZeroAI\Core\FormBuilder();
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

<?php
$columns = ['id', 'username', 'email', 'role', 'status', 'last_login'];
$columnLabels = [
    'id' => 'ID',
    'username' => 'Username', 
    'email' => 'Email',
    'role' => 'Role',
    'status' => 'Status',
    'last_login' => 'Last Login'
];
echo $formBuilder->renderTable('users', $users, $columns, $columnLabels, '👥 Existing Users (' . count($users) . ')', false);
?>



<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/admin/js/users.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>