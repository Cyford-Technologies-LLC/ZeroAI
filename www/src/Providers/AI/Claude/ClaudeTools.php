<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeTools {
    private $security;
    
    public function __construct() {
        $this->security = new \ZeroAI\Core\Security();
    }
    
    public function executeCommand($container, $command, $mode = 'hybrid') {
        if (!$this->security->hasPermission('claude', 'docker_exec', $mode)) {
            return ['error' => 'Permission denied: docker exec'];
        }
        
        $result = shell_exec("docker exec " . escapeshellarg($container) . " {$command} 2>&1");
        
        // Log to Claude's database
        $this->logCommand('exec', "docker exec {$container} {$command}", $result);
        
        return [
            'success' => true,
            'output' => $result ?: 'No output',
            'command' => "docker exec {$container} {$command}"
        ];
    }
    
    public function readFile($path, $mode = 'hybrid') {
        if (!$this->security->hasPermission('claude', 'cmd_file', $mode)) {
            return ['error' => 'Permission denied: file read'];
        }
        
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
            'path' => $path
        ];
    }
    
    public function listDirectory($path, $mode = 'hybrid') {
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
            'path' => $path
        ];
    }
    
    public function getMemory($type, $filter = null) {
        try {
            $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
            $dbPath = $memoryDir . '/claude_memory.db';
            
            if (!file_exists($dbPath)) {
                return "🧠 Memory: No command history found";
            }
            
            $pdo = new \PDO("sqlite:$dbPath");
            
            if ($type === 'commands') {
                $stmt = $pdo->prepare("SELECT command, output, timestamp FROM command_history ORDER BY timestamp DESC LIMIT 10");
                $stmt->execute();
                $commands = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $result = "🧠 Memory: Recent Commands\n";
                foreach ($commands as $cmd) {
                    $time = date('M d H:i', strtotime($cmd['timestamp']));
                    $result .= "[$time] {$cmd['command']}\n";
                }
                return $result;
            }
            
            return "🧠 Memory: Unknown type '$type'";
            
        } catch (\Exception $e) {
            return "🧠 Memory: Error - " . $e->getMessage();
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
                ->execute([$input, $output, 'success', 'claude-tools', 1]);
                
        } catch (\Exception $e) {
            error_log("Failed to log Claude tool: " . $e->getMessage());
        }
    }
}
?>
