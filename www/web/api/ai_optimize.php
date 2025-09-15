<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../admin/includes/autoload.php';

use ZeroAI\Core\{Project, DatabaseManager};

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && preg_match('/\/api\/projects\/(\d+)\/ai-optimize/', $path, $matches)) {
    $projectId = $matches[1];
    $project = new Project();
    
    $result = $project->aiOptimizeDescription($projectId);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Project description optimized by AI' : 'Failed to optimize'
    ]);
    exit;
}

if ($method === 'POST' && preg_match('/\/api\/tasks\/(\d+)\/ai-rewrite/', $path, $matches)) {
    $taskId = $matches[1];
    $db = DatabaseManager::getInstance();
    
    $task = $db->select('tasks', ['id' => $taskId]);
    if ($task) {
        $aiDescription = "AI-enhanced task: " . $task[0]['description'];
        $result = $db->update('tasks', ['ai_description' => $aiDescription], ['id' => $taskId]);
        
        echo json_encode([
            'success' => $result,
            'ai_description' => $aiDescription
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid endpoint']);
?>