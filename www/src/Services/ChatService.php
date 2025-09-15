<?php

namespace ZeroAI\Services;

use ZeroAI\Providers\AI\Claude\ClaudeProvider;
use ZeroAI\Core\System;

class ChatService {
    private $system;
    
    public function __construct() {
        $this->system = System::getInstance();
    }
    
    public function processChat($message, $provider = 'claude', $model = 'claude-sonnet-4-20250514', $autonomous = true, $history = []) {
        switch ($provider) {
            case 'claude':
                return $this->processClaude($message, $model, $autonomous, $history);
            case 'local':
                return $this->processLocal($message, $model, $autonomous, $history);
            default:
                throw new \Exception("Unsupported provider: $provider");
        }
    }
    
    private function processClaude($message, $model, $autonomous, $history) {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('Anthropic API key not configured');
        }
        
        $claude = new ClaudeProvider($apiKey);
        return $claude->chat($message, $model, $autonomous, $history);
    }
    
    private function processLocal($message, $model, $autonomous, $history) {
        require_once __DIR__ . '/../Providers/AI/Local/LocalProvider.php';
        require_once __DIR__ . '/../Providers/AI/Local/LocalCommands.php';
        
        $local = new \ZeroAI\Providers\AI\Local\LocalProvider();
        return $local->chat($message, $model, $autonomous, $history);
    }
}


