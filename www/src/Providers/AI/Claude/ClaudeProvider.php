<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeProvider {
    private $apiKey;
    private $commands;
    private $integration;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->commands = new ClaudeCommands();
        $this->integration = new ClaudeIntegration($apiKey);
    }
    
    public function chat($message, $model = 'claude-sonnet-4-20250514', $autonomous = true, $history = []) {
        try {
            if ($autonomous) {
                $message = "[AUTONOMOUS MODE ENABLED] You have full access to analyze, create, edit, and optimize files proactively. " . $message;
                $message .= $this->autoScan($message);
            }
            
            $commandOutputs = $this->processCommands($message);
            $systemPrompt = $this->getSystemPrompt();
            
            $response = $this->integration->chatWithClaude(
                $message . $commandOutputs, 
                $systemPrompt, 
                $model, 
                $history
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
        $originalMessage = $message;
        $this->commands->processFileCommands($message);
        $this->commands->processClaudeCommands($message);
        return strlen($message) > strlen($originalMessage) ? substr($message, strlen($originalMessage)) : '';
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