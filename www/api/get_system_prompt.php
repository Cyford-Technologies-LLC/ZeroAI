<?php
header('Content-Type: application/json');

// Get current system prompt from claude_chat.php
$claudeChatFile = __DIR__ . '/claude_chat.php';
$content = file_get_contents($claudeChatFile);

// Extract system prompt building section
preg_match('/\$systemPrompt = "([^"]*)";\s*\n(.*?)\$systemPrompt \.= "([^"]*)";/s', $content, $matches);

if ($matches) {
    $prompt = $matches[1] . $matches[2] . $matches[3];
    echo json_encode(['success' => true, 'prompt' => $prompt]);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not extract system prompt']);
}
?>