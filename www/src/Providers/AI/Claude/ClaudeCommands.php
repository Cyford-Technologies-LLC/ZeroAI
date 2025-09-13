<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeCommands {
    private $security;
    
    public function __construct() {
        $this->security = new \ZeroAI\Core\Security();
    }
    
    public function processFileCommands(&$message, $user = 'claude', $mode = 'hybrid') {
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
        $message = preg_replace_callback('/\@agents/', [$this, 'listAgents'], $message);
        $message = preg_replace_callback('/\@docker\s+(.+)/', [$this, 'dockerCommand'], $message);
        $message = preg_replace_callback('/\@compose\s+(.+)/', [$this, 'composeCommand'], $message);
        $message = preg_replace_callback('/\@ps/', [$this, 'showContainers'], $message);
        $message = preg_replace_callback('/\@exec\s+([^\s]+)\s+(.+)/', [$this, 'execContainer'], $message);
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
            return "\n\n📄 File: {$matches[1]}\n```\n" . file_get_contents($path) . "\n```\n";
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
            return "\n\n📁 Directory: {$matches[1]}\n" . implode("\n", $list) . "\n";
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
            $result = $db->executeSQL("SELECT * FROM agents ORDER BY id");
            
            $output = "\n\n🤖 Agents:\n";
            if (!empty($result[0]['data'])) {
                foreach ($result[0]['data'] as $a) {
                    $output .= "ID: {$a['id']} | Role: {$a['role']} | Goal: {$a['goal']}\n";
                }
            } else {
                $output .= "No agents found\n";
            }
            return $output;
        } catch (Exception $e) {
            return "\n\n❌ Error loading agents: " . $e->getMessage() . "\n";
        }
    }
    
    private function dockerCommand($matches) {
        $cmd = escapeshellcmd($matches[1]);
        $result = shell_exec("docker {$cmd} 2>&1");
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
        $cmd = $matches[2]; // Don't escape the full command
        $result = shell_exec("docker exec {$container} {$cmd} 2>&1");
        return "\n\n🐳 Exec {$matches[1]}: {$matches[2]}\n" . ($result ?: "No output") . "\n";
    }
    
    private function bashCommand($matches) {
        if (!$this->security->hasPermission('claude', 'cmd_exec', 'hybrid')) {
            return "\n❌ Permission denied: bash command\n";
        }
        
        $cmd = $matches[1];
        $result = shell_exec($cmd . " 2>&1");
        return "\n\n💻 Bash: {$cmd}\n" . ($result ?: "No output") . "\n";
    }
}