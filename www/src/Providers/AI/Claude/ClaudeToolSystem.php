<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeToolSystem {
    private $security;
    
    public function __construct() {
        $this->security = new \ZeroAI\Core\Security();
    }
    
    public function execute($command, $args = [], $mode = 'hybrid') {
        // Security check before any tool execution
        if (!$this->hasPermission($command, $mode)) {
            return ['error' => "Permission denied: $command requires " . $this->getRequiredMode($command) . " mode"];
        }
        
        // Route to appropriate tool
        switch ($command) {
            case 'exec':
                return $this->execCommand($args[0], $args[1]);
            case 'file':
                return $this->readFile($args[0]);
            case 'list':
                return $this->listDirectory($args[0]);
            case 'agents':
                return $this->listAgents();
            case 'update_agent':
                return $this->updateAgent($args[0], $args[1]);
            case 'crews':
                return $this->listCrews();
            case 'analyze_crew':
                return $this->analyzeCrew($args[0]);
            case 'logs':
                return $this->getCrewLogs($args[0] ?? 7, $args[1] ?? null);
            case 'optimize_agents':
                return $this->optimizeAgents();
            case 'train_agents':
                return $this->trainAgents();
            case 'docker':
                return $this->dockerCommand($args[0]);
            case 'ps':
                return $this->showContainers();
            case 'memory':
                return $this->memoryCommand($args[0], $args[1] ?? null);
            case 'context':
                return $this->contextCommand($args[0]);
            default:
                return ['error' => "Unknown command: $command"];
        }
    }
    
    private function hasPermission($command, $mode) {
        $permissions = [
            'exec' => 'hybrid',
            'file' => 'hybrid', 
            'list' => 'hybrid',
            'agents' => 'hybrid',
            'docker' => 'hybrid',
            'ps' => 'hybrid',
            'memory' => 'hybrid',
            'create' => 'autonomous',
            'edit' => 'autonomous',
            'delete' => 'autonomous'
        ];
        
        $required = $permissions[$command] ?? 'hybrid';
        
        if ($required === 'autonomous' && $mode !== 'autonomous') {
            return false;
        }
        
        return $this->security->hasPermission('claude', $this->getPermissionType($command), $mode);
    }
    
    private function getRequiredMode($command) {
        $modes = [
            'create' => 'autonomous',
            'edit' => 'autonomous', 
            'delete' => 'autonomous'
        ];
        return $modes[$command] ?? 'hybrid';
    }
    
    private function getPermissionType($command) {
        $types = [
            'exec' => 'docker_exec',
            'file' => 'cmd_file',
            'list' => 'cmd_file',
            'agents' => 'cmd_file',
            'docker' => 'docker_exec',
            'ps' => 'docker_exec',
            'memory' => 'cmd_file'
        ];
        return $types[$command] ?? 'cmd_file';
    }
    
    private function execCommand($container, $command) {
        $result = shell_exec("docker exec " . escapeshellarg($container) . " {$command} 2>&1");
        $this->logCommand('exec', "docker exec {$container} {$command}", $result);
        
        return [
            'success' => true,
            'output' => $result ?: 'No output',
            'formatted' => "🐳 Exec {$container}: {$command}\n" . ($result ?: 'No output')
        ];
    }
    
    private function readFile($path) {
        if (!str_starts_with($path, '/')) {
            $path = '/app/' . $path;
        }
        
        if (!file_exists($path)) {
            return ['error' => "File not found: {$path}"];
        }
        
        $content = file_get_contents($path);
        $this->logCommand('file', "read {$path}", 'File read successfully');
        
        return [
            'success' => true,
            'content' => $content,
            'formatted' => "📄 File: {$path}\n```\n{$content}\n```"
        ];
    }
    
    private function listDirectory($path) {
        if (!str_starts_with($path, '/')) {
            $path = '/app/' . $path;
        }
        
        if (!is_dir($path)) {
            return ['error' => "Directory not found: {$path}"];
        }
        
        $files = scandir($path);
        $list = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
        $this->logCommand('list', "list {$path}", count($list) . ' files found');
        
        return [
            'success' => true,
            'files' => array_values($list),
            'formatted' => "📁 Directory: {$path}\n" . implode("\n", $list)
        ];
    }
    
    private function listAgents() {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $result = $db->executeSQL("SELECT * FROM agents ORDER BY id", 'main');
            
            $output = "🤖 Agents:\n";
            if (!empty($result[0]['data'])) {
                foreach ($result[0]['data'] as $a) {
                    $output .= "ID: {$a['id']} | Role: {$a['role']} | Goal: {$a['goal']}\n";
                }
                $this->logCommand('agents', 'list agents', count($result[0]['data']) . ' agents listed');
            } else {
                $output .= "No agents found\n";
                $this->logCommand('agents', 'list agents', 'No agents found');
            }
            
            return ['success' => true, 'formatted' => $output];
        } catch (\Exception $e) {
            return ['error' => 'Error loading agents: ' . $e->getMessage()];
        }
    }
    
    private function dockerCommand($cmd) {
        $result = shell_exec("docker {$cmd} 2>&1");
        $this->logCommand('docker', "docker {$cmd}", $result);
        
        return [
            'success' => true,
            'output' => $result ?: 'No output',
            'formatted' => "🐳 Docker: {$cmd}\n" . ($result ?: 'No output')
        ];
    }
    
    private function showContainers() {
        $result = shell_exec("docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1");
        $this->logCommand('ps', 'docker ps', $result);
        
        return [
            'success' => true,
            'output' => $result ?: 'No containers',
            'formatted' => "🐳 Running Containers:\n" . ($result ?: 'No containers')
        ];
    }
    
    private function updateAgent($agentId, $updates) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $updateData = [];
            
            // Parse update parameters
            if (preg_match('/role="([^"]+)"/', $updates, $roleMatch)) $updateData['role'] = $roleMatch[1];
            if (preg_match('/goal="([^"]+)"/', $updates, $goalMatch)) $updateData['goal'] = $goalMatch[1];
            if (preg_match('/backstory="([^"]+)"/', $updates, $backstoryMatch)) $updateData['backstory'] = $backstoryMatch[1];
            if (preg_match('/status="([^"]+)"/', $updates, $statusMatch)) $updateData['status'] = $statusMatch[1];
            
            if (!empty($updateData)) {
                $setParts = [];
                $params = [];
                foreach ($updateData as $field => $value) {
                    $setParts[] = "$field = ?";
                    $params[] = $value;
                }
                $params[] = $agentId;
                
                $sql = "UPDATE agents SET " . implode(', ', $setParts) . " WHERE id = ?";
                $db->executeSQL($sql, 'main', $params);
                
                $this->logCommand('update_agent', "update agent $agentId", json_encode($updateData));
                return ['success' => true, 'formatted' => "✅ Agent {$agentId} updated: " . json_encode($updateData)];
            }
            
            return ['error' => 'No valid update parameters found'];
        } catch (\Exception $e) {
            return ['error' => 'Error updating agent: ' . $e->getMessage()];
        }
    }
    
    private function listCrews() {
        return ['success' => true, 'formatted' => "🚀 Crew Status: Crew system not available"];
    }
    
    private function analyzeCrew($taskId) {
        return ['error' => 'Crew system not available'];
    }
    
    private function getCrewLogs($days, $agentRole) {
        return ['success' => true, 'formatted' => "📋 Crew logs: System not available"];
    }
    
    private function optimizeAgents() {
        return ['success' => true, 'formatted' => "📈 Agent optimization: System not available"];
    }
    
    private function trainAgents() {
        return ['success' => true, 'formatted' => "🎓 Agent training: System not available"];
    }
    
    private function memoryCommand($type, $filter) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $memoryData = [];
            
            if ($type === 'chat' && preg_match('/(\d+)min/', $filter, $timeMatch)) {
                $minutes = (int)$timeMatch[1];
                $result = $db->executeSQL("SELECT sender, message, model_used, timestamp FROM chat_history ORDER BY timestamp DESC LIMIT 50", 'claude');
                $memoryData = $result[0]['data'] ?? [];
            } elseif ($type === 'commands' && preg_match('/(\d+)min/', $filter, $timeMatch)) {
                $minutes = (int)$timeMatch[1];
                $result = $db->executeSQL("SELECT command, output, status, model_used, timestamp FROM command_history ORDER BY timestamp DESC LIMIT 50", 'claude');
                $memoryData = $result[0]['data'] ?? [];
            } elseif ($type === 'config') {
                $result = $db->executeSQL("SELECT system_prompt, goals, personality, capabilities, updated_at FROM claude_config WHERE id = 1", 'claude');
                $memoryData = $result[0]['data'] ?? [];
            } elseif ($type === 'sessions') {
                $result = $db->executeSQL("SELECT model_used, mode, start_time, message_count, command_count FROM claude_sessions ORDER BY start_time DESC LIMIT 10", 'claude');
                $memoryData = $result[0]['data'] ?? [];
            }
            
            // Create memory file
            $sessionsDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions';
            if (!is_dir($sessionsDir)) mkdir($sessionsDir, 0777, true);
            
            $memoryFile = $sessionsDir . '/memory.json';
            file_put_contents($memoryFile, json_encode($memoryData, JSON_PRETTY_PRINT));
            
            $output = "🧠 Memory: Found " . count($memoryData) . " $type records\n";
            if (!empty($memoryData)) {
                $output .= "Recent entries:\n";
                foreach (array_slice($memoryData, 0, 5) as $item) {
                    if ($type === 'chat') {
                        $output .= "[{$item['timestamp']}] {$item['sender']}: " . substr($item['message'], 0, 100) . "...\n";
                    } elseif ($type === 'commands') {
                        $output .= "[{$item['timestamp']}] {$item['command']}\n";
                    }
                }
            } else {
                $output .= "No records found.\n";
            }
            
            $this->logCommand('memory', "memory $type $filter", count($memoryData) . ' records found');
            return ['success' => true, 'formatted' => $output];
        } catch (\Exception $e) {
            return ['error' => 'Error accessing memory: ' . $e->getMessage()];
        }
    }
    
    private function contextCommand($commandsStr) {
        try {
            // Extract individual commands from [@cmd1] [@cmd2] format
            preg_match_all('/\[([^\]]+)\]/', $commandsStr, $cmdMatches);
            $commands = $cmdMatches[1] ?? [];
            
            if (empty($commands)) {
                return ['error' => 'No commands found in @context. Use format: @context [@file path] [@list dir]'];
            }
            
            $contextData = [
                'commands' => $commands,
                'mode' => $GLOBALS['claudeMode'] ?? 'hybrid'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/claude_context_api');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contextData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $httpCode === 200) {
                $result = json_decode($response, true);
                if ($result && $result['success']) {
                    $output = "📡 Context API Results:\n";
                    foreach ($result['results'] as $cmdResult) {
                        $output .= "- {$cmdResult['command']}: " . ($cmdResult['executed'] ? 'executed' : 'failed') . "\n";
                    }
                    if (!empty($result['context'])) {
                        $output .= "\nContext Data:\n" . $result['context'];
                    }
                    
                    $this->logCommand('context', "context " . implode(' ', $commands), 'Context API executed');
                    return ['success' => true, 'formatted' => $output];
                } else {
                    return ['error' => 'Context API error: ' . ($result['error'] ?? 'Unknown error')];
                }
            } else {
                return ['error' => "Context API request failed (HTTP $httpCode)"];
            }
        } catch (\Exception $e) {
            return ['error' => 'Error executing context command: ' . $e->getMessage()];
        }
    }
    
    private function logCommand($command, $input, $output) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            
            // Create table if not exists
            $db->executeSQL("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTOINCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)", 'claude');
            
            // Log the command with null checks
            $safeCommand = $command ?: 'unknown';
            $safeInput = $input ?: 'unknown_command';
            $safeOutput = $output ?: '';
            
            // Use command + input for the command field
            $fullCommand = $safeCommand . ': ' . $safeInput;
            
            // Use raw SQL - DatabaseManager doesn't support parameters
            $escapedCommand = str_replace("'", "''", $fullCommand);
            $escapedOutput = str_replace("'", "''", $safeOutput);
            
            // Get current timestamp in proper timezone
            $timezone = \ZeroAI\Core\TimezoneManager::getInstance();
            $timestamp = $timezone->getCurrentTime();
            
            $db->executeSQL("INSERT INTO command_history (command, output, status, model_used, session_id, timestamp) VALUES ('$escapedCommand', '$escapedOutput', 'success', 'claude-unified', 1, '$timestamp')", 'claude');
                
        } catch (\Exception $e) {
            error_log("Failed to log Claude command: " . $e->getMessage());
        }
    }
}
?>