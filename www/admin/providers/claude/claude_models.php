<?php 
try {
    $pageTitle = 'Claude Models - ZeroAI';
    $currentPage = 'claude_models';
    include __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/autoload.php';
} catch (Exception $e) {
    try {
        require_once __DIR__ . '/../../../src/bootstrap.php';
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Claude models bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Claude Models Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}
$db = \ZeroAI\Core\DatabaseManager::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_model'])) {
        $model_id = trim($_POST['model_id']);
        $display_name = trim($_POST['display_name']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if ($is_default) {
            // Remove default from other models
            SQLiteManager::executeSQL("UPDATE claude_models SET is_default = 0");
        }
        
        SQLiteManager::executeSQL(
            "INSERT INTO claude_models (model_id, display_name, is_default, created_at) VALUES (?, ?, ?, datetime('now'))",
            [$model_id, $display_name, $is_default]
        );
    } elseif (isset($_POST['delete_model'])) {
        SQLiteManager::executeSQL("DELETE FROM claude_models WHERE id = ?", [$_POST['model_id']]);
    } elseif (isset($_POST['set_default'])) {
        SQLiteManager::executeSQL("UPDATE claude_models SET is_default = 0");
        SQLiteManager::executeSQL("UPDATE claude_models SET is_default = 1 WHERE id = ?", [$_POST['model_id']]);
    }
}

// Get all models
$models = SQLiteManager::executeSQL("SELECT * FROM claude_models ORDER BY is_default DESC, display_name ASC")[0]['data'] ?? [];
?>

<h1>🤖 Claude Models Management</h1>

<div class="card">
    <h3>Add New Model</h3>
    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 10px; align-items: end;">
            <div>
                <label>Model ID:</label>
                <input type="text" name="model_id" placeholder="claude-3-5-sonnet-20241022" required>
            </div>
            <div>
                <label>Display Name:</label>
                <input type="text" name="display_name" placeholder="Claude 3.5 Sonnet" required>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="is_default"> Default
                </label>
            </div>
            <button type="submit" name="add_model" class="btn-success">Add Model</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Current Models</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 10px; text-align: left;">Model ID</th>
                <th style="padding: 10px; text-align: left;">Display Name</th>
                <th style="padding: 10px; text-align: center;">Default</th>
                <th style="padding: 10px; text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($models as $model): ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-family: monospace;"><?= htmlspecialchars($model['model_id']) ?></td>
                <td style="padding: 10px;"><?= htmlspecialchars($model['display_name']) ?></td>
                <td style="padding: 10px; text-align: center;">
                    <?php if ($model['is_default']): ?>
                        <span style="color: green;">✓ Default</span>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="model_id" value="<?= $model['id'] ?>">
                            <button type="submit" name="set_default" class="btn-secondary" style="padding: 2px 8px; font-size: 11px;">Set Default</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td style="padding: 10px; text-align: center;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="model_id" value="<?= $model['id'] ?>">
                        <button type="submit" name="delete_model" class="btn-danger" style="padding: 2px 8px; font-size: 11px;" onclick="return confirm('Delete this model?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>