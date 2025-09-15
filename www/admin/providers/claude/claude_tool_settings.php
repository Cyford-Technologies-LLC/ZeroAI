<?php
try {
    $pageTitle = 'Claude Tool Settings - ZeroAI Admin';
    $currentPage = 'claude_tool_settings';
    include __DIR__ . '/includes/header.php';
} catch (Exception $e) {
    try {
        require_once __DIR__ . '/../../../src/bootstrap.php';
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Claude tool settings bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Claude Tool Settings Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}

// Handle form submission
if ($_POST) {
    $unifiedTools = $_POST['unified_tools'] ?? 'false';
    
    try {
        require_once __DIR__ . '/../bootstrap.php';
        $db = \ZeroAI\Core\DatabaseManager::getInstance();
        
        // Create table if not exists
        $db->query("CREATE TABLE IF NOT EXISTS claude_settings (id INTEGER PRIMARY KEY, setting_name TEXT UNIQUE, setting_value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        
        // Update or insert setting
        $db->query("INSERT OR REPLACE INTO claude_settings (setting_name, setting_value, updated_at) VALUES (?, ?, datetime('now'))", ['unified_tools', $unifiedTools]);
        
        $message = "Settings saved successfully!";
    } catch (Exception $e) {
        $error = "Error saving settings: " . $e->getMessage();
    }
}

// Get current setting
$currentSetting = 'false';
try {
    require_once __DIR__ . '/../bootstrap.php';
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    $result = $db->query("SELECT setting_value FROM claude_settings WHERE setting_name = 'unified_tools'");
    if (!empty($result)) {
        $currentSetting = $result[0]['setting_value'];
    }
} catch (Exception $e) {
    // Use default
}
?>

<h1>🛠️ Claude Tool Settings</h1>

<?php if (isset($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Command Processing System</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>
                    <input type="radio" name="unified_tools" value="false" <?= $currentSetting === 'false' ? 'checked' : '' ?>>
                    <strong>Old System</strong> - Commands processed after Claude responds (current behavior)
                </label>
                <p class="text-muted">Claude writes commands → System processes them → Outputs added to her response → Claude can't see results</p>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="radio" name="unified_tools" value="true" <?= $currentSetting === 'true' ? 'checked' : '' ?>>
                    <strong>New Unified System</strong> - Commands processed before Claude responds (like Amazon Q)
                </label>
                <p class="text-muted">Claude writes commands → System processes them → Results given to Claude → Claude responds with knowledge of results</p>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>Current Status</h3>
    </div>
    <div class="card-body">
        <p><strong>Active System:</strong> 
            <?php if ($currentSetting === 'true'): ?>
                <span class="badge badge-success">New Unified System</span>
            <?php else: ?>
                <span class="badge badge-secondary">Old System</span>
            <?php endif; ?>
        </p>
        
        <p><strong>Benefits of New System:</strong></p>
        <ul>
            <li>Claude can see command outputs before responding</li>
            <li>More accurate responses based on actual results</li>
            <li>Unified security and permission handling</li>
            <li>Consistent logging to Claude's database</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
?>


