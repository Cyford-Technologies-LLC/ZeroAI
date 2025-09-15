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
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
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
    
    <?php if (isset($message)): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Create New User</h3>
        <form method="POST" action="/admin/users">
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
        <h3>Existing Users (<?= count($users) ?>)</h3>
        <?php foreach ($users as $user): ?>
            <div class="user-item <?= $user['role'] === 'admin' ? 'admin-user' : '' ?>">
                <div>
                    <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                    <small>Role: <?= htmlspecialchars($user['role']) ?></small>
                    <?= $user['role'] === 'admin' ? '<span style="color: #007bff;"> (Admin Access)</span>' : '<span style="color: #28a745;"> (Frontend Portal)</span>' ?>
                </div>
                <div>
                    <?php if ($user['username'] !== 'admin'): ?>
                        <form method="POST" action="/admin/users/delete" style="display: inline;" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?')">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($user['username']) ?>">
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    <?php else: ?>
                        <span style="color: #666; font-size: 12px;">Protected</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
