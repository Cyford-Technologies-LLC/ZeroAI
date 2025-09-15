<?php

namespace ZeroAI\AI;

class Claude extends CloudAI {
    protected $baseUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($apiKey = null, $model = 'claude-3-5-sonnet-20241022') {
        parent::__construct($apiKey ?: getenv('ANTHROPIC_API_KEY'), $model);
    }
    
    public function setModel($model) {
        $this->model = $model;
    }
    
    public function chat($message, $systemPrompt = null, $conversationHistory = []) {
        if (!$this->apiKey) {
            throw new \Exception('Anthropic API key not configured');
        }
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        $messages = $this->buildMessages($message, $conversationHistory);
        
        $data = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages
        ];
        
        if ($systemPrompt) {
            $data['system'] = $systemPrompt;
        }
        
        $result = $this->makeRequest($this->baseUrl, $data, $headers);
        
        if (!isset($result['content'][0]['text'])) {
            throw new \Exception('Invalid response from Claude API');
        }
        
        return [
            'message' => $result['content'][0]['text'],
            'usage' => $result['usage'] ?? [],
            'model' => $result['model'] ?? $this->model
        ];
    }
    
    public function testConnection() {
        try {
            $response = $this->chat("Hello, please respond with 'Claude is connected to ZeroAI' to test the integration.");
            return [
                'success' => true,
                'message' => $response['message'],
                'model' => $response['model']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getAvailableModels() {
        return [
            'claude-3-5-sonnet-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307'
        ];
    }
    
    private function buildMessages($message, $conversationHistory) {
        $messages = [];
        
        // Add conversation history (limit to last 10 messages)
        $recentHistory = array_slice($conversationHistory, -10);
        foreach ($recentHistory as $historyItem) {
            if (isset($historyItem['sender']) && isset($historyItem['message'])) {
                $role = ($historyItem['sender'] === 'Claude') ? 'assistant' : 'user';
                if ($historyItem['sender'] !== 'System') {
                    $messages[] = [
                        'role' => $role,
                        'content' => $historyItem['message']
                    ];
                }
            }
        }
        
        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        return $messages;
    }
    
    public function analyzeAgentPerformance($agentData) {
        $prompt = "Analyze this ZeroAI agent's performance data and provide optimization recommendations:\n\n" 
                . json_encode($agentData, JSON_PRETTY_PRINT) 
                . "\n\nPlease provide:\n1. Performance assessment\n2. Specific optimization recommendations\n3. Suggested role/goal improvements\n4. Tool recommendations\n5. Training suggestions";
        
        return $this->chat($prompt);
    }
    
    public function generateAgentCode($agentSpec) {
        $prompt = "Generate Python code for a ZeroAI CrewAI agent based on this specification:\n\n" 
                . json_encode($agentSpec, JSON_PRETTY_PRINT) 
                . "\n\nPlease provide:\n1. Complete Python agent class\n2. Required imports\n3. Tool integrations\n4. Error handling\n5. Documentation\n\nFollow ZeroAI patterns and CrewAI best practices.";
        
        return $this->chat($prompt);
    }
    
    public function saveScratchPad($content) {
        $db = \ZeroAI\Core\DatabaseManager::getInstance();
        
        // Create table if not exists
        $db->query("CREATE TABLE IF NOT EXISTS claude_scratch_pad (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert or update scratch pad content
        $existing = $db->query("SELECT id FROM claude_scratch_pad LIMIT 1");
        if ($existing && count($existing) > 0) {
            $db->query("UPDATE claude_scratch_pad SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1", [$content]);
        } else {
            $db->query("INSERT INTO claude_scratch_pad (content) VALUES (?)", [$content]);
        }
        
        return true;
    }
    
    public function getScratchPad() {
        $db = \ZeroAI\Core\DatabaseManager::getInstance();
        
        // Create table if not exists
        $db->query("CREATE TABLE IF NOT EXISTS claude_scratch_pad (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $result = $db->query("SELECT content FROM claude_scratch_pad ORDER BY updated_at DESC LIMIT 1");
        
        return ($result && count($result) > 0) ? $result[0]['content'] : '';
    }
}