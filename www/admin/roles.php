<?php 
session_start();
$pageTitle = 'Role Management - ZeroAI';
$currentPage = 'roles';

require_once __DIR__ . '/includes/autoload.php';
use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

// Initialize roles and permissions tables
$db->query("CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    display_name TEXT NOT NULL,
    description TEXT,
    level INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->query("CREATE TABLE IF NOT EXISTS permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    display_name TEXT NOT NULL,
    category TEXT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->query("CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INTEGER,
    permission_id INTEGER,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
)");

// Insert default roles if not exists
$defaultRoles = [
    ['name' => 'super_admin', 'display_name' => 'Super Administrator', 'description' => 'Full system access', 'level' => 10],
    ['name' => 'ceo', 'display_name' => 'CEO', 'description' => 'Chief Executive Officer', 'level' => 9],
    ['name' => 'cto', 'display_name' => 'CTO', 'description' => 'Chief Technology Officer', 'level' => 9],
    ['name' => 'cfo', 'display_name' => 'CFO', 'description' => 'Chief Financial Officer', 'level' => 9],
    ['name' => 'vp_sales', 'display_name' => 'VP Sales', 'description' => 'Vice President of Sales', 'level' => 8],
    ['name' => 'vp_marketing', 'display_name' => 'VP Marketing', 'description' => 'Vice President of Marketing', 'level' => 8],
    ['name' => 'director', 'display_name' => 'Director', 'description' => 'Department Director', 'level' => 7],
    ['name' => 'manager', 'display_name' => 'Manager', 'description' => 'Team Manager', 'level' => 6],
    ['name' => 'team_lead', 'display_name' => 'Team Lead', 'description' => 'Team Leader', 'level' => 5],
    ['name' => 'senior_employee', 'display_name' => 'Senior Employee', 'description' => 'Senior Staff Member', 'level' => 4],
    ['name' => 'employee', 'display_name' => 'Employee', 'description' => 'Regular Employee', 'level' => 3],
    ['name' => 'contractor', 'display_name' => 'Contractor', 'description' => 'External Contractor', 'level' => 2],
    ['name' => 'intern', 'display_name' => 'Intern', 'description' => 'Intern/Trainee', 'level' => 1]
];

foreach ($defaultRoles as $role) {
    $existing = $db->select('roles', ['name' => $role['name']]);
    if (empty($existing)) {
        $db->insert('roles', $role);
    }
}

// Insert default permissions
$defaultPermissions = [
    // Agent Management
    ['name' => 'agents.view', 'display_name' => 'View Agents', 'category' => 'Agents'],
    ['name' => 'agents.create', 'display_name' => 'Create Agents', 'category' => 'Agents'],
    ['name' => 'agents.edit', 'display_name' => 'Edit Agents', 'category' => 'Agents'],
    ['name' => 'agents.delete', 'display_name' => 'Delete Agents', 'category' => 'Agents'],
    ['name' => 'agents.assign', 'display_name' => 'Assign Agents', 'category' => 'Agents'],
    
    // CRM Management
    ['name' => 'crm.view_all', 'display_name' => 'View All CRM Data', 'category' => 'CRM'],
    ['name' => 'crm.view_own', 'display_name' => 'View Own CRM Data', 'category' => 'CRM'],
    ['name' => 'crm.edit_all', 'display_name' => 'Edit All CRM Data', 'category' => 'CRM'],
    ['name' => 'crm.edit_own', 'display_name' => 'Edit Own CRM Data', 'category' => 'CRM'],
    
    // User Management
    ['name' => 'users.view', 'display_name' => 'View Users', 'category' => 'Users'],
    ['name' => 'users.create', 'display_name' => 'Create Users', 'category' => 'Users'],
    ['name' => 'users.edit', 'display_name' => 'Edit Users', 'category' => 'Users'],
    ['name' => 'users.delete', 'display_name' => 'Delete Users', 'category' => 'Users'],
    
    // System Administration
    ['name' => 'system.admin', 'display_name' => 'System Administration', 'category' => 'System'],
    ['name' => 'system.settings', 'display_name' => 'System Settings', 'category' => 'System'],
    ['name' => 'system.logs', 'display_name' => 'View System Logs', 'category' => 'System'],
    
    // Financial
    ['name' => 'finance.view', 'display_name' => 'View Financial Data', 'category' => 'Finance'],
    ['name' => 'finance.edit', 'display_name' => 'Edit Financial Data', 'category' => 'Finance'],
    
    // Reports
    ['name' => 'reports.view', 'display_name' => 'View Reports', 'category' => 'Reports'],
    ['name' => 'reports.create', 'display_name' => 'Create Reports', 'category' => 'Reports']
];

foreach ($defaultPermissions as $perm) {
    $existing = $db->select('permissions', ['name' => $perm['name']]);
    if (empty($existing)) {
        $db->insert('permissions', $perm);
    }
}

$roles = $db->select('roles', [], null, 'level DESC');
$permissions = $db->select('permissions', [], null, 'category, display_name');

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>👥 Role & Permission Management</h1>
    <div>
        <button onclick="showCreateRoleForm()" class="btn btn-success">+ Create Role</button>
        <button onclick="showPermissionMatrix()" class="btn btn-primary">Permission Matrix</button>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-value"><?= count($roles) ?></div>
        <div class="stat-label">Total Roles</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($permissions) ?></div>
        <div class="stat-label">Permissions</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count(array_unique(array_column($permissions, 'category'))) ?></div>
        <div class="stat-label">Categories</div>
    </div>
</div>

<div class="card">
    <h3>🏢 Organizational Roles</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;">
        <?php foreach ($roles as $role): ?>
            <div class="role-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #fff;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; color: #007cba;"><?= htmlspecialchars($role['display_name']) ?></h4>
                    <span style="background: #007cba; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">
                        Level <?= $role['level'] ?>
                    </span>
                </div>
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                    <?= htmlspecialchars($role['description']) ?>
                </p>
                <div style="display: flex; gap: 8px;">
                    <button onclick="editRole(<?= $role['id'] ?>)" class="btn btn-warning btn-sm">Edit</button>
                    <button onclick="managePermissions(<?= $role['id'] ?>)" class="btn btn-primary btn-sm">Permissions</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function showCreateRoleForm() {
    alert('Create role form - to be implemented');
}

function showPermissionMatrix() {
    alert('Permission matrix - to be implemented');
}

function editRole(roleId) {
    alert('Edit role ' + roleId + ' - to be implemented');
}

function managePermissions(roleId) {
    alert('Manage permissions for role ' + roleId + ' - to be implemented');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>