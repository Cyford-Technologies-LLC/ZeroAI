<?php
namespace ZeroAI\API;

use PDO;
use Exception;

class ClaudeContextAPI {
    private $memoryPdo;
    private $sessionId;
    
    public function handleRequest() {
        date_default_timezone_set('America/New_York');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $commands = $input['commands'] ?? [];
        $mode = $input['mode'] ?? 'hybrid';
        $selectedModel = $input['model'] ?? 'claude-sonnet-4-20250514';
        
        if (empty($commands)) {
            echo json_encode(['success' => false, 'error' => 'Commands required']);
            exit;
        }
        
        $this->initializeMemory($selectedModel, $mode);
        $results = $this->processCommands($commands);
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function initializeMemory($selectedModel, $mode) {
        $GLOBALS['claudeMode'] = $mode;
        $GLOBALS['executedCommands'] = [];
        
        try {
            $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
            if (!is_dir($memoryDir)) {
                mkdir($memoryDir, 0777, true);
            }
            
            $dbPath = $memoryDir . '/claude_memory.db';
            $this->memoryPdo = new PDO("sqlite:$dbPath");
            
            $now = date('Y-m-d H:i:s');
            $this->memoryPdo->prepare("INSERT INTO claude_sessions (model_used, mode, start_time) VALUES (?, ?, ?)")
                           ->execute([$selectedModel, $mode, $now]);
            $this->sessionId = $this->memoryPdo->lastInsertId();
            
            $GLOBALS['memoryPdo'] = $this->memoryPdo;
            $GLOBALS['sessionId'] = $this->sessionId;
        } catch (Exception $e) {
            $GLOBALS['memoryPdo'] = null;
            $GLOBALS['sessionId'] = null;
        }
    }
    
    private function processCommands($commands) {
        require_once __DIR__ . '/../file_commands.php';
        require_once __DIR__ . '/../claude_commands.php';
        
        $results = [];
        foreach ($commands as $command) {
            $message = $command;
            $originalLength = strlen($message);
            
            processFileCommands($message);
            processClaudeCommands($message);
            
            $output = '';
            if (strlen($message) > $originalLength) {
                $output = substr($message, $originalLength);
            }
            
            $results[] = [
                'command' => $command,
                'output' => $output,
                'executed' => true
            ];
        }
        
        return $results;
    }
}


