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
    $db = new \ZeroAI\Core\DatabaseManager();
    $db->executeSQL("INSERT OR REPLACE INTO system_prompts (id, prompt, created_at) VALUES (1, ?, datetime('now'))", [$prompt]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>