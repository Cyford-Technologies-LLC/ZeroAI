<?php
header('Content-Type: application/json');

// Start hybrid mode - background tasks + chat
$tasks = [
    'Monitor system health',
    'Check for code improvements',
    'Update agent performance metrics',
    'Scan for security issues'
];

// Queue background tasks
foreach ($tasks as $task) {
    $taskData = [
        'id' => uniqid(),
        'command' => $task,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'type' => 'background'
    ];
    
    $tasksFile = '/app/data/claude_tasks.json';
    $existingTasks = file_exists($tasksFile) ? json_decode(file_get_contents($tasksFile), true) : [];
    $existingTasks[] = $taskData;
    file_put_contents($tasksFile, json_encode($existingTasks));
}

echo json_encode(['success' => true, 'background_tasks' => count($tasks)]);
?>