<?php
namespace ZeroAI\API;

use PDO;
use Exception;

class ClaudeMemoryAPI {
    public function handleRequest() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        try {
            $dbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
            
            if (!file_exists($dbPath)) {
                echo json_encode(['success' => false, 'error' => 'Database not found']);
                exit;
            }
            
            $pdo = new PDO("sqlite:$dbPath");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                SELECT command, output, status, model_used, timestamp 
                FROM command_history 
                WHERE datetime(timestamp) >= datetime('now', '-3 minutes') 
                ORDER BY timestamp DESC
            ");
            
            $stmt->execute();
            $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'commands' => $commands,
                'count' => count($commands),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'error' => $e->getMessage()
            ]);
        }
    }
}