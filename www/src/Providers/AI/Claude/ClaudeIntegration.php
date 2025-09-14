<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    private $toolSystem;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->toolSystem = new ClaudeToolSystem();
    }
    
    public function chatWithClaude($message, $systemPrompt, $model, $conversationHistory = []) {
        // Skip background commands to prevent timeout - Claude can request manually
        // $backgroundResults = $this->executeBackgroundCommands($systemPrompt);
        
        // Check if Claude needs to use tools before generating response
        $toolResults = $this->processTools($message);
        if ($toolResults) {
            $message .= "\n\nTool Results:\n" . $toolResults;
        }
        
        $messages = [];
        
        // Use pre-converted history from ClaudeProvider
        if (is_array($conversationHistory) && !empty($conversationHistory)) {
            foreach ($conversationHistory as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = $msg;
                }
            }
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        // Ensure system prompt is not empty
        if (empty($systemPrompt)) {
            $systemPrompt = 'You are Claude, integrated into ZeroAI.';
        }
        
        $data = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemPrompt
                ]
            ],
            'messages' => $messages
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("API request failed with status $httpCode: $response");
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded || !isset($decoded['content'][0]['text'])) {
            throw new \Exception('Invalid API response format');
        }
        
        $claudeResponse = $decoded['content'][0]['text'];
        
        // Process Claude's commands and show results
        $claudeToolResults = $this->processTools($claudeResponse);
        if ($claudeToolResults) {
            $claudeResponse .= "\n\nTool Results:\n" . $claudeToolResults;
        }
        
        return [
            'message' => $claudeResponse,
            'usage' => $decoded['usage'] ?? [],
            'model' => $decoded['model'] ?? $model
        ];
    }
    
    private function processTools($message) {
        $results = '';
        $mode = $GLOBALS['claudeMode'] ?? 'hybrid';
        // Convert autonomous to agentic for tool permissions
        if ($mode === 'autonomous') $mode = 'agentic';
        
        // Process @exec commands
        if (preg_match_all('/@exec\s+([^\s]+)\s+(.+?)(?=\n@|\n\n|$)/ms', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $container = trim($match[1]);
                $command = trim($match[2]);
                if ($container && $command) {
                    $result = $this->toolSystem->execute('exec', [$container, $command], $mode);
                    if (isset($result['success'])) {
                        $results .= $result['formatted'] . "\n\n";
                    } else {
                        $results .= "❌ " . $result['error'] . "\n\n";
                    }
                }
            }
        }
        
        // Process @file commands
        if (preg_match_all('/@file\s+([^\s]+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('file', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @list commands
        if (preg_match_all('/@list\s+([^\s]+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('list', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @agents commands
        if (preg_match('/@agents/', $message)) {
            $result = $this->toolSystem->execute('agents', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @update_agent commands
        if (preg_match_all('/@update_agent\s+(\d+)\s+(.+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('update_agent', [$match[1], $match[2]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @crews commands
        if (preg_match('/@crews/', $message)) {
            $result = $this->toolSystem->execute('crews', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @analyze_crew commands
        if (preg_match_all('/@analyze_crew\s+([^\s]+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('analyze_crew', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @logs commands
        if (preg_match('/@logs(?:\s+(\d+))?(?:\s+(\w+))?/', $message, $matches)) {
            $days = isset($matches[1]) ? (int)$matches[1] : 7;
            $agentRole = isset($matches[2]) ? $matches[2] : null;
            $result = $this->toolSystem->execute('logs', [$days, $agentRole], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @optimize_agents commands
        if (preg_match('/@optimize_agents/', $message)) {
            $result = $this->toolSystem->execute('optimize_agents', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @train_agents commands
        if (preg_match('/@train_agents/', $message)) {
            $result = $this->toolSystem->execute('train_agents', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @memory commands
        if (preg_match('/@memory\s+(chat|commands|search|config|sessions)\s*(.*)/', $message, $matches)) {
            $action = $matches[1];
            $params = trim($matches[2]);
            $result = $this->toolSystem->execute('memory', [$action, $params], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @context commands
        if (preg_match('/@context\s+(.+)/', $message, $matches)) {
            $commandsStr = trim($matches[1]);
            $result = $this->toolSystem->execute('context', [$commandsStr], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @docker commands
        if (preg_match_all('/@docker\s+(.+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('docker', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @ps commands
        if (preg_match('/@ps/', $message)) {
            $result = $this->toolSystem->execute('ps', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        return $results;
    }
    
    private function executeBackgroundCommands($systemPrompt) {
        // Get main container name dynamically
        $containerName = $this->getMainContainer();
        
        // Auto-execute essential status commands for Claude (reduced for performance)
        $backgroundCommands = [
            ['ps', []],
            ['agents', []]
        ];
        
        // Add git commands if container found
        if ($containerName) {
            $backgroundCommands[] = ['exec', [$containerName, 'git status']];
            $backgroundCommands[] = ['exec', [$containerName, 'git branch']];
        }
        
        $results = '';
        foreach ($backgroundCommands as $cmd) {
            $result = $this->toolSystem->execute($cmd[0], $cmd[1], 'hybrid');
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            }
        }
        
        return $results;
    }
    
    private function getMainContainer() {
        // Use docker ps --format to get just container names
        $result = shell_exec("docker ps --format '{{.Names}}' 2>&1");
        
        if ($result) {
            $lines = explode("\n", trim($result));
            foreach ($lines as $containerName) {
                $containerName = trim($containerName);
                if ($containerName && strpos($containerName, 'zeroai') !== false && strpos($containerName, 'api') !== false) {
                    return $containerName;
                }
            }
        }
        return 'zeroai_api-test'; // Fallback to known container
    }
}
?>