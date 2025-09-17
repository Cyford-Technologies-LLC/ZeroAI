<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Avatar API: Method not allowed - ' . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';
$image = $input['image'] ?? 'examples/source_image/art_0.png';

error_log('Avatar API: Request received - Prompt: ' . substr($prompt, 0, 100) . ', Image: ' . $image);

if (empty($prompt)) {
    error_log('Avatar API: Empty prompt provided');
    http_response_code(400);
    echo json_encode(['error' => 'Prompt is required']);
    exit;
}

// Call avatar service internally
$avatarUrl = 'http://avatar:7860/generate';
$postData = json_encode([
    'prompt' => $prompt,
    'image' => $image
]);

error_log('Avatar API: Calling avatar service at ' . $avatarUrl);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $postData,
        'timeout' => 300 // 5 minutes timeout
    ]
]);

$result = file_get_contents($avatarUrl, false, $context);

if ($result === false) {
    $error = error_get_last();
    error_log('Avatar API: Failed to call avatar service - ' . ($error['message'] ?? 'Unknown error'));
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate avatar', 'details' => $error['message'] ?? 'Unknown error']);
    exit;
}

error_log('Avatar API: Successfully received response from avatar service');

// Return the video file
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="avatar.mp4"');
echo $result;
?>