<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$history = $input['history'] ?? [];

// Debug the history structure
file_put_contents('/tmp/claude_history_debug.log', 
    "History received: " . json_encode($history, JSON_PRETTY_PRINT) . "\n\n", 
    FILE_APPEND
);

echo json_encode([
    'success' => true,
    'history_count' => count($history),
    'history_sample' => array_slice($history, -3),
    'debug' => 'History logged to /tmp/claude_history_debug.log'
]);
?>