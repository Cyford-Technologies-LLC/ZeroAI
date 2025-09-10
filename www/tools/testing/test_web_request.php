<?php
// Test the actual web request to claude_chat.php
$data = [
    'message' => 'test',
    'model' => 'claude-sonnet-4-20250514',
    'autonomous' => false,
    'history' => []
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:333/api/claude_chat.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: $curlError\n";
echo "Response length: " . strlen($response) . "\n";
echo "Response: " . substr($response, 0, 200) . "\n";

if (empty($response)) {
    echo "❌ Empty response from web server\n";
} else {
    echo "✅ Got response from web server\n";
}
?>