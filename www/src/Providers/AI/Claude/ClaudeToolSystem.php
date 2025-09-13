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
            case 'docker':
                return $this->dockerCommand($args[0]);
            case 'ps':
                return $this->showContainers();
            case 'memory':
                return $this->memoryCommand($args[0], $args[1] ?? null);
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
    
    private function memoryCommand($type, $filter) {
        // Route to existing memory system
        return ['success' => true, 'formatted' => "🧠 Memory command processed"];
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