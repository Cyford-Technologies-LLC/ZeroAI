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
        try {
            require_once '/app/www/api/crew_context.php';
            $crewContext = new \CrewContextManager();
            $runningCrews = $crewContext->getRunningCrews();
            $recentCrews = $crewContext->getRecentCrewExecutions(5);
            
            $output = "🚀 Crew Status:\n\n";
            
            if (!empty($runningCrews)) {
                $output .= "Currently Running Crews:\n";
                foreach ($runningCrews as $crew) {
                    $output .= "- Task ID: {$crew['task_id']}, Project: {$crew['project_id']}, Prompt: {$crew['prompt']}\n";
                }
                $output .= "\n";
            }
            
            if (!empty($recentCrews)) {
                $output .= "Recent Crew Executions:\n";
                foreach ($recentCrews as $crew) {
                    $output .= "- Task ID: {$crew['task_id']}, Status: {$crew['status']}, Project: {$crew['project_id']}\n";
                }
            }
            
            if (empty($runningCrews) && empty($recentCrews)) {
                $output .= "No active or recent crews found.";
            }
            
            $this->logCommand('crews', 'list crews', 'Crews listed successfully');
            return ['success' => true, 'formatted' => $output];
        } catch (\Exception $e) {
            return ['error' => 'Error loading crews: ' . $e->getMessage()];
        }
    }
    
    private function analyzeCrew($taskId) {
        try {
            require_once '/app/www/api/crew_context.php';
            $crewContext = new \CrewContextManager();
            $execution = $crewContext->getCrewExecution($taskId);
            
            if ($execution) {
                $output = "📊 Crew Execution Details for {$taskId}:\n" . json_encode($execution, JSON_PRETTY_PRINT);
                $this->logCommand('analyze_crew', "analyze crew $taskId", 'Crew analysis completed');
                return ['success' => true, 'formatted' => $output];
            } else {
                return ['error' => "Crew execution not found: {$taskId}"];
            }
        } catch (\Exception $e) {
            return ['error' => 'Error analyzing crew: ' . $e->getMessage()];
        }
    }
    
    private function getCrewLogs($days, $agentRole) {
        try {
            $logDir = '/app/logs/crews';
            if (!is_dir($logDir)) {
                return ['error' => 'Crew logs directory not found'];
            }
            
            $recentLogs = [];
            $currentDate = new \DateTime();
            
            for ($i = 0; $i < $days; $i++) {
                $date = clone $currentDate;
                $date->sub(new \DateInterval('P' . $i . 'D'));
                $logFile = $logDir . '/crew_conversations_' . $date->format('Y-m-d') . '.jsonl';
                
                if (file_exists($logFile)) {
                    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $entry = json_decode($line, true);
                        if ($entry && (!$agentRole || $entry['agent_role'] === $agentRole)) {
                            $recentLogs[] = $entry;
                        }
                    }
                }
            }
            
            if (!empty($recentLogs)) {
                usort($recentLogs, function($a, $b) { return strcmp($b['timestamp'], $a['timestamp']); });
                $output = "📋 Recent Crew Logs (" . count($recentLogs) . " entries):\n\n";
                foreach (array_slice($recentLogs, 0, 20) as $log) {
                    $output .= "[{$log['timestamp']}] {$log['agent_role']}: {$log['prompt']}\n";
                }
            } else {
                $output = "📋 No crew logs found for the specified criteria.";
            }
            
            $this->logCommand('logs', "get logs $days days" . ($agentRole ? " role=$agentRole" : ""), count($recentLogs) . ' logs found');
            return ['success' => true, 'formatted' => $output];
        } catch (\Exception $e) {
            return ['error' => 'Error loading crew logs: ' . $e->getMessage()];
        }
    }
    
    private function optimizeAgents() {
        try {
            $logDir = '/app/logs/crews';
            if (!is_dir($logDir)) {
                return ['error' => 'Crew logs directory not found'];
            }
            
            $logFiles = glob($logDir . '/crew_conversations_*.jsonl');
            $agentStats = [];
            
            foreach ($logFiles as $logFile) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $entry = json_decode($line, true);
                    if ($entry) {
                        $role = $entry['agent_role'];
                        if (!isset($agentStats[$role])) {
                            $agentStats[$role] = ['count' => 0, 'avg_response_len' => 0, 'total_response_len' => 0];
                        }
                        $agentStats[$role]['count']++;
                        $agentStats[$role]['total_response_len'] += strlen($entry['response']);
                    }
                }
            }
            
            foreach ($agentStats as $role => &$stats) {
                $stats['avg_response_len'] = $stats['count'] > 0 ? $stats['total_response_len'] / $stats['count'] : 0;
            }
            
            $output = "📈 Agent Performance Analysis:\n" . json_encode($agentStats, JSON_PRETTY_PRINT);
            $this->logCommand('optimize_agents', 'analyze agent performance', count($agentStats) . ' agents analyzed');
            return ['success' => true, 'formatted' => $output];
        } catch (\Exception $e) {
            return ['error' => 'Error optimizing agents: ' . $e->getMessage()];
        }
    }
    
    private function trainAgents() {
        try {
            $response = file_get_contents('http://localhost/api/claude_autonomous.php', false, stream_context_create([
                'http' => ['method' => 'POST']
            ]));
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $output = "🎓 Autonomous Training Results:\n" . implode("\n", $result['improvements']);
                $this->logCommand('train_agents', 'autonomous training', 'Training completed successfully');
                return ['success' => true, 'formatted' => $output];
            } else {
                return ['error' => 'Autonomous training failed: ' . ($result['error'] ?? 'Unknown error')];
            }
        } catch (\Exception $e) {
            return ['error' => 'Error training agents: ' . $e->getMessage()];
        }
    }
    
    private function memoryCommand($type, $filter) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $memoryData = [];
            
            if ($type === 'chat' && preg_match('/(\d+)min/', $filter, $timeMatch)) {
                $minutes = (int)$timeMatch[1];
                $result = $db->executeSQL("SELECT sender, message, model_used, timestamp FROM chat_history WHERE timestamp >= datetime('now', '-{$minutes} minutes') ORDER BY timestamp DESC", 'claude');
                $memoryData = $result[0]['data'] ?? [];
            } elseif ($type === 'commands' && preg_match('/(\d+)min/', $filter, $timeMatch)) {
                $minutes = (int)$timeMatch[1];
                $result = $db->executeSQL("SELECT command, output, status, model_used, timestamp FROM command_history WHERE datetime(timestamp) >= datetime('now', '-{$minutes} minutes') ORDER BY timestamp DESC", 'claude');
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
            $output .= "File content of sessions/memory.json:\n" . json_encode($memoryData, JSON_PRETTY_PRINT) . "\n";
            $output .= "[View Memory File](../knowledge/internal_crew/agent_learning/self/claude/sessions/memory.json)";
            
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
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/claude_context_api.php');
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
            $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
            if (!is_dir($memoryDir)) {
                mkdir($memoryDir, 0777, true);
            }
            
            $dbPath = $memoryDir . '/claude_memory.db';
            $pdo = new \PDO("sqlite:$dbPath");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTOINCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
            
            $pdo->prepare("INSERT INTO command_history (command, output, status, model_used, session_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$input, $output, 'success', 'claude-unified', 1]);
                
        } catch (\Exception $e) {
            error_log("Failed to log Claude command: " . $e->getMessage());
        }
    }
}
?>