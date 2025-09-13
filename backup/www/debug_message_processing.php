<?php
// Debug what happens to Claude's message in claude_chat.php
$input = json_decode('{"message":"@create knowledge/internal_crew/agent_learning/self/claude/test.txt\n```\ntest file created\n```","model":"claude-sonnet-4-20250514"}', true);

$message = $input['message'] ?? '';

echo "Original message:\n" . $message . "\n\n";

// Check if @create is detected
if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
    echo "✅ @create detected in message processing\n";
    echo "File: " . $matches[1] . "\n";
    echo "Content: " . trim($matches[2]) . "\n";
    
    // Log this detection
    file_put_contents('/app/logs/debug_processing.log', date('Y-m-d H:i:s') . " @create DETECTED in message processing\n", FILE_APPEND);
} else {
    echo "❌ @create NOT detected in message processing\n";
    file_put_contents('/app/logs/debug_processing.log', date('Y-m-d H:i:s') . " @create NOT DETECTED in message processing\n", FILE_APPEND);
}

echo "\nCheck /app/logs/debug_processing.log for results\n";
?>