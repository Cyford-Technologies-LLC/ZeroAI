<?php
namespace Core;

class ClaudeContext extends ChatContext {
    private $apiKey;
    
    public function __construct() {
        parent::__construct();
        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
    }
    
    public function getSystemPrompt(string $mode): string {
        $basePrompt = "You are Claude, integrated into ZeroAI.\n\n";
        
        $modePrompts = [
            'autonomous' => "[AUTONOMOUS MODE] You have FULL access including write commands: @create, @edit, @append, @delete.",
            'hybrid' => "[HYBRID MODE] You have read-only access: @file, @list, @exec, @agents, @crews, @logs.",
            'chat' => "[CHAT MODE] You have read-only access: @file, @list, @agents, @crews, @logs."
        ];
        
        $commands = "\n\nCOMMANDS:\n";
        $commands .= "- @file path - Read file contents\n";
        $commands .= "- @list path - List directory contents\n";
        $commands .= "- @exec container cmd - Execute command\n";
        $commands .= "- @agents - List all agents\n";
        $commands .= "- @crews - Show crew status\n";
        $commands .= "- @logs - Show recent logs\n";
        
        if ($mode === 'autonomous') {
            $commands .= "- @create path ```content``` - Create file\n";
            $commands .= "- @edit path ```content``` - Edit file\n";
            $commands .= "- @append path ```content``` - Append to file\n";
            $commands .= "- @delete path - Delete file\n";
        }
        
        return $basePrompt . ($modePrompts[$mode] ?? '') . $commands;
    }
    
    public function processCommands(string $message, string $mode): string {
        $originalMessage = $message;
        $commandOutputs = '';
        
        // Extract and execute commands
        preg_match_all('/@(\w+)\s*([^\n]*)/u', $message, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $command = $match[1];
            $params = trim($match[2] ?? '');
            
            try {
                $result = $this->executeClaudeCommand($command, $params, $mode);
                if ($result['success']) {
                    $commandOutputs .= "\n\n" . $result['output'];
                } else {
                    $commandOutputs .= "\n\n[ERROR] " . $result['error'];
                }
            } catch (\Exception $e) {
                $commandOutputs .= "\n\n[ERROR] " . $e->getMessage();
            }
        }
        
        return $originalMessage . $commandOutputs;
    }
    
    private function executeClaudeCommand(string $command, string $params, string $mode): array {
        if (!$this->hasPermission('claude', "cmd_$command", $mode)) {
            return [
                'success' => false,
                'error' => "Command '$command' not allowed in $mode mode"
            ];
        }
        
        return match($command) {
            'file' => $this->cmdFile($params),
            'list' => $this->cmdList($params),
            'exec' => $this->cmdExec($params),
            'agents' => $this->cmdAgents(),
            'crews' => $this->cmdCrews(),
            'logs' => $this->cmdLogs(),
            'create' => $this->cmdCreate($params),
            'edit' => $this->cmdEdit($params),
            'append' => $this->cmdAppend($params),
            'delete' => $this->cmdDelete($params),
            default => ['success' => false, 'error' => "Unknown command: $command"]
        };
    }
    
    private function cmdFile(string $path): array {
        try {
            $result = $this->executeCommand('file_read', ['path' => $path], 'claude');
            return [
                'success' => $result['success'],
                'output' => $result['success'] ? "File: $path\n" . $result['data'] : $result['error']
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function cmdList(string $path): array {
        try {
            $result = $this->executeCommand('file_list', ['path' => $path], 'claude');
            return [
                'success' => $result['success'],
                'output' => $result['success'] ? "Directory: $path\n" . implode("\n", $result['data']) : $result['error']
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function cmdExec(string $params): array {
        $parts = explode(' ', $params, 2);
        $container = $parts[0] ?? '';
        $cmd = $parts[1] ?? '';
        
        try {
            $result = $this->executeCommand('docker_exec', ['container' => $container, 'cmd' => $cmd], 'claude');
            return [
                'success' => $result['success'],
                'output' => $result['success'] ? "Exec [$container]: $cmd\n" . $result['data'] : $result['error']
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function cmdAgents(): array {
        try {
            $db = $this->system->getDatabase();
            $result = $db->executeSQL("SELECT id, name, role, status FROM agents ORDER BY name");
            
            if (!empty($result[0]['data'])) {
                $output = "Agents:\n";
                foreach ($result[0]['data'] as $agent) {
                    $output .= "- ID: {$agent['id']}, Name: {$agent['name']}, Role: {$agent['role']}\n";
                }
            } else {
                $output = "No agents found";
            }
            
            return ['success' => true, 'output' => $output];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function cmdCrews(): array {
        try {
            $db = $this->system->getDatabase();
            $result = $db->executeSQL("SELECT id, name, status FROM crews ORDER BY name");
            
            if (!empty($result[0]['data'])) {
                $output = "Crews:\n";
                foreach ($result[0]['data'] as $crew) {
                    $output .= "- ID: {$crew['id']}, Name: {$crew['name']}, Status: {$crew['status']}\n";
                }
            } else {
                $output = "No crews found";
            }
            
            return ['success' => true, 'output' => $output];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function cmdLogs(): array {
        try {
            $logs = $this->system->getLogger()->getRecentLogs('ai', 10);
            $output = "Recent Logs:\n" . implode("\n", $logs);
            return ['success' => true, 'output' => $output];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function cmdCreate(string $params): array {
        // Parse @create path ```content```
        if (preg_match('/^([^\s]+)\s+```(.*)```$/s', $params, $matches)) {
            $path = $matches[1];
            $content = $matches[2];
            
            try {
                $result = $this->executeCommand('file_write', ['path' => $path, 'content' => $content], 'claude');
                return [
                    'success' => $result['success'],
                    'output' => $result['success'] ? "Created: $path" : $result['error']
                ];
            } catch (\Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return ['success' => false, 'error' => 'Invalid create syntax. Use: @create path ```content```'];
    }
    
    private function cmdEdit(string $params): array {
        return $this->cmdCreate($params); // Same logic as create
    }
    
    private function cmdAppend(string $params): array {
        if (preg_match('/^([^\s]+)\s+```(.*)```$/s', $params, $matches)) {
            $path = $matches[1];
            $content = $matches[2];
            
            try {
                // Read existing content first
                $existing = $this->executeCommand('file_read', ['path' => $path], 'claude');
                if ($existing['success']) {
                    $newContent = $existing['data'] . "\n" . $content;
                } else {
                    $newContent = $content;
                }
                
                $result = $this->executeCommand('file_write', ['path' => $path, 'content' => $newContent], 'claude');
                return [
                    'success' => $result['success'],
                    'output' => $result['success'] ? "Appended to: $path" : $result['error']
                ];
            } catch (\Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return ['success' => false, 'error' => 'Invalid append syntax. Use: @append path ```content```'];
    }
    
    private function cmdDelete(string $path): array {
        try {
            $fullPath = $this->system->resolvePath($path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
                return ['success' => true, 'output' => "Deleted: $path"];
            } else {
                return ['success' => false, 'error' => "File not found: $path"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function generateResponse(string $message, string $systemPrompt, string $mode): array {
        if (!$this->apiKey) {
            throw new \Exception('Anthropic API key not configured');
        }
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        $data = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $message]]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("API request failed with code: $httpCode");
        }
        
        $decoded = json_decode($response, true);
        if (!$decoded || !isset($decoded['content'][0]['text'])) {
            throw new \Exception("Invalid API response");
        }
        
        return [
            'message' => $decoded['content'][0]['text'],
            'tokens' => $decoded['usage']['total_tokens'] ?? 0,
            'model' => $decoded['model'] ?? 'claude-3-5-sonnet-20241022'
        ];
    }
}
