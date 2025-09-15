<?php

namespace ZeroAI\Providers\AI\Local;

class LocalProvider {
    private $ollamaUrl;
    private $commands;
    
    public function __construct($ollamaUrl = 'http://localhost:11434') {
        $this->ollamaUrl = $ollamaUrl;
        $this->commands = new LocalCommands();
    }
    
    public function chat($message, $model = 'llama3.2:1b', $autonomous = false, $history = []) {
        try {
            if ($autonomous) {
                $message = "[LOCAL MODE] You are running on local hardware with Ollama. " . $message;
            }
            
            $commandOutputs = $this->processCommands($message);
            
            $response = $this->ollamaChat($message . $commandOutputs, $model, $history);
            
            return [
                'success' => true,
                'response' => $response['message'],
                'tokens' => $response['tokens'] ?? 0,
                'cost' => 0.0,
                'model' => $model
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Local AI error: ' . $e->getMessage()];
        }
    }
    
    private function processCommands($message) {
        $originalMessage = $message;
        $this->commands->processLocalCommands($message);
        return strlen($message) > strlen($originalMessage) ? substr($message, strlen($originalMessage)) : '';
    }
    
    private function ollamaChat($message, $model, $history) {
        $data = [
            'model' => $model,
            'prompt' => $message,
            'stream' => false
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->ollamaUrl . '/api/generate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 300
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Ollama request failed with status $httpCode");
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded || !isset($decoded['response'])) {
            throw new \Exception('Invalid Ollama response format');
        }
        
        return [
            'message' => $decoded['response'],
            'tokens' => $decoded['eval_count'] ?? 0
        ];
    }
}


