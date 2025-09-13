<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeProvider {
    private $apiKey;
    private $commands;
    private $integration;
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: $this->getApiKey();
        $this->commands = new ClaudeCommands();
        $this->integration = new ClaudeIntegration($this->apiKey);
    }
    
    private function getApiKey() {
        if (file_exists('/app/.env')) {
            $envContent = file_get_contents('/app/.env');
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches)) {
                return trim($matches[1]);
            }
        }
        return getenv('ANTHROPIC_API_KEY');
    }
    
    public function chat($message, $model = 'claude-3-5-sonnet-20241022', $history = [], $mode = 'hybrid') {
        try {
            error_log("[CLAUDE_PROVIDER] Starting chat with mode: $mode");
            error_log("[CLAUDE_PROVIDER] Original message: " . substr($message, 0, 100));
            
            // Auto-scan for autonomous mode detection
            $message .= $this->autoScan($message);
            error_log("[CLAUDE_PROVIDER] After auto-scan: " . substr($message, 0, 100));
            
            $commandOutputs = $this->processCommands($message, $mode);
            error_log("[CLAUDE_PROVIDER] Command outputs length: " . strlen($commandOutputs));
            error_log("[CLAUDE_PROVIDER] Command outputs: " . substr($commandOutputs, 0, 200));
            
            $systemPrompt = $this->getSystemPrompt();
            error_log("[CLAUDE_PROVIDER] System prompt length: " . strlen($systemPrompt));
            
            // Convert frontend history format to Claude API format
            $convertedHistory = [];
            foreach ($history as $msg) {
                if (isset($msg['sender']) && isset($msg['message'])) {
                    if ($msg['sender'] === 'Claude') {
                        $convertedHistory[] = ['role' => 'assistant', 'content' => $msg['message']];
                    } elseif ($msg['sender'] === 'You' || $msg['sender'] === 'User') {
                        $convertedHistory[] = ['role' => 'user', 'content' => $msg['message']];
                    }
                }
            }
            
            // Add command outputs to message for Claude to see
            $fullMessage = $message;
            if ($commandOutputs) {
                $fullMessage .= "\n\nCommand Results:" . $commandOutputs;
            }
            
            $response = $this->integration->chatWithClaude(
                $fullMessage, 
                $systemPrompt, 
                $model, 
                $convertedHistory
            );
            
            // Log conversation to database
            $this->logConversation($message, $response['message'], $model);
            
            return [
                'success' => true,
                'response' => $response['message'],
                'tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
                'cost' => 0.0,
                'model' => $response['model'] ?? $model
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Claude error: ' . $e->getMessage()];
        }
    }
    
    private function autoScan($message) {
        if (!preg_match('/\@(file|list|search|create|edit|append|delete)/', $message)) {
            $scan = "\n\nAuto-scanning key directories:\n";
            if (is_dir('/app/src')) {
                $srcFiles = shell_exec('find /app/src -name "*.py" | head -10');
                $scan .= "\nSrc files:\n" . ($srcFiles ?: "No Python files found");
            }
            if (is_dir('/app/config')) {
                $configFiles = scandir('/app/config');
                $scan .= "\nConfig files: " . implode(", ", array_filter($configFiles, function($f) { return $f !== '.' && $f !== '..'; }));
            }
            return $scan;
        }
        return '';
    }
    
    private function processCommands($message, $mode = 'hybrid') {
        $originalLength = strlen($message);
        error_log("[CLAUDE_PROVIDER] Processing commands in mode: $mode");
        error_log("[CLAUDE_PROVIDER] Message before commands: " . substr($message, 0, 100));
        
        // Pass actual Claude mode for permission checks
        $this->commands->processFileCommands($message, 'claude', $mode);
        $this->commands->processClaudeCommands($message, 'claude', $mode);
        
        $commandOutput = strlen($message) > $originalLength ? substr($message, $originalLength) : '';
        error_log("[CLAUDE_PROVIDER] Command output extracted: " . substr($commandOutput, 0, 200));
        return $commandOutput;
    }
    
    private function getSystemPrompt() {
        require_once __DIR__ . '/../../../Core/DatabaseManager.php';
        $db = new \ZeroAI\Core\DatabaseManager();
        
        $result = $db->executeSQL("SELECT prompt FROM system_prompts WHERE id = 1 ORDER BY created_at DESC LIMIT 1");
        
        if (!empty($result[0]['data'])) {
            $prompt = $result[0]['data'][0]['prompt'];
            if (strpos($prompt, '@file') === false) {
                $prompt .= $this->getCommandsHelp();
            }
            return $prompt;
        }
        
        $this->initializeSystemPrompt();
        return $this->getSystemPrompt();
    }
    
    private function getCommandsHelp() {
        return "\n\nCOMMANDS:\n" .
               "- @file path/to/file.py - Read file contents\n" .
               "- @list path/to/directory - List directory contents\n" .
               "- @search pattern - Find files matching pattern\n" .
               "- @create path/to/file.py ```content``` - Create file\n" .
               "- @edit path/to/file.py ```content``` - Replace file content\n" .
               "- @append path/to/file.py ```content``` - Add to file\n" .
               "- @delete path/to/file.py - Delete file\n" .
               "- @agents - List all agents\n" .
               "- @docker [command] - Execute Docker commands\n" .
               "- @ps - Show running containers\n";
    }
    
    private function initializeSystemPrompt() {
        $promptInit = new ClaudePromptInit();
        $promptInit->initialize();
    }
    
    private function logConversation($userMessage, $claudeResponse, $model) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $db->executeSQL(
                "INSERT INTO conversations (user_message, claude_response, model, timestamp) VALUES (?, ?, ?, datetime('now'))",
                'main',
                [$userMessage, $claudeResponse, $model]
            );
            error_log("[CLAUDE_PROVIDER] Conversation logged to database");
        } catch (\Exception $e) {
            error_log("[CLAUDE_PROVIDER] Failed to log conversation: " . $e->getMessage());
        }
    }
}