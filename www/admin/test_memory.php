<?php
// Test Claude's memory system
$testHistory = [
    ['sender' => 'You', 'message' => 'My favorite color is blue', 'type' => 'user'],
    ['sender' => 'Claude', 'message' => 'I understand your favorite color is blue', 'type' => 'claude']
];

$testData = [
    'message' => 'What is my favorite color?',
    'model' => 'claude-3-5-sonnet-20241022',
    'autonomous' => false,
    'history' => $testHistory
];

$response = file_get_contents('http://localhost/admin/chat_handler.php', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($testData)
    ]
]));

echo "Test Response:\n";
echo $response;
echo "\n\nDebug Log:\n";
if (file_exists('/app/logs/claude_debug.log')) {
    $lines = file('/app/logs/claude_debug.log');
    $recent = array_slice($lines, -10);
    foreach ($recent as $line) {
        echo $line;
    }
}
?>