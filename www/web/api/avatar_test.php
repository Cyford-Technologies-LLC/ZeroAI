<?php
require_once '../../src/autoload.php';

use ZeroAI\Providers\AI\Local\AvatarProvider;

header('Content-Type: application/json');

try {
    $avatarProvider = new AvatarProvider();
    $result = $avatarProvider->testConnection();
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>