<?php
// Log Claude's actual responses to see if she's using @commands
function logClaudeResponse($userMessage, $claudeResponse) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_message' => $userMessage,
        'claude_response' => $claudeResponse,
        'contains_create' => strpos($claudeResponse, '@create') !== false,
        'contains_edit' => strpos($claudeResponse, '@edit') !== false,
        'contains_file' => strpos($claudeResponse, '@file') !== false,
        'contains_any_command' => preg_match('/\@(create|edit|append|delete|file|list|mkdir)/', $claudeResponse)
    ];
    
    $logFile = 'c:/Users/allen/PycharmProjects/ZeroAI/logs/claude_responses.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
}
?>