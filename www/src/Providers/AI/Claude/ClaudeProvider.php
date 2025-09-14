<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeProvider {
    private $apiKey;
    private $commands;
    private $integration;
    private $backgroundWorker;
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: $this->getApiKey();
        $this->commands = new ClaudeCommands();
        $this->integration = new ClaudeIntegration($this->apiKey);
        $this->backgroundWorker = new ClaudeBackgroundWorker();
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
    
    private function useUnifiedTools() {
        // Check setting for which command system to use
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            
            // Create table if not exists
            $db->executeSQL("CREATE TABLE IF NOT EXISTS claude_settings (id INTEGER PRIMARY KEY, setting_name TEXT UNIQUE, setting_value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'main');
            
            $result = $db->executeSQL("SELECT setting_value FROM claude_settings WHERE setting_name = 'unified_tools'", 'main');
            if (!empty($result[0]['data'])) {
                return $result[0]['data'][0]['setting_value'] === 'true';
            }
        } catch (\Exception $e) {
            // Default to new system if setting not found
        }
        return true; // Default to new unified system
    }
    
    public function chat($message, $model = 'claude-3-5-sonnet-20241022', $history = [], $mode = 'hybrid') {
        try {
            // Set global mode for tool system
            $GLOBALS['claudeMode'] = $mode;
            
            // Auto-scan for autonomous mode detection
            $message .= $this->autoScan($message);
            
            $systemPrompt = $this->getSystemPrompt();
            
            // Add background context to system prompt
            $backgroundContext = $this->getBackgroundContext();
            if ($backgroundContext) {
                $systemPrompt .= "\n\nBACKGROUND CONTEXT:\n" . $backgroundContext;
            }
            
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
            
            if ($this->useUnifiedTools()) {
                // NEW SYSTEM: Unified tools (Claude sees results before responding)
                $response = $this->integration->chatWithClaude(
                    $message, 
                    $systemPrompt, 
                    $model, 
                    $convertedHistory
                );
            } else {
                // OLD SYSTEM: Process commands after Claude responds
                $commandOutputs = $this->processCommands($message, $mode);
                
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
                
                // Process Claude's own commands in her response
                $claudeResponse = $response['message'];
                $claudeCommandOutputs = $this->processCommands($claudeResponse, $mode);
                if ($claudeCommandOutputs) {
                    $response['message'] = $claudeResponse . $claudeCommandOutputs;
                }
                
                $this->saveExecutedCommands();
            }
            
            // Save chat to Claude's memory database
            $this->saveChatToMemory($message, $response['message'], $model);
            
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
    
    private function processCommands($message, $mode = 'hybrid') {
        $originalLength = strlen($message);
        
        // Pass actual Claude mode for permission checks
        $this->commands->processFileCommands($message, 'claude', $mode);
        $this->commands->processClaudeCommands($message, 'claude', $mode);
        
        return strlen($message) > $originalLength ? substr($message, $originalLength) : '';
    }
    
    private function saveExecutedCommands() {
        if (!isset($GLOBALS['executedCommands']) || empty($GLOBALS['executedCommands'])) {
            return;
        }
        
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            
            // Create table if not exists
            $db->executeSQL("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTOINCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)", 'claude');
            
            foreach ($GLOBALS['executedCommands'] as $cmdData) {
                $db->executeSQL("INSERT INTO command_history (command, output, status, model_used, session_id) VALUES (?, ?, ?, ?, ?)", 'claude', [$cmdData['command'], $cmdData['output'], 'success', 'claude-unified-system', 1]);
            }
            
            $GLOBALS['executedCommands'] = [];
            
        } catch (\Exception $e) {
            error_log("Command save error: " . $e->getMessage());
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
    
    private function getSystemPrompt() {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $result = $db->executeSQL("SELECT prompt FROM claude_prompts ORDER BY created_at DESC LIMIT 1", 'claude');
            
            if (!empty($result[0]['data'])) {
                $prompt = $result[0]['data'][0]['prompt'];
                if ($prompt && strpos($prompt, '@file') === false) {
                    $prompt .= $this->getCommandsHelp();
                }
                return $prompt;
            }
        } catch (\Exception $e) {
            // Fall back to default
        }
        
        return 'You are Claude, integrated into ZeroAI.' . $this->getCommandsHelp();
    }
    
    private function getBackgroundContext() {
        try {
            $context = $this->backgroundWorker->getContext();
            if (empty($context)) return '';
            
            $contextStr = '';
            foreach ($context as $item) {
                $contextStr .= "- {$item['key']}: {$item['value']}\n";
            }
            return $contextStr;
        } catch (\Exception $e) {
            return '';
        }
    }
    
    public function executeBackgroundCommand($command, $args = []) {
        return $this->backgroundWorker->executeCommand($command, $args);
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
               "- @ps - Show running containers\n" .
               "\n\nIMPORTANT RULES:\n" .
               "- NEVER fabricate command outputs or results\n" .
               "- If you don't see actual command results, say 'I cannot see the command response'\n" .
               "- Only report what you actually observe from tool results\n" .
               "- When commands fail, report the actual error message\n" .
               "- Do not assume or guess what commands might return\n";
    }
    
    private function initializeSystemPrompt() {
        $promptInit = new ClaudePromptInit();
        $promptInit->initialize();
    }
    
    private function saveChatToMemory($userMessage, $claudeResponse, $model) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            
            // Create table if not exists
            $db->executeSQL("CREATE TABLE IF NOT EXISTS chat_history (id INTEGER PRIMARY KEY AUTOINCREMENT, sender TEXT NOT NULL, message TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)", 'claude');
            
            // Save user and Claude messages with null checks
            $userSender = 'User';
            $claudeSender = 'Claude';
            $safeUserMessage = $userMessage ?: '';
            $safeClaudeResponse = $claudeResponse ?: '';
            $safeModel = $model ?: 'unknown';
            
            $db->executeSQL("INSERT INTO chat_history (sender, message, model_used, session_id) VALUES (?, ?, ?, ?)", 'claude', [$userSender, $safeUserMessage, $safeModel, 1]);
            $db->executeSQL("INSERT INTO chat_history (sender, message, model_used, session_id) VALUES (?, ?, ?, ?)", 'claude', [$claudeSender, $safeClaudeResponse, $safeModel, 1]);
                
        } catch (\Exception $e) {
            error_log("Failed to save chat to Claude memory: " . $e->getMessage());
        }
    }
}
?>