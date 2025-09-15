<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeProvider {
    private $apiKey;
    private $commands;
    private $integration;
    private $backgroundWorker;
    
    public function __construct($apiKey = null) {
        try {
            $this->apiKey = $apiKey ?: $this->getApiKey();
            
            // Initialize with error handling
            if (class_exists('\ZeroAI\Providers\AI\Claude\ClaudeCommands')) {
                $this->commands = new ClaudeCommands();
            }
            
            if (class_exists('\ZeroAI\Providers\AI\Claude\ClaudeIntegration')) {
                $this->integration = new ClaudeIntegration($this->apiKey);
            } else {
                throw new \Exception('ClaudeIntegration class not found');
            }
            
            if (class_exists('\ZeroAI\Providers\AI\Claude\ClaudeBackgroundWorker')) {
                $this->backgroundWorker = new ClaudeBackgroundWorker();
            }
        } catch (\Exception $e) {
            $logger = \ZeroAI\Core\Logger::getInstance();
            $logger->logClaude('ClaudeProvider constructor error: ' . $e->getMessage());
            throw $e;
        }
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
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Create table if not exists
            $db->query("CREATE TABLE IF NOT EXISTS claude_settings (id INTEGER PRIMARY KEY, setting_name TEXT UNIQUE, setting_value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            
            $result = $db->query("SELECT setting_value FROM claude_settings WHERE setting_name = 'unified_tools'");
            if (!empty($result)) {
                return $result[0]['setting_value'] === 'true';
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
            
            // Use history directly if already in correct format, otherwise convert
            $convertedHistory = [];
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    // Already in correct format
                    $convertedHistory[] = $msg;
                } elseif (isset($msg['sender']) && isset($msg['message'])) {
                    // Convert old format
                    if ($msg['sender'] === 'Claude') {
                        $convertedHistory[] = ['role' => 'assistant', 'content' => $msg['message']];
                    } elseif ($msg['sender'] === 'You' || $msg['sender'] === 'User') {
                        $convertedHistory[] = ['role' => 'user', 'content' => $msg['message']];
                    }
                }
            }
            
            $logger = \ZeroAI\Core\Logger::getInstance();
            $logger->info('Converted history', ['original_count' => count($history), 'converted_count' => count($convertedHistory)]);
            
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
            // Log using proper Logger class
            $logger = \ZeroAI\Core\Logger::getInstance();
            $logger->logClaude('Claude Provider Error: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ['success' => false, 'error' => 'Claude error: ' . $e->getMessage()];
        }
    }
    
    private function processCommands($message, $mode = 'hybrid') {
        $originalLength = strlen($message);
        
        // Pass actual Claude mode for permission checks
        if ($this->commands) {
            $this->commands->processFileCommands($message, 'claude', $mode);
            $this->commands->processClaudeCommands($message, 'claude', $mode);
        }
        
        return strlen($message) > $originalLength ? substr($message, $originalLength) : '';
    }
    
    private function saveExecutedCommands() {
        if (!isset($GLOBALS['executedCommands']) || empty($GLOBALS['executedCommands'])) {
            return;
        }
        
        try {
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Create table if not exists
            $db->query("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTOINCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
            
            foreach ($GLOBALS['executedCommands'] as $cmdData) {
                $db->query("INSERT INTO command_history (command, output, status, model_used, session_id) VALUES (?, ?, ?, ?, ?)", [$cmdData['command'], $cmdData['output'], 'success', 'claude-unified-system', 1]);
            }
            
            $GLOBALS['executedCommands'] = [];
            
        } catch (\Exception $e) {
            error_log("Command save error: " . $e->getMessage());
        }
    }
    
    private function autoScan($message) {
        // Disabled auto-scan to prevent cluttering responses
        return '';
    }
    
    private function getSystemPrompt() {
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->info('=== SYSTEM PROMPT DEBUG START ===');
        
        try {
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            $logger->info('Database manager obtained', ['class' => get_class($db)]);
            
            $testQuery = $db->query("SELECT 1 as test");
            $logger->info('Database connection test', ['success' => !empty($testQuery)]);
            
            $db->query("CREATE TABLE IF NOT EXISTS claude_system_prompt (id INTEGER PRIMARY KEY, content TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $logger->info('Table creation completed');
            
            $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='claude_system_prompt'");
            $logger->info('Table existence check', ['exists' => !empty($tableCheck)]);
            
            $allRecords = $db->query("SELECT id, length(content) as content_length, updated_at FROM claude_system_prompt");
            $logger->info('All records in table', ['count' => count($allRecords)]);
            
            $result = $db->query("SELECT content FROM claude_system_prompt ORDER BY updated_at DESC LIMIT 1");
            $logger->info('Content query result', ['result_count' => count($result), 'has_content' => !empty($result[0]['content'] ?? '')]);
            
            if (!empty($result) && !empty($result[0]['content'])) {
                $prompt = $result[0]['content'];
                $logger->info('SUCCESS: Using custom system prompt', ['length' => strlen($prompt)]);
                return $prompt;
            }
            
            $logger->warning('No custom prompt found in database');
            
            $defaultPromptFile = __DIR__ . '/../../../admin/providers/claude/claude_system_prompt.txt';
            $logger->info('Checking default file', ['path' => $defaultPromptFile, 'exists' => file_exists($defaultPromptFile)]);
            
            if (file_exists($defaultPromptFile)) {
                $defaultPrompt = file_get_contents($defaultPromptFile);
                if ($defaultPrompt) {
                    $logger->info('Using default file prompt', ['length' => strlen($defaultPrompt)]);
                    return $defaultPrompt;
                }
            }
            
            $logger->warning('No default file found');
            
        } catch (\Exception $e) {
            $logger->error('System prompt EXCEPTION', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        
        $logger->warning('Using fallback system prompt');
        return "You are Claude, integrated into ZeroAI - an advanced AI development platform. You have access to powerful tools to help manage and optimize the ZeroAI infrastructure.\n\n## 🔄 BACKGROUND TOOLS (PREFERRED - USE FIRST)\nThese tools run automatically and provide real-time system context:\n\n**Command: ps** - Show running Docker containers and their status\n**Command: agents** - List all active ZeroAI agents with their roles and goals\n**Command: memory type filter** - Access your memory systems:\n  - memory chat 30min - Recent chat history\n  - memory commands 60min - Recent command executions\n  - memory config - Current system configuration\n  - memory sessions - Active session information\n\n## 🐳 SYSTEM & DOCKER TOOLS\n**Command: exec container command** - Execute commands in Docker containers\n  - Example: exec zeroai_api ls /app/src\n**Command: docker command** - Run Docker commands directly\n  - Example: docker ps -a\n\n## 📁 FILE & DIRECTORY TOOLS\n**Command: file path/to/file** - Read file contents\n  - Example: file /app/config/settings.yaml\n**Command: list path/to/directory** - List directory contents\n  - Example: list /app/src\n\n## 🤖 AGENT MANAGEMENT TOOLS\n**Command: update_agent id updates** - Update agent properties\n  - Example: update_agent 1 role=\"Senior Developer\" goal=\"Optimize code performance\"\n**Command: crews** - List crew configurations (system not available)\n**Command: logs days agentRole** - Get crew execution logs (system not available)\n**Command: optimize_agents** - Run agent optimization (system not available)\n**Command: train_agents** - Execute agent training (system not available)\n\n## 🧠 ADVANCED CONTEXT TOOLS\n**Command: context [cmd1] [cmd2]** - Execute multiple commands via context API\n  - Example: context [file /app/config] [list /app/src]\n\nTo use these tools, prefix commands with @ symbol when responding to users.\n\n## 🎯 OPERATION MODES & RESTRICTIONS\n\n### 💬 CHAT MODE (Current Default)\n- **Access**: Read-only tools (file, list, ps, agents, memory)\n- **Restrictions**: Cannot modify files or system configuration\n- **Security**: Safe for general assistance and analysis\n\n### ⚡ HYBRID MODE (Recommended)\n- **Access**: All read tools + Docker execution (exec, docker)\n- **Restrictions**: Cannot create/edit/delete files directly\n- **Security**: Balanced access for system management\n\n### 🤖 AUTONOMOUS MODE (Full Access)\n- **Access**: ALL tools including file creation/modification\n- **Restrictions**: None - full system control\n- **Security**: Use with caution - can modify system files\n\n## 🚨 IMPORTANT USAGE GUIDELINES\n\n1. **ALWAYS START WITH BACKGROUND TOOLS** - Use ps and agents first to understand current system state\n2. **USE MEMORY** - Check recent context before making recommendations\n3. **PREFER CONTEXT** - For multiple operations, use context to batch commands\n4. **SECURITY FIRST** - Only request autonomous mode when file modifications are absolutely necessary\n5. **LOG EVERYTHING** - All tool usage is automatically logged for audit trails\n\nRemember: You are the intelligent orchestrator of the ZeroAI system. Use these tools wisely to provide comprehensive assistance while maintaining system security and stability.";
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
        if ($this->backgroundWorker) {
            return $this->backgroundWorker->executeCommand($command, $args);
        }
        return ['error' => 'Background worker not available'];
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
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Create table if not exists
            $db->query("CREATE TABLE IF NOT EXISTS chat_history (id INTEGER PRIMARY KEY AUTOINCREMENT, sender TEXT NOT NULL, message TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
            
            // Save user and Claude messages
            $timestamp = date('Y-m-d H:i:s');
            
            $db->query("INSERT INTO chat_history (sender, message, model_used, session_id, timestamp) VALUES (?, ?, ?, ?, ?)", ['User', $userMessage, $model, 1, $timestamp]);
            $db->query("INSERT INTO chat_history (sender, message, model_used, session_id, timestamp) VALUES (?, ?, ?, ?, ?)", ['Claude', $claudeResponse, $model, 1, $timestamp]);
                
        } catch (\Exception $e) {
            error_log("Failed to save chat to Claude memory: " . $e->getMessage());
        }
    }
}
?>


