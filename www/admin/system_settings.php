<?php
session_start();
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/../src/Services/SettingsService.php';

use ZeroAI\Services\SettingsService;

$settingsService = new SettingsService();

if ($_POST) {
    $settings = [
        'display_errors' => isset($_POST['display_errors'])
    ];
    
    $settingsService->saveSettings($settings);
    $_SESSION['settings_message'] = 'Settings saved successfully!';
    header('Location: /admin/system_settings.php');
    exit;
}

$pageTitle = 'System Settings';
$currentPage = 'settings';
include __DIR__ . '/includes/header.php';

$systemInfo = $settingsService->getSystemInfo();
$debugSettings = $settingsService->getDebugSettings();
?>

<h1>System Settings</h1>

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
