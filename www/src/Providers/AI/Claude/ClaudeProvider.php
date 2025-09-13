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
    
    public function chat($message, $model = 'claude-3-5-sonnet-20241022', $history = []) {
        try {
            // Auto-scan for autonomous mode detection
            $message .= $this->autoScan($message);
            
            $commandOutputs = $this->processCommands($message);
            $systemPrompt = $this->getSystemPrompt();
            
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
    
    private function processCommands($message) {
        $originalLength = strlen($message);
        $this->commands->processFileCommands($message);
        $this->commands->processClaudeCommands($message);
        return strlen($message) > $originalLength ? substr($message, $originalLength) : '';
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
}