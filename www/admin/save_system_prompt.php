<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';

if (!$prompt) {
    echo json_encode(['success' => false, 'error' => 'Prompt required']);
    exit;
}

try {
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    
    // Create table if not exists
    $db->query("CREATE TABLE IF NOT EXISTS claude_data (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Update system prompt
    $db->query("INSERT OR REPLACE INTO claude_data (key, value) VALUES ('system_prompt', ?)", [$prompt]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


