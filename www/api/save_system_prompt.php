<?php
header('Content-Type: application/json');
require_once __DIR__ . '/sqlite_manager.php';

$input = json_decode(file_get_contents('php://input'), true);
$newPrompt = $input['prompt'] ?? '';

if (!$newPrompt) {
    echo json_encode(['success' => false, 'error' => 'No prompt provided']);
    exit;
}

try {
    // Create table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS system_prompts (id INTEGER PRIMARY KEY, prompt TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
    SQLiteManager::executeSQL($createTable);
    
    // Insert or update prompt
    $insertPrompt = "INSERT OR REPLACE INTO system_prompts (id, prompt) VALUES (1, '" . SQLite3::escapeString($newPrompt) . "')";
    $result = SQLiteManager::executeSQL($insertPrompt);
    
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>