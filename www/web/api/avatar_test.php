<?php
header('Content-Type: application/json');

// Test avatar service connectivity
$avatarUrl = 'http://avatar:7860/health';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10
    ]
]);

$result = @file_get_contents($avatarUrl, false, $context);

if ($result === false) {
    $error = error_get_last();
    echo json_encode([
        'status' => 'error',
        'message' => 'Avatar service not reachable',
        'error' => $error['message'] ?? 'Unknown error',
        'url' => $avatarUrl
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'message' => 'Avatar service is running',
        'response' => $result
    ]);
}
?>