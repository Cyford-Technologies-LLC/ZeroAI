<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeCommands {
    
    public function processFileCommands(&$message) {
        $message = preg_replace_callback('/\@file\s+([^\s]+)/', [$this, 'readFile'], $message);
        $message = preg_replace_callback('/\@read\s+([^\s]+)/', [$this, 'readFile'], $message);
        $message = preg_replace_callback('/\@list\s+([^\s]+)/', [$this, 'listDirectory'], $message);
        $message = preg_replace_callback('/\@search\s+([^\s]+)/', [$this, 'searchFiles'], $message);
        $message = preg_replace_callback('/\@create\s+([^\s]+)\s+```([^`]+)```/', [$this, 'createFile'], $message);
        $message = preg_replace_callback('/\@edit\s+([^\s]+)\s+```([^`]+)```/', [$this, 'editFile'], $message);
        $message = preg_replace_callback('/\@append\s+([^\s]+)\s+```([^`]+)```/', [$this, 'appendFile'], $message);
        $message = preg_replace_callback('/\@delete\s+([^\s]+)/', [$this, 'deleteFile'], $message);
    }
    
    public function processClaudeCommands(&$message) {
        $message = preg_replace_callback('/\@agents/', [$this, 'listAgents'], $message);
        $message = preg_replace_callback('/\@docker\s+(.+)/', [$this, 'dockerCommand'], $message);
        $message = preg_replace_callback('/\@compose\s+(.+)/', [$this, 'composeCommand'], $message);
        $message = preg_replace_callback('/\@ps/', [$this, 'showContainers'], $message);
        $message = preg_replace_callback('/\@exec\s+([^\s]+)\s+(.+)/', [$this, 'execContainer'], $message);
    }
    
    private function readFile($matches) {
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
        require_once __DIR__ . '/../../../Models/Agent.php';
        $agent = new \ZeroAI\Models\Agent();
        $agents = $agent->getAll();
        
        $output = "\n\n🤖 Agents:\n";
        foreach ($agents as $a) {
            $output .= "ID: {$a['id']} | Role: {$a['role']} | Goal: {$a['goal']}\n";
        }
        return $output;
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
        $container = escapeshellarg($matches[1]);
        $cmd = escapeshellcmd($matches[2]);
        $result = shell_exec("docker exec {$container} {$cmd} 2>&1");
        return "\n\n🐳 Exec {$matches[1]}: {$matches[2]}\n" . ($result ?: "No output") . "\n";
    }
}