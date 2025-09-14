<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

try {
    $db = new \ZeroAI\Core\DatabaseManager();
    $result = $db->executeSQL("SELECT prompt FROM system_prompts WHERE id = 1 ORDER BY created_at DESC LIMIT 1");
    
    if (!empty($result[0]['data'])) {
        echo json_encode(['success' => true, 'prompt' => $result[0]['data'][0]['prompt']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No system prompt found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>