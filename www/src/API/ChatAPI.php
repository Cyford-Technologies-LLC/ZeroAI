<?php

namespace ZeroAI\API;

require_once __DIR__ . '/../AI/CloudAI.php';
require_once __DIR__ . '/../AI/Claude.php';
require_once __DIR__ . '/../AI/LocalAgent.php';
require_once __DIR__ . '/../AI/AIManager.php';

use ZeroAI\AI\AIManager;

class ChatAPI {
    private $aiManager;
    
    public function __construct() {
        $this->aiManager = new AIManager();
    }
    
    public function handleRequest() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $provider = $input['provider'] ?? null;
        $model = $input['model'] ?? null;
        $autonomousMode = $input['autonomous'] ?? false;
        $conversationHistory = $input['history'] ?? [];
        
        if (!$message) {
            return $this->error('Message required');
        }
        
        try {
            if ($autonomousMode) {
                $message = $this->processAutonomousMode($message);
            }
            
            $message = $this->processCommands($message);
            $systemPrompt = $this->getSystemPrompt();
            
            if ($provider === 'smart') {
                $response = $this->aiManager->smartRoute($message, $systemPrompt, $conversationHistory);
            } else {
                $response = $this->aiManager->chat($message, $provider, $systemPrompt, $conversationHistory);
            }
            
            return $this->success($response);
            
        } catch (\Exception $e) {
            return $this->error('AI error: ' . $e->getMessage());
        }
    }
    
    private function processAutonomousMode($message) {
        $message = "[AUTONOMOUS MODE ENABLED] You have full access to analyze, create, edit, and optimize files proactively. " . $message;
        
        if (!preg_match('/\@(file|list|search|create|edit|append|delete)/', $message)) {
            $autoScan = "\n\nAuto-scanning key directories:\n";
            if (is_dir('/app/src')) {
                $srcFiles = shell_exec('find /app/src -name "*.py" | head -10');
                $autoScan .= "\nSrc files:\n" . ($srcFiles ?: "No Python files found");
            }
            $message .= $autoScan;
        }
        
        return $message;
    }
    
    private function processCommands($message) {
        require_once __DIR__ . '/../../api/file_commands.php';
        require_once __DIR__ . '/../../api/claude_commands.php';
        
        processFileCommands($message);
        processClaudeCommands($message);
        
        return $message;
    }
    
    private function getSystemPrompt() {
        require_once __DIR__ . '/../../api/sqlite_manager.php';
        
        $sql = "SELECT prompt FROM system_prompts WHERE id = 1 ORDER BY created_at DESC LIMIT 1";
        $result = \SQLiteManager::executeSQL($sql);
        
        if (!empty($result[0]['data'])) {
            return $result[0]['data'][0]['prompt'];
        }
        
        require_once __DIR__ . '/../../api/init_claude_prompt.php';
        $result = \SQLiteManager::executeSQL($sql);
        return $result[0]['data'][0]['prompt'];
    }
    
    public function getProviders() {
        header('Content-Type: application/json');
        return $this->success($this->aiManager->getAvailableProviders());
    }
    
    public function testProvider() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $provider = $input['provider'] ?? 'claude';
        
        $result = $this->aiManager->testProvider($provider);
        return $this->success($result);
    }
    
    private function success($data) {
        echo json_encode(['success' => true] + $data);
        exit;
    }
    
    private function error($message) {
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
