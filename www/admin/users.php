<?php 
$pageTitle = 'User Management - ZeroAI';
$currentPage = 'users';
include __DIR__ . '/includes/header.php';
?>

<h1>User Management</h1>
    
    <div class="card">
        <h3>Create New User</h3>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role">
                <option value="user">Frontend Portal User</option>
                <option value="admin">Admin User</option>
            </select>
            <button type="submit">Create User</button>
        </form>
    </div>
    
    <div class="card">
        <h3>User List</h3>
        <p>User management functionality coming soon...</p>
    </div>
        </div>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>