<?php
// Initialize timezone system-wide
require_once __DIR__ . '/src/Core/TimezoneManager.php';

// Apply timezone settings on every request
$timezoneManager = \ZeroAI\Core\TimezoneManager::getInstance();

// Set timezone for Python processes
if (file_exists('/app/.env.timezone')) {
    $tzContent = file_get_contents('/app/.env.timezone');
    if (preg_match('/TZ=(.+)/', $tzContent, $matches)) {
        putenv('TZ=' . trim($matches[1]));
    }
}
?>

