<?php

namespace ZeroAI\AI;

class LocalAgent {
    protected $name;
    protected $role;
    protected $goal;
    protected $backstory;
    protected $tools = [];
    protected $model;
    protected $baseUrl;
    
    public function __construct($config = []) {
        $this->name = $config['name'] ?? 'Local Agent';
        $this->role = $config['role'] ?? 'Assistant';
        $this->goal = $config['goal'] ?? 'Help with tasks';
        $this->backstory = $config['backstory'] ?? 'A helpful AI assistant';
        $this->model = $config['model'] ?? 'llama3.2:latest';
        $this->baseUrl = $config['base_url'] ?? 'http://localhost:11434';
    }
    
    public function chat($message, $systemPrompt = null) {
        $url = $this->baseUrl . '/api/generate';
        
        $prompt = $systemPrompt ? $systemPrompt . "\n\n" . $message : $message;
        
        $data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Local agent error (HTTP {$httpCode}): " . $response);
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['response'])) {
            throw new \Exception('Invalid response from local agent');
        }
        
        return [
            'message' => $result['response'],
            'model' => $this->model,
            'agent' => $this->name
        ];
    }
    
    public function testConnection() {
        try {
            $response = $this->chat("Hello, please respond with 'Local agent is connected' to test.");
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
        try {
            $url = $this->baseUrl . '/api/tags';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if (isset($result['models'])) {
                return array_column($result['models'], 'name');
            }
            
            return [$this->model];
        } catch (\Exception $e) {
            return [$this->model];
        }
    }
    
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    public function setBaseUrl($url) {
        $this->baseUrl = $url;
        return $this;
    }
    
    public function addTool($tool) {
        $this->tools[] = $tool;
        return $this;
    }
    
    public function getConfig() {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'goal' => $this->goal,
            'backstory' => $this->backstory,
            'model' => $this->model,
            'base_url' => $this->baseUrl,
            'tools' => $this->tools
        ];
    }
}
