<?php
header('Content-Type: application/json');

$message = $_GET['message'] ?? 'test message';
$project = $_GET['project'] ?? 'zeroai';

// Test basic Python execution
$pythonCmd = 'cd /app && /app/venv/bin/python --version 2>&1';
$output = shell_exec($pythonCmd);

echo json_encode([
    'python_version' => trim($output),
    'working_directory' => getcwd(),
    'python_path' => '/app/venv/bin/python',
    'script_exists' => file_exists('/app/run/internal/run_dev_ops.py'),
    'script_executable' => is_executable('/app/run/internal/run_dev_ops.py'),
    'message' => $message,
    'project' => $project
]);
?>

