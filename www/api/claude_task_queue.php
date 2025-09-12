<?php
// Add tasks for Claude to execute autonomously
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $command = $input['command'] ?? '';
    
    if (!$command) {
        echo json_encode(['success' => false, 'error' => 'Command required']);
        exit;
    }
    
    // Load existing tasks
    $tasksFile = '/app/data/claude_tasks.json';
    $tasks = file_exists($tasksFile) ? json_decode(file_get_contents($tasksFile), true) : [];
    
    // Add new task
    $tasks[] = [
        'id' => uniqid(),
        'command' => $command,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Save tasks
    file_put_contents($tasksFile, json_encode($tasks));
    
    echo json_encode(['success' => true, 'message' => 'Task queued for Claude']);
} else {
    // Get task status
    $tasksFile = '/app/data/claude_tasks.json';
    $tasks = file_exists($tasksFile) ? json_decode(file_get_contents($tasksFile), true) : [];
    echo json_encode(['tasks' => $tasks]);
}
?>