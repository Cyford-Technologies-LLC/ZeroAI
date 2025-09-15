<!DOCTYPE html>
<html>
<head>
    <title>Agent Management - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .agent-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
        .core-agent { background: #e3f2fd; }
        button { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-success { background: #28a745; color: white; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Agent Management</h1>
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
        <h3>Create New Agent</h3>
        <form method="POST">
            <input type="text" name="name" placeholder="Agent Name" required>
            <input type="text" name="role" placeholder="Agent Role" required>
            <textarea name="goal" placeholder="Agent Goal" rows="2" required></textarea>
            <textarea name="backstory" placeholder="Agent Backstory" rows="3" required></textarea>
            <button type="submit" class="btn-success">Create Agent</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Existing Agents (<?= count($agents) ?>)</h3>
        <?php foreach ($agents as $agent): ?>
            <div class="agent-item <?= $agent['is_core'] ? 'core-agent' : '' ?>">
                <strong><?= htmlspecialchars($agent['name']) ?></strong> - <?= htmlspecialchars($agent['role']) ?><br>
                <small><?= htmlspecialchars($agent['goal']) ?></small>
                <?= $agent['is_core'] ? '<span style="color: #007bff;"> (Core Agent)</span>' : '' ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
