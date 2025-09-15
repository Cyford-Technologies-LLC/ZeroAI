<?php

namespace ZeroAI\AI;

class OpenAI extends CloudAI {
    protected $baseUrl = 'https://api.openai.com/v1/chat/completions';
    
    public function __construct($apiKey = null, $model = 'gpt-4') {
        parent::__construct($apiKey ?: getenv('OPENAI_API_KEY'), $model);
    }
    
    public function chat($message, $systemPrompt = null, $conversationHistory = []) {
        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $messages = $this->buildMessages($message, $systemPrompt, $conversationHistory);
        
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens
        ];
        
        $result = $this->makeRequest($this->baseUrl, $data, $headers);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response from OpenAI API');
        }
        
        return [
            'message' => $result['choices'][0]['message']['content'],
            'usage' => $result['usage'] ?? [],
            'model' => $result['model'] ?? $this->model
        ];
    }
    
    public function testConnection() {
        try {
            $response = $this->chat("Hello, please respond with 'OpenAI is connected to ZeroAI' to test the integration.");
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
            'gpt-4',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-16k'
        ];
    }
    
    private function buildMessages($message, $systemPrompt, $conversationHistory) {
        $messages = [];
        
        if ($systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];
        }
        
        // Add conversation history
        $recentHistory = array_slice($conversationHistory, -10);
        foreach ($recentHistory as $historyItem) {
            if (isset($historyItem['sender']) && isset($historyItem['message'])) {
                $role = ($historyItem['sender'] === 'OpenAI') ? 'assistant' : 'user';
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
}


