<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing avatar API...\n";

$avatarUrl = 'http://avatar:7860/generate';
$postData = json_encode([
    'prompt' => 'Hello test',
    'image' => 'test.png'
]);

echo "Calling: $avatarUrl\n";
echo "Data: $postData\n";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $postData,
        'timeout' => 30
    ]
]);

$result = file_get_contents($avatarUrl, false, $context);

if ($result === false) {
    $error = error_get_last();
    echo "ERROR: " . ($error['message'] ?? 'Unknown error') . "\n";
    echo "HTTP Response Headers:\n";
    print_r($http_response_header ?? 'No headers');
} else {
    echo "SUCCESS: Got response\n";
    echo "Response length: " . strlen($result) . " bytes\n";
}
?>