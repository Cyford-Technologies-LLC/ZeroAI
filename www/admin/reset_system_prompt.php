<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

try {
    $promptInit = new \ZeroAI\Providers\AI\Claude\ClaudePromptInit();
    $promptInit->initialize();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>