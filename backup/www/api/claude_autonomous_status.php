<?php
header('Content-Type: application/json');

// Get autonomous mode updates
$logFile = '/app/logs/claude_autonomous.log';
$updatesFile = '/app/data/claude_updates.json';

$updates = [];
if (file_exists($updatesFile)) {
    $updates = json_decode(file_get_contents($updatesFile), true) ?: [];
    // Clear updates after reading
    file_put_contents($updatesFile, json_encode([]));
}

echo json_encode(['updates' => $updates]);
?>