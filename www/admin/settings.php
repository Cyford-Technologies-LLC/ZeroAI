<?php 
$pageTitle = 'Settings - ZeroAI';
$currentPage = 'settings';
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
        </div>
    </div>
<?php include __DIR__ . '/includes/footer.php'; ?>