<?php

namespace ZeroAI\Providers\AI\Claude;

use ZeroAI\Providers\AI\BaseAIProvider;

class ClaudeIntegration extends BaseAIProvider {
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    private $toolSystem;
    
    public function __construct($apiKey = null, array $config = []) {
        $this->apiKey = $apiKey ?: $this->getApiKey();
        parent::__construct('Claude', $this->apiKey, $config);
        $this->toolSystem = new ClaudeToolSystem();
        $this->models = [
            'claude-opus-4-1-20250805' => ['input' => 20.00, 'output' => 100.00],
            'claude-opus-4-20250514' => ['input' => 18.00, 'output' => 90.00],
            'claude-sonnet-4-20250514' => ['input' => 4.00, 'output' => 20.00],
            'claude-3-7-sonnet-20250219' => ['input' => 3.50, 'output' => 17.50],
            'claude-3-5-haiku-20241022' => ['input' => 0.25, 'output' => 1.25]
        ];
    }
    
    public function chat(string $message, array $options = []): array {
        $systemPrompt = $options['system'] ?? 'You are Claude, integrated into ZeroAI.';
        $model = $options['model'] ?? 'claude-3-5-sonnet-20241022';
        $conversationHistory = $options['history'] ?? [];
        
        return $this->chatWithClaude($message, $systemPrompt, $model, $conversationHistory);
    }
    
    public function getModels(): array {
        $logger = \ZeroAI\Core\Logger::getInstance();
        
        // Check cache first
        try {
            $cache = \ZeroAI\Core\CacheManager::getInstance();
            $cachedModels = $cache->get('claude_api_models');
            $logger->logClaude('Cache key check', ['key' => 'claude_api_models', 'found' => $cachedModels !== null, 'empty' => empty($cachedModels)]);
            
            if ($cachedModels !== null && !empty($cachedModels)) {
                $logger->logClaude('Cache key found and not empty - using cached models', ['count' => count($cachedModels), 'models' => $cachedModels]);
                return $cachedModels;
            }
        } catch (\Exception $e) {
            $logger->logClaude('Cache check failed', ['error' => $e->getMessage()]);
        }
        
        // Try API call
        $logger->logClaude('Attempting API call to fetch models');
        $apiModels = $this->fetchRealModels();
        $logger->logClaude('API call result', ['api_models' => $apiModels, 'count' => count($apiModels)]);
        
        if (!empty($apiModels)) {
            // Cache API models
            try {
                $cache = \ZeroAI\Core\CacheManager::getInstance();
                $cache->set('claude_api_models', $apiModels, 3600);
                $logger->logClaude('API models fetched and cached', ['count' => count($apiModels), 'source' => 'api_fresh', 'models' => $apiModels]);
            } catch (\Exception $e) {
                $logger->logClaude('Failed to cache API models', ['error' => $e->getMessage()]);
            }
            return $apiModels;
        }
        
        // API failed, return hardcoded (never cache these)
        $hardcodedModels = array_keys($this->models);
        $logger->logClaude('API failed, using hardcoded models', ['count' => count($hardcodedModels), 'source' => 'hardcoded', 'models' => $hardcodedModels]);
        return $hardcodedModels;
    }
    
    public function getModelsWithSource(): array {
        $models = $this->getModels();
        
        // Check if these are cached API models
        try {
            $cache = \ZeroAI\Core\CacheManager::getInstance();
            $cachedModels = $cache->get('claude_api_models');
            if ($cachedModels !== null && !empty($cachedModels) && $models === $cachedModels) {
                return ['models' => $models, 'source' => 'API', 'color' => 'green'];
            }
        } catch (\Exception $e) {
            // Continue
        }
        
        // Must be hardcoded models
        return ['models' => $models, 'source' => 'Hardcoded', 'color' => 'red'];
    }
    
    private function fetchRealModels(): array {
        $logger = \ZeroAI\Core\Logger::getInstance();
        
        try {
            $logger->logClaude('API key check', ['has_key' => !empty($this->apiKey), 'key_length' => strlen($this->apiKey)]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.anthropic.com/v1/models',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'x-api-key: ' . $this->apiKey,
                    'anthropic-version: 2023-06-01'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $logger->logClaude('API call details', ['http_code' => $httpCode, 'curl_error' => $curlError, 'response_length' => strlen($response)]);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $logger->logClaude('API response parsed', ['data_structure' => isset($data['data']), 'data_count' => isset($data['data']) ? count($data['data']) : 0]);
                
                if (isset($data['data']) && is_array($data['data'])) {
                    $models = [];
                    foreach ($data['data'] as $model) {
                        if (isset($model['id'])) {
                            $models[] = $model['id'];
                        }
                    }
                    $logger->logClaude('API models extracted', ['models' => $models]);
                    return $models;
                }
            } else {
                $logger->logClaude('API call failed', ['http_code' => $httpCode, 'response' => substr($response, 0, 500)]);
            }
        } catch (\Exception $e) {
            $logger->logClaude('API call exception', ['error' => $e->getMessage()]);
        }
        
        return [];
    }
    
    public function validateApiKey(): bool {
        try {
            $response = $this->chatWithClaude('test', 'Test system', 'claude-3-5-haiku-20241022');
            return isset($response['message']);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function calculateCost(array $usage): float {
        $model = $usage['model'] ?? 'claude-3-5-sonnet-20241022';
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        
        $modelPricing = $this->models[$model] ?? ['input' => 3.00, 'output' => 15.00];
        
        $inputCost = ($inputTokens / 1000000) * $modelPricing['input'];
        $outputCost = ($outputTokens / 1000000) * $modelPricing['output'];
        
        return $inputCost + $outputCost;
    }
    
    public function chatWithClaude($message, $systemPrompt, $model, $conversationHistory = []) {
        // Disable background commands temporarily to prevent SQL injection errors
        // TODO: Fix command parsing to prevent SQL injection
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
        
        // Format Claude's response with proper code blocks
        $claudeResponse = $this->formatResponse($claudeResponse);
        
        // Process Claude's commands and show results
        $claudeToolResults = $this->processTools($claudeResponse);
        if ($claudeToolResults) {
            $claudeResponse .= "\n\n" . $claudeToolResults;
        }
        
        // Track token usage by model
        $this->trackTokenUsage($model, $decoded['usage'] ?? []);
        
        return [
            'message' => $claudeResponse,
            'usage' => $decoded['usage'] ?? [],
            'model' => $decoded['model'] ?? $model
        ];
    }
    
    private function formatResponse($response) {
        // Format code blocks
//        $response = preg_replace('/```([a-zA-Z]*\n[^`]+)```/', '<pre><code>$1</code></pre>', $response);
        
        // Format tool results sections
        $response = preg_replace('/Tool Results:\n([^\n]+(?:\n[^\n]+)*)/m', '<div class="tool-results"><strong>🔧 Tool Results:</strong><br>$1</div>', $response);
        
        // Format file paths
        $response = preg_replace('/📄 File: ([^\n]+)/', '<strong>📄 File:</strong> <code>$1</code>', $response);
        
        // Format directory listings
        $response = preg_replace('/📁 Directory: ([^\n]+)/', '<strong>📁 Directory:</strong> <code>$1</code>', $response);
        
        return $response;
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
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Use existing token tracking system instead of creating duplicate table
            // This integrates with your existing /app/data/zeroai.db system
            
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;
            $totalTokens = $inputTokens + $outputTokens;
            
            // Calculate cost based on model pricing (per 1M tokens)
            $cost = $this->calculateCost(['model' => $model, 'input_tokens' => $inputTokens, 'output_tokens' => $outputTokens]);
            
            // Use existing Python token tracker instead
            $command = "cd /app && /app/venv/bin/python3 -c \"from src.database.token_tracking import tracker; tracker.log_usage('claude', '$model', $inputTokens, $outputTokens, '$model', $cost)\" 2>/dev/null";
            shell_exec($command);
            
        } catch (\Exception $e) {
            error_log("Failed to track token usage: " . $e->getMessage());
        }
    }
    

    
    private function getApiKey() {
        if (file_exists('/app/.env')) {
            $envContent = file_get_contents('/app/.env');
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches)) {
                return trim($matches[1]);
            }
        }
        return getenv('ANTHROPIC_API_KEY');
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


