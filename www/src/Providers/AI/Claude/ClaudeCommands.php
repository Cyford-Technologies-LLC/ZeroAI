<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeCommands {
    private $security;
    
    public function __construct() {
        $this->security = new \ZeroAI\Core\Security();
        // Initialize global for command tracking like old system
        if (!isset($GLOBALS['executedCommands'])) {
            $GLOBALS['executedCommands'] = [];
        }
    }
    
    public function processFileCommands(&$message, $user = 'claude', $mode = 'hybrid') {
        error_log("[CLAUDE_COMMANDS] Processing file commands for user: $user, mode: $mode");
        $message = preg_replace_callback('/\@file\s+([^\s]+)/', [$this, 'readFile'], $message);
        $message = preg_replace_callback('/\@read\s+([^\s]+)/', [$this, 'readFile'], $message);
        $message = preg_replace_callback('/\@list\s+([^\s]+)/', [$this, 'listDirectory'], $message);
        $message = preg_replace_callback('/\@search\s+([^\s]+)/', [$this, 'searchFiles'], $message);
        $message = preg_replace_callback('/\@create\s+([^\s]+)\s+```([^`]+)```/', [$this, 'createFile'], $message);
        $message = preg_replace_callback('/\@edit\s+([^\s]+)\s+```([^`]+)```/', [$this, 'editFile'], $message);
        $message = preg_replace_callback('/\@append\s+([^\s]+)\s+```([^`]+)```/', [$this, 'appendFile'], $message);
        $message = preg_replace_callback('/\@delete\s+([^\s]+)/', [$this, 'deleteFile'], $message);
    }
    
    public function processClaudeCommands(&$message, $user = 'claude', $mode = 'hybrid') {
        error_log("[CLAUDE_COMMANDS] Processing claude commands for user: $user, mode: $mode");
        error_log("[CLAUDE_COMMANDS] Message content: " . substr($message, 0, 200));
        
        if (strpos($message, '@exec') !== false) {
            error_log("[CLAUDE_COMMANDS] Found @exec in message");
        }
        if (strpos($message, 'exec ') !== false) {
            error_log("[CLAUDE_COMMANDS] Found exec command in message");
        }
        
        $message = preg_replace_callback('/\@agents/', [$this, 'listAgents'], $message);
        $message = preg_replace_callback('/\@docker\s+(.+)/', [$this, 'dockerCommand'], $message);
        $message = preg_replace_callback('/\@compose\s+(.+)/', [$this, 'composeCommand'], $message);
        $message = preg_replace_callback('/\@ps/', [$this, 'showContainers'], $message);
        $originalLength = strlen($message);
        // Handle both @exec and exec patterns
        $message = preg_replace_callback('/\@exec\s+([^\s]+)\s+(.+)/s', [$this, 'execContainer'], $message);
        $message = preg_replace_callback('/^exec\s+([^\s]+)\s+(.+)/m', [$this, 'execContainer'], $message);
        if (strlen($message) > $originalLength) {
            error_log("[CLAUDE_COMMANDS] Exec command processed successfully");
        } else if (strpos($message, '@exec') !== false) {
            error_log("[CLAUDE_COMMANDS] Exec command found but not processed");
        }
        $message = preg_replace_callback('/\@bash\s+(.+)/', [$this, 'bashCommand'], $message);
    }
    
    private function readFile($matches) {
        if (!$this->security->hasPermission('claude', 'cmd_file', 'hybrid')) {
            return "\n❌ Permission denied: file read\n";
        }
        
        $path = $matches[1];
        if (!str_starts_with($path, '/')) {
            $path = '/app/' . $path;
        }
        if (file_exists($path)) {
            $result = "\n\n📄 File: {$matches[1]}\n```\n" . file_get_contents($path) . "\n```\n";
            $GLOBALS['executedCommands'][] = ['command' => "file {$matches[1]}", 'output' => 'File read successfully'];
            return $result;
        }
        return "\n❌ File not found: {$matches[1]} (tried: $path)\n";
    }
    
    private function listDirectory($matches) {
        $path = $matches[1];
        if (!str_starts_with($path, '/')) {
            $path = '/app/' . $path;
        }
        if (is_dir($path)) {
            $files = scandir($path);
            $list = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
            $result = "\n\n📁 Directory: {$matches[1]}\n" . implode("\n", $list) . "\n";
            $GLOBALS['executedCommands'][] = ['command' => "list {$matches[1]}", 'output' => count($list) . ' files found'];
            return $result;
        }
        return "\n❌ Directory not found: {$matches[1]} (tried: $path)\n";
    }
    
    private function searchFiles($matches) {
        $pattern = $matches[1];
        $result = shell_exec("find /app -name '*{$pattern}*' 2>/dev/null | head -20");
        return "\n\n🔍 Search results for '{$pattern}':\n" . ($result ?: "No files found") . "\n";
    }
    
    private function createFile($matches) {
        if (!$this->security->hasPermission('claude', 'file_write', 'autonomous')) {
            return "\n❌ Permission denied: file creation requires autonomous mode\n";
        }
        
        $path = '/app/' . ltrim($matches[1], '/');
        $content = trim($matches[2]);
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (file_put_contents($path, $content)) {
            return "\n✅ Created file: {$matches[1]}\n";
        }
        return "\n❌ Failed to create file: {$matches[1]}\n";
    }
    
    private function editFile($matches) {
        if (!$this->security->hasPermission('claude', 'file_write', 'autonomous')) {
            return "\n❌ Permission denied: file editing requires autonomous mode\n";
        }
        
        $path = '/app/' . ltrim($matches[1], '/');
        $content = trim($matches[2]);
        
        if (file_put_contents($path, $content)) {
            return "\n✅ Updated file: {$matches[1]}\n";
        }
        return "\n❌ Failed to update file: {$matches[1]}\n";
    }
    
    private function appendFile($matches) {
        $path = '/app/' . ltrim($matches[1], '/');
        $content = trim($matches[2]);
        
        if (file_put_contents($path, $content, FILE_APPEND)) {
            return "\n✅ Appended to file: {$matches[1]}\n";
        }
        return "\n❌ Failed to append to file: {$matches[1]}\n";
    }
    
    private function deleteFile($matches) {
        $path = '/app/' . ltrim($matches[1], '/');
        
        if (file_exists($path) && unlink($path)) {
            return "\n✅ Deleted file: {$matches[1]}\n";
        }
        return "\n❌ Failed to delete file: {$matches[1]}\n";
    }
    
    private function listAgents() {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            $result = $db->query("SELECT * FROM agents ORDER BY id");
            
            $output = "\n\n🤖 Agents:\n";
            if (!empty($result)) {
                foreach ($result as $a) {
                    $output .= "ID: {$a['id']} | Role: {$a['role']} | Goal: {$a['goal']}\n";
                }
                $GLOBALS['executedCommands'][] = ['command' => 'agents', 'output' => count($result) . ' agents listed'];
            } else {
                $output .= "No agents found\n";
                $GLOBALS['executedCommands'][] = ['command' => 'agents', 'output' => 'No agents found'];
            }
            return $output;
        } catch (Exception $e) {
            return "\n\n❌ Error loading agents: " . $e->getMessage() . "\n";
        }
    }
    
    private function dockerCommand($matches) {
        $cmd = escapeshellcmd($matches[1]);
        $result = shell_exec("docker exec {$container} bash -c " . escapeshellarg($cmd) . " 2>&1");
        return "\n\n🐳 Docker: {$matches[1]}\n" . ($result ?: "No output") . "\n";
    }
    
    private function composeCommand($matches) {
        $cmd = escapeshellcmd($matches[1]);
        $result = shell_exec("docker-compose {$cmd} 2>&1");
        return "\n\n🐳 Compose: {$matches[1]}\n" . ($result ?: "No output") . "\n";
    }
    
    private function showContainers() {
        $result = shell_exec("docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1");
        return "\n\n🐳 Running Containers:\n" . ($result ?: "No containers") . "\n";
    }
    
    private function execContainer($matches) {
        if (!$this->security->hasPermission('claude', 'docker_exec', 'hybrid')) {
            return "\n❌ Permission denied: docker exec\n";
        }
        
        $container = escapeshellarg($matches[1]);
        $cmd = $matches[2];
        $result = shell_exec("docker exec {$container} {$cmd} 2>&1");
        
        // Track command like old system
        $GLOBALS['executedCommands'][] = [
            'command' => "exec {$matches[1]} {$matches[2]}",
            'output' => $result ?: "No output"
        ];
        
        return "\n\n🐳 Exec {$matches[1]}: {$matches[2]}\n" . ($result ?: "No output") . "\n";
    }
    
    private function bashCommand($matches) {
        if (!$this->security->hasPermission('claude', 'cmd_exec', 'hybrid')) {
            return "\n❌ Permission denied: bash command\n";
        }
        
        $cmd = $matches[1];
        $result = shell_exec($cmd . " 2>&1");
        
        // Log to Claude's personal database
        $this->logToClaudeMemory('bash', $cmd, $result);
        
        return "\n\n💻 Bash: {$cmd}\n" . ($result ?: "No output") . "\n";
    }
    
    private function logToClaudeMemory($command, $input, $output) {
        try {
            $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
            if (!is_dir($memoryDir)) {
                mkdir($memoryDir, 0777, true);
            }
            
            $dbPath = $memoryDir . '/claude_memory.db';
            $pdo = new \PDO("sqlite:$dbPath");
            
            // Create table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS command_history (id INTEGER PRIMARY KEY AUTOINCREMENT, command TEXT NOT NULL, output TEXT, status TEXT NOT NULL, model_used TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, session_id INTEGER)");
            
            $pdo->prepare("INSERT INTO command_history (command, output, status, model_used) VALUES (?, ?, ?, ?)")
                ->execute([$command . ': ' . $input, $output, 'success', 'claude']);
                
        } catch (\Exception $e) {
            error_log("Failed to log to Claude memory: " . $e->getMessage());
        }
    }
}


