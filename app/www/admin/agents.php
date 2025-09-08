<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO agents (name, role, goal, backstory, config, is_core) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['role'],
                    $_POST['goal'],
                    $_POST['backstory'],
                    json_encode(['tools' => [], 'memory' => true])
                ]);
                $success = "Agent created successfully";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("SELECT is_core FROM agents WHERE id = ?");
                $stmt->execute([$_POST['agent_id']]);
                $agent = $stmt->fetch();
                
                if ($agent && !$agent['is_core']) {
                    $stmt = $pdo->prepare("DELETE FROM agents WHERE id = ?");
                    $stmt->execute([$_POST['agent_id']]);
                    $success = "Agent deleted successfully";
                } else {
                    $error = "Cannot delete core agent";
                }
                break;
        }
    }
}

// Get all agents
$stmt = $pdo->query("SELECT * FROM agents ORDER BY is_core DESC, name");
$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agent Management - ZeroAI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .agent-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .core-agent { background: #e3f2fd; }
        button { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        .success { color: green; margin: 10px 0; }
        .error { color: red; margin: 10px 0; }
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
    
    <?php if (isset($success)): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Create New Agent</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
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
                <div>
                    <strong><?= htmlspecialchars($agent['name']) ?></strong> - <?= htmlspecialchars($agent['role']) ?><br>
                    <small><?= htmlspecialchars($agent['goal']) ?></small>
                    <?= $agent['is_core'] ? '<span style="color: #007bff;"> (Core Agent)</span>' : '' ?>
                </div>
                <div>
                    <?php if (!$agent['is_core']): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete agent?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds for realtime updates
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>