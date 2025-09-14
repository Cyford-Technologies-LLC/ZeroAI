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
        // Skip background commands if using high-demand models to reduce load
        if (strpos($model, 'sonnet-4') === false && strpos($model, 'opus-4') === false) {
            $backgroundResults = $this->executeBackgroundCommands($systemPrompt);
            if ($backgroundResults) {
                $systemPrompt .= "\n\nBACKGROUND RESULTS:\n" . $backgroundResults;
            }
        }
        
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
            CURLOPT_TIMEOUT => 420,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $maxRetries = 20;
        $retryCount = 0;
        
        do {
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 529 || $httpCode === 429) {
                $retryCount++;
                if ($retryCount < $maxRetries) {
                    sleep(2 + $retryCount); // Exponential backoff
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
                        CURLOPT_TIMEOUT => 420,
                        CURLOPT_SSL_VERIFYPEER => false
                    ]);
                }
            } else {
                break;
            }
        } while ($retryCount < $maxRetries && ($httpCode === 529 || $httpCode === 429));
        
        if ($httpCode !== 200) {
            if ($httpCode === 529) {
                throw new \Exception("Claude API is overloaded. Try again in a few minutes or switch to a different model like Haiku 3.5 which may be less congested.");
            }
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
        
        // Track token usage by model
        $this->trackTokenUsage($model, $decoded['usage'] ?? []);
        
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
    
    private function trackTokenUsage($model, $usage) {
        try {
            $db = new \ZeroAI\Core\DatabaseManager();
            
            // Check if table exists first
            $tableCheck = $db->executeSQL("SELECT name FROM sqlite_master WHERE type='table' AND name='claude_token_usage'", 'claude');
            
            if (empty($tableCheck[0]['data'])) {
                // Create token usage table only if it doesn't exist
                $db->executeSQL("CREATE TABLE claude_token_usage (id INTEGER PRIMARY KEY AUTOINCREMENT, model TEXT NOT NULL, input_tokens INTEGER DEFAULT 0, output_tokens INTEGER DEFAULT 0, total_tokens INTEGER DEFAULT 0, cost_usd REAL DEFAULT 0, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)", 'claude');
            }
            
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;
            $totalTokens = $inputTokens + $outputTokens;
            
            // Calculate cost based on model pricing (per 1M tokens)
            $cost = $this->calculateCost($model, $inputTokens, $outputTokens);
            
            $escapedModel = str_replace("'", "''", $model);
            $db->executeSQL("INSERT INTO claude_token_usage (model, input_tokens, output_tokens, total_tokens, cost_usd) VALUES ('$escapedModel', $inputTokens, $outputTokens, $totalTokens, $cost)", 'claude');
            
        } catch (\Exception $e) {
            error_log("Failed to track token usage: " . $e->getMessage());
        }
    }
    
    private function calculateCost($model, $inputTokens, $outputTokens) {
        // Claude pricing per 1M tokens (as of 2024)
        $pricing = [
            'claude-sonnet-4-20250514' => ['input' => 3.00, 'output' => 15.00],
            'claude-opus-4-1-20250805' => ['input' => 15.00, 'output' => 75.00],
            'claude-opus-4-20250514' => ['input' => 15.00, 'output' => 75.00],
            'claude-sonnet-3.7-20250514' => ['input' => 3.00, 'output' => 15.00],
            'claude-haiku-3.5-20250514' => ['input' => 0.25, 'output' => 1.25],
            'claude-3-opus-20240229' => ['input' => 15.00, 'output' => 75.00],
            'claude-3-5-sonnet-20240620' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-sonnet-20240229' => ['input' => 3.00, 'output' => 15.00],
            'claude-haiku-3-20240307' => ['input' => 0.25, 'output' => 1.25]
        ];
        
        $modelPricing = $pricing[$model] ?? ['input' => 3.00, 'output' => 15.00]; // Default to Sonnet pricing
        
        $inputCost = ($inputTokens / 1000000) * $modelPricing['input'];
        $outputCost = ($outputTokens / 1000000) * $modelPricing['output'];
        
        return $inputCost + $outputCost;
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