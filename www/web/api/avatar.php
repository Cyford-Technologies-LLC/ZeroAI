<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    error_log('Avatar API: Starting request');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = $input['prompt'] ?? '';
    $image = $input['image'] ?? 'examples/source_image/art_0.png';
    
    error_log('Avatar API: Input - prompt: ' . substr($prompt, 0, 50) . ', image: ' . $image);

    $avatarProvider = new AvatarProvider();
    $result = $avatarProvider->generateAvatar($prompt, $image);
    
    error_log('Avatar API: Success - ' . json_encode($result));
    echo json_encode($result);
} catch (Exception $e) {
    error_log('Avatar API: Error - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>