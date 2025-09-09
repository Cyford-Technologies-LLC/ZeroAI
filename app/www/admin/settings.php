<!DOCTYPE html>
<html>
<head>
    <title>Settings - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; }
        label { display: block; margin: 10px 0; }
        input[type="checkbox"] { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Settings</h1>
        <div class="nav">
            <a href="/admin/dashboard">Dashboard</a>
            <a href="/admin/users">Users</a>
            <a href="/admin/agents">Agents</a>
            <a href="/admin/settings">Settings</a>
            <a href="/admin/logout">Logout</a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['settings_message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['settings_message']) ?></div>
        <?php unset($_SESSION['settings_message']); ?>
    <?php endif; ?>
    
    <div class="card">
        <h3>Debug Settings</h3>
        <form method="POST">
            <label>
                <input type="checkbox" name="display_errors" value="1" <?= isset($_SESSION['display_errors']) && $_SESSION['display_errors'] ? 'checked' : '' ?>>
                Display PHP Errors (for debugging)
            </label>
            <button type="submit">Save Settings</button>
        </form>
    </div>
    
    <div class="card">
        <h3>System Information</h3>
        <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
        <p><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></p>
        <p><strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?></p>
        <p><strong>Error Display:</strong> <?= isset($_SESSION['display_errors']) && $_SESSION['display_errors'] ? 'Enabled' : 'Disabled' ?></p>
    </div>
</body>
</html>