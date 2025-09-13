<?php

namespace ZeroAI\AI;

class Claude extends CloudAI {
    protected $baseUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($apiKey = null, $model = 'claude-3-5-sonnet-20241022') {
        parent::__construct($apiKey ?: getenv('ANTHROPIC_API_KEY'), $model);
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
}