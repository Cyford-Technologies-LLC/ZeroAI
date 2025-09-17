<?php
require_once '../../src/autoload.php';

use ZeroAI\Providers\AI\Local\AvatarProvider;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = $input['prompt'] ?? '';
    $image = $input['image'] ?? 'examples/source_image/art_0.png';

    $avatarProvider = new AvatarProvider();
    $result = $avatarProvider->generateAvatar($prompt, $image);

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>