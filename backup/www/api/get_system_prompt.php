<?php
header('Content-Type: application/json');
require_once __DIR__ . '/sqlite_manager.php';

try {
    // Get prompt from SQLite
    $sql = "SELECT prompt FROM system_prompts WHERE id = 1 ORDER BY created_at DESC LIMIT 1";
    $result = SQLiteManager::executeSQL($sql);
    
    if (!empty($result[0]['data'])) {
        $prompt = $result[0]['data'][0]['prompt'];
        echo json_encode(['success' => true, 'prompt' => $prompt]);
    } else {
        // Return default prompt if none saved
        $defaultPrompt = "You are Claude, integrated into ZeroAI.\n\nRole: AI Architect & Code Review Specialist\nGoal: Provide code review and optimization for ZeroAI";
        echo json_encode(['success' => true, 'prompt' => $defaultPrompt]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>