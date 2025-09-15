<?php 
require_once 'includes/autoload.php';

$pageTitle = 'Settings - ZeroAI';
$currentPage = 'settings';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

if ($_POST) {
    // Handle form submission
    $_SESSION['settings_message'] = 'Settings saved successfully!';
    header('Location: /admin/settings.php');
    return;
}

// Get system info
$systemInfo = [
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '/app/www',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'error_display' => ini_get('display_errors') ? 'On' : 'Off'
];

$debugSettings = ['display_errors' => ini_get('display_errors')];

include __DIR__ . '/includes/header.php';
?>

<h1>Settings</h1>
    
    <?php if (isset($_SESSION['settings_message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['settings_message']) ?></div>
        <?php unset($_SESSION['settings_message']); ?>
    <?php endif; ?>
    
    <div class="card">
        <h3>Debug Settings</h3>
        <form method="POST">
            <label>
                <input type="checkbox" name="display_errors" value="1" <?= $debugSettings['display_errors'] ? 'checked' : '' ?>>
                Display PHP Errors (for debugging)
            </label>
            <button type="submit">Save Settings</button>
        </form>
    </div>
    
    <div class="card">
        <h3>System Information</h3>
        <p><strong>PHP Version:</strong> <?= $systemInfo['php_version'] ?></p>
        <p><strong>Server:</strong> <?= $systemInfo['server'] ?></p>
        <p><strong>Document Root:</strong> <?= $systemInfo['document_root'] ?></p>
        <p><strong>Memory Limit:</strong> <?= $systemInfo['memory_limit'] ?></p>
        <p><strong>Max Execution Time:</strong> <?= $systemInfo['max_execution_time'] ?>s</p>
        <p><strong>Upload Max Filesize:</strong> <?= $systemInfo['upload_max_filesize'] ?></p>
        <p><strong>Error Display:</strong> <?= $systemInfo['error_display'] ?></p>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>