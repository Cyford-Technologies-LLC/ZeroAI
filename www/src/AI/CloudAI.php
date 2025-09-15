<?php

namespace ZeroAI\AI;

abstract class CloudAI {
    protected $apiKey;
    protected $baseUrl;
    protected $model;
    protected $maxTokens = 4000;
    protected $timeout = 300;
    
    public function __construct($apiKey = null, $model = null) {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }
    
    abstract public function chat($message, $systemPrompt = null, $conversationHistory = []);
    abstract public function testConnection();
    abstract public function getAvailableModels();
    
    protected function makeRequest($url, $data, $headers) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \Exception("Connection error: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("API error (HTTP {$httpCode}): " . $response);
        }
        
        return json_decode($response, true);
    }
    
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    public function setMaxTokens($tokens) {
        $this->maxTokens = $tokens;
        return $this;
    }
    
    public function setTimeout($seconds) {
        $this->timeout = $seconds;
        return $this;
    }
}
