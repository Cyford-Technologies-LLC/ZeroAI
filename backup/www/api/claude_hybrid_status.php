<?php
header('Content-Type: application/json');

// Get hybrid mode status
$tasksFile = '/app/data/claude_tasks.json';
$tasks = file_exists($tasksFile) ? json_decode(file_get_contents($tasksFile), true) : [];

$backgroundTasks = array_filter($tasks, function($task) {
    return isset($task['type']) && $task['type'] === 'background' && $task['status'] === 'pending';
});

echo json_encode(['background_tasks' => count($backgroundTasks)]);
?>