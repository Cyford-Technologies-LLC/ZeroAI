<?php
// Test the avatar API directly
$url = 'api/avatar.php';
$data = json_encode(['prompt' => 'Hello test', 'image' => 'test.png']);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $data
    ]
]);

$result = file_get_contents($url, false, $context);

echo "Response: " . $result . "\n";
echo "HTTP Headers: ";
print_r($http_response_header);
?>