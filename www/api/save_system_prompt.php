<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$newPrompt = $input['prompt'] ?? '';

if (!$newPrompt) {
    echo json_encode(['success' => false, 'error' => 'No prompt provided']);
    exit;
}

// Save to custom prompt file
$promptFile = '/app/data/custom_system_prompt.txt';
$dir = dirname($promptFile);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

if (file_put_contents($promptFile, $newPrompt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save prompt']);
}
?>