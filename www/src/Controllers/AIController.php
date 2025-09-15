<?php

namespace ZeroAI\Controllers;

require_once __DIR__ . '/../AI/AIManager.php';

use ZeroAI\AI\AIManager;
use ZeroAI\AI\Claude;
use ZeroAI\AI\OpenAI;
use ZeroAI\AI\LocalAgent;

class AIController extends BaseController {
    private $aiManager;
    
    public function __construct() {
        parent::__construct();
        $this->aiManager = new AIManager();
    }
    
    public function chat() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonResponse(['error' => 'Method not allowed'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $provider = $input['provider'] ?? 'smart';
        
        if (!$message) {
            return $this->jsonResponse(['error' => 'Message required'], 400);
        }
        
        try {
            $response = $this->aiManager->chat($message, $provider);
            return $this->jsonResponse(['success' => true, 'data' => $response]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    public function providers() {
        $providers = $this->aiManager->getAvailableProviders();
        return $this->jsonResponse(['success' => true, 'data' => $providers]);
    }
    
    public function testProvider() {
        $input = json_decode(file_get_contents('php://input'), true);
        $provider = $input['provider'] ?? 'claude';
        
        $result = $this->aiManager->testProvider($provider);
        return $this->jsonResponse(['success' => true, 'data' => $result]);
    }
    
    public function addCloudProvider() {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $type = $input['type'] ?? '';
        $apiKey = $input['api_key'] ?? '';
        $model = $input['model'] ?? '';
        
        if (!$name || !$type || !$apiKey) {
            return $this->jsonResponse(['error' => 'Name, type, and API key required'], 400);
        }
        
        try {
            switch ($type) {
                case 'claude':
                    $provider = new Claude($apiKey, $model);
                    break;
                case 'openai':
                    $provider = new OpenAI($apiKey, $model);
                    break;
                default:
                    return $this->jsonResponse(['error' => 'Unsupported provider type'], 400);
            }
            
            $this->aiManager->addCloudProvider($name, $provider);
            return $this->jsonResponse(['success' => true, 'message' => 'Provider added successfully']);
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    public function addLocalAgent() {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $config = $input['config'] ?? [];
        
        if (!$name) {
            return $this->jsonResponse(['error' => 'Agent name required'], 400);
        }
        
        try {
            $agent = new LocalAgent($config);
            $this->aiManager->addLocalAgent($name, $agent);
            return $this->jsonResponse(['success' => true, 'message' => 'Local agent added successfully']);
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    public function getModels() {
        $input = json_decode(file_get_contents('php://input'), true);
        $provider = $input['provider'] ?? 'claude';
        
        $models = $this->aiManager->getProviderModels($provider);
        return $this->jsonResponse(['success' => true, 'data' => ['models' => $models]]);
    }
}
