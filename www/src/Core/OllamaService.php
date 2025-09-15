<?php
namespace ZeroAI\Core;

class OllamaService {
    private $baseUrl;
    
    public function __construct() {
        $this->baseUrl = 'http://ollama:11434';
    }
    
    public function isAvailable() {
        try {
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($this->baseUrl . '/api/tags', false, $context);
            return $response !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function getModels() {
        try {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $response = @file_get_contents($this->baseUrl . '/api/tags', false, $context);
            if ($response === false) return [];
            
            $data = json_decode($response, true);
            return $data['models'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getStatus() {
        $available = $this->isAvailable();
        $models = $available ? $this->getModels() : [];
        
        return [
            'available' => $available,
            'url' => $this->baseUrl,
            'models' => $models,
            'model_count' => count($models),
            'total_size' => array_sum(array_column($models, 'size')),
            'last_checked' => date('Y-m-d H:i:s')
        ];
    }
}


