<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing Claude endpoint...\n";

// Check if .env file exists
if (!file_exists('/app/.env')) {
    echo "ERROR: .env file not found\n";
    exit;
}

// Load API key
$envContent = file_get_contents('/app/.env');
preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
$apiKey = isset($matches[1]) ? trim($matches[1]) : '';

if (!$apiKey) {
    echo "ERROR: ANTHROPIC_API_KEY not found in .env\n";
    exit;
}

echo "API Key found: " . substr($apiKey, 0, 10) . "...\n";

// Test simple request
$data = [
    'model' => 'claude-3-5-sonnet-20241022',
    'max_tokens' => 100,
    'messages' => [['role' => 'user', 'content' => 'Hello']]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: $curlError\n";
echo "Response: " . substr($response, 0, 200) . "\n";

if ($httpCode === 200) {
    echo "✅ Claude API working\n";
} else {
    echo "❌ Claude API failed\n";
}
?>