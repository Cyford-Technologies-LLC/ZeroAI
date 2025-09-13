<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    public function chatWithClaude($message, $systemPrompt, $model, $conversationHistory = []) {
        $messages = [];
        
        foreach ($conversationHistory as $msg) {
            if (isset($msg['sender']) && isset($msg['message']) && !empty(trim($msg['message']))) {
                $sender = $msg['sender'];
                if ($sender === 'Claude') {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => trim($msg['message'])
                    ];
                } elseif ($sender === 'You' || $sender === 'User') {
                    $messages[] = [
                        'role' => 'user',
                        'content' => trim($msg['message'])
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