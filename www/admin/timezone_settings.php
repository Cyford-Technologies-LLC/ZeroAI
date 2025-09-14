<?php
require_once __DIR__ . '/../src/Core/DatabaseManager.php';

$db = new \ZeroAI\Core\DatabaseManager();

// Handle form submission
if ($_POST) {
    $timezone = $_POST['timezone'] ?? 'America/New_York';
    
    // Create settings table if not exists
    $db->executeSQL("CREATE TABLE IF NOT EXISTS system_settings (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'main');
    
    // Save timezone setting
    $db->executeSQL("INSERT OR REPLACE INTO system_settings (key, value) VALUES ('timezone', '$timezone')", 'main');
    
    // Apply timezone immediately
    date_default_timezone_set($timezone);
    
    // Set environment variable for Docker containers
    file_put_contents('/app/.env.timezone', "TZ=$timezone\n");
    
    $success = "Timezone set to $timezone";
}

// Get current timezone setting
$result = $db->executeSQL("SELECT value FROM system_settings WHERE key = 'timezone'", 'main');
$currentTimezone = $result[0]['data'][0]['value'] ?? 'America/New_York';

// Apply current timezone
date_default_timezone_set($currentTimezone);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Timezone Settings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, input { padding: 8px; width: 300px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
        .success { color: green; margin: 10px 0; }
        .current-time { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Timezone Settings</h1>
    
    <?php if (isset($success)): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="current-time">
        <strong>Current Time:</strong> <?= date('Y-m-d H:i:s T') ?><br>
        <strong>Current Timezone:</strong> <?= $currentTimezone ?>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label>Select Timezone:</label>
            <select name="timezone">
                <option value="America/New_York" <?= $currentTimezone === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (EST/EDT)</option>
                <option value="America/Chicago" <?= $currentTimezone === 'America/Chicago' ? 'selected' : '' ?>>Central Time (CST/CDT)</option>
                <option value="America/Denver" <?= $currentTimezone === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (MST/MDT)</option>
                <option value="America/Los_Angeles" <?= $currentTimezone === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (PST/PDT)</option>
                <option value="UTC" <?= $currentTimezone === 'UTC' ? 'selected' : '' ?>>UTC</option>
            </select>
        </div>
        
        <button type="submit">Update Timezone</button>
    </form>
    
    <h3>System Status</h3>
    <div>
        <strong>PHP Timezone:</strong> <?= date_default_timezone_get() ?><br>
        <strong>Docker TZ File:</strong> <?= file_exists('/app/.env.timezone') ? file_get_contents('/app/.env.timezone') : 'Not set' ?>
    </div>
    
    <p><a href="index.php">← Back to Admin</a></p>
</body>
</html>