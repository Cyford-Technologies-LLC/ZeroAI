<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    public function chatWithClaude($message, $systemPrompt, $model, $conversationHistory = []) {
        // Simple debug - write history count to a file
        file_put_contents('/app/claude_memory_debug.txt', "History count: " . count($conversationHistory) . "\n" . json_encode($conversationHistory, JSON_PRETTY_PRINT), LOCK_EX);
        
        $messages = [];
        
        // Process conversation history - limit to last 20 messages
        if (is_array($conversationHistory) && !empty($conversationHistory)) {
            $recentHistory = array_slice($conversationHistory, -20);
            
            foreach ($recentHistory as $historyItem) {
                if (!is_array($historyItem) || !isset($historyItem['sender']) || !isset($historyItem['message'])) {
                    continue;
                }
                
                $sender = trim($historyItem['sender']);
                $messageContent = trim($historyItem['message']);
                
                if (empty($messageContent)) {
                    continue;
                }
                
                // Map sender names to Claude API roles
                if (in_array($sender, ['Claude', 'claude', 'Assistant', 'assistant', 'Claude (Auto)', 'AI'], true)) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $messageContent
                    ];
                } elseif (!in_array($sender, ['System', 'system', 'Error', 'error'], true)) {
                    $messages[] = [
                        'role' => 'user',
                        'content' => $messageContent
                    ];
                }
            }
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        $data = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
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
        
        return [
            'message' => $decoded['content'][0]['text'],
            'usage' => $decoded['usage'] ?? [],
            'model' => $decoded['model'] ?? $model
        ];
    }
}