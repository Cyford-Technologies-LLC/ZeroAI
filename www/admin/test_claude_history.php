<?php
header('Content-Type: application/json');

// Test what happens when we send a message with history
$testHistory = [
    ['sender' => 'You', 'message' => 'Hello Claude', 'type' => 'user'],
    ['sender' => 'Claude', 'message' => 'Hello! How can I help?', 'type' => 'claude'],
    ['sender' => 'You', 'message' => 'Remember: optimize the ZeroAI system', 'type' => 'user']
];

$testMessage = 'Do you remember what I asked you to do?';

// Send to chat handler
$response = file_get_contents('http://localhost/admin/chat_handler.php', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'message' => $testMessage,
            'model' => 'claude-3-5-sonnet-20241022',
            'autonomous' => false,
            'history' => $testHistory
        ])
    ]
]));

echo json_encode([
    'test_history' => $testHistory,
    'test_message' => $testMessage,
    'claude_response' => json_decode($response, true)
]);
?>


