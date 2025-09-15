<?php

namespace ZeroAI\AI;

class AIManager {
    private $cloudProviders = [];
    private $localAgents = [];
    private $defaultProvider = 'claude';
    
    public function __construct() {
        $this->initializeProviders();
    }
    
    private function initializeProviders() {
        // Initialize Claude if API key exists
        if (getenv('ANTHROPIC_API_KEY')) {
            $this->cloudProviders['claude'] = new Claude();
        }
        
        // Initialize local agents
        $this->localAgents['default'] = new LocalAgent([
            'name' => 'ZeroAI Assistant',
            'role' => 'AI Assistant',
            'goal' => 'Help with ZeroAI tasks and development'
        ]);
    }
    
    public function addCloudProvider($name, CloudAI $provider) {
        $this->cloudProviders[$name] = $provider;
        return $this;
    }
    
    public function addLocalAgent($name, LocalAgent $agent) {
        $this->localAgents[$name] = $agent;
        return $this;
    }
    
    public function chat($message, $provider = null, $systemPrompt = null, $conversationHistory = []) {
        $provider = $provider ?: $this->defaultProvider;
        
        // Try cloud provider first
        if (isset($this->cloudProviders[$provider])) {
            return $this->cloudProviders[$provider]->chat($message, $systemPrompt, $conversationHistory);
        }
        
        // Fallback to local agent
        if (isset($this->localAgents[$provider])) {
            return $this->localAgents[$provider]->chat($message, $systemPrompt);
        }
        
        // Use default local agent
        return $this->localAgents['default']->chat($message, $systemPrompt);
    }
    
    public function getAvailableProviders() {
        return [
            'cloud' => array_keys($this->cloudProviders),
            'local' => array_keys($this->localAgents)
        ];
    }
    
    public function testProvider($provider) {
        if (isset($this->cloudProviders[$provider])) {
            return $this->cloudProviders[$provider]->testConnection();
        }
        
        if (isset($this->localAgents[$provider])) {
            return $this->localAgents[$provider]->testConnection();
        }
        
        return ['success' => false, 'error' => 'Provider not found'];
    }
    
    public function getProviderModels($provider) {
        if (isset($this->cloudProviders[$provider])) {
            return $this->cloudProviders[$provider]->getAvailableModels();
        }
        
        if (isset($this->localAgents[$provider])) {
            return $this->localAgents[$provider]->getAvailableModels();
        }
        
        return [];
    }
    
    public function setDefaultProvider($provider) {
        $this->defaultProvider = $provider;
        return $this;
    }
    
    public function getCloudProvider($name) {
        return $this->cloudProviders[$name] ?? null;
    }
    
    public function getLocalAgent($name) {
        return $this->localAgents[$name] ?? null;
    }
    
    public function smartRoute($message, $systemPrompt = null, $conversationHistory = []) {
        // Simple routing logic - use cloud for complex tasks, local for simple ones
        $complexity = $this->assessComplexity($message);
        
        if ($complexity > 7 && !empty($this->cloudProviders)) {
            $provider = array_key_first($this->cloudProviders);
            return $this->chat($message, $provider, $systemPrompt, $conversationHistory);
        }
        
        // Use local agent for simple tasks
        $provider = array_key_first($this->localAgents);
        return $this->chat($message, $provider, $systemPrompt, $conversationHistory);
    }
    
    private function assessComplexity($message) {
        $complexityIndicators = [
            'analyze' => 2,
            'generate' => 3,
            'create' => 2,
            'optimize' => 3,
            'debug' => 2,
            'code' => 2,
            'algorithm' => 3,
            'architecture' => 3,
            'design' => 2,
            'complex' => 3,
            'advanced' => 3
        ];
        
        $score = 1;
        $words = str_word_count(strtolower($message), 1);
        
        foreach ($words as $word) {
            if (isset($complexityIndicators[$word])) {
                $score += $complexityIndicators[$word];
            }
        }
        
        // Length factor
        if (strlen($message) > 500) $score += 2;
        if (strlen($message) > 1000) $score += 3;
        
        return min($score, 10);
    }
}
