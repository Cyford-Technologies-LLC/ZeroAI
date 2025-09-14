<?php

namespace ZeroAI\Services;

use ZeroAI\Models\Agent;
use ZeroAI\Core\DatabaseManager;

class ZeroAIChatService {
    private $db;
    private $agent;
    
    public function __construct() {
        $this->db = new DatabaseManager();
        $this->agent = new Agent();
        $this->initChatTables();
    }
    
    private function initChatTables() {
        $this->db->executeSQL("
            CREATE TABLE IF NOT EXISTS chat_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_id INTEGER,
                user_id TEXT,
                session_name TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (agent_id) REFERENCES agents(id)
            );
            
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER,
                sender TEXT,
                message TEXT,
                response TEXT,
                tokens_used INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES chat_sessions(id)
            );
        ");
    }
    
    public function startChatSession($agentId, $userId, $sessionName = null) {
        $sessionName = $sessionName ?: 'Chat with Agent ' . $agentId;
        
        $result = $this->db->executeSQL(
            "INSERT INTO chat_sessions (agent_id, user_id, session_name) VALUES (?, ?, ?)",
            [$agentId, $userId, $sessionName]
        );
        
        return $result[0]['lastInsertId'];
    }
    
    public function sendMessage($sessionId, $message) {
        $session = $this->getSession($sessionId);
        if (!$session) {
            throw new \Exception("Chat session not found");
        }
        
        $result = $this->db->executeSQL(
            "INSERT INTO chat_messages (session_id, sender, message) VALUES (?, 'user', ?)",
            [$sessionId, $message]
        );
        $messageId = $result[0]['lastInsertId'];
        
        $history = $this->getChatHistory($sessionId, 10);
        $response = $this->executeAgentChat($session, $message, $history);
        
        $this->db->executeSQL(
            "UPDATE chat_messages SET response = ?, tokens_used = ? WHERE id = ?",
            [$response['message'], $response['tokens'], $messageId]
        );
        
        return [
            'message_id' => $messageId,
            'response' => $response['message'],
            'tokens_used' => $response['tokens'],
            'agent_name' => $session['name']
        ];
    }
    
    private function executeAgentChat($session, $message, $history) {
        if ($session['name'] === 'Claude AI Assistant') {
            return $this->executeClaude($session, $message, $history);
        }
        
        return $this->executeLocalAgent($session, $message, $history);
    }
    
    private function executeClaude($session, $message, $history) {
        require_once __DIR__ . '/../Providers/AI/Claude/ClaudeProvider.php';
        
        try {
            $chatService = new ChatService();
            $response = $chatService->processChat($message, 'claude');
            
            return [
                'message' => $response['response'],
                'tokens' => $response['tokens'],
                'success' => true
            ];
        } catch (\Exception $e) {
            return [
                'message' => "Sorry, I encountered an error: " . $e->getMessage(),
                'tokens' => 0,
                'success' => false
            ];
        }
    }
    
    private function executeLocalAgent($session, $message, $history) {
        $historyStr = $this->formatHistoryForAgent($history);
        
        $chatScript = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from crewai import Agent
    import json
    
    agent = Agent(
        role='{$session['role']}',
        goal='{$session['goal']}',
        backstory='{$session['backstory']}',
        memory=True,
        verbose=False,
        allow_delegation=False
    )
    
    context = '''
Previous conversation:
{$historyStr}

Current message: {$message}

Please respond as {$session['name']} ({$session['role']}). 
Keep responses conversational and helpful.
'''
    
    response = agent.execute_task(context)
    tokens = len(context.split()) + len(str(response).split())
    
    result = {
        'message': str(response),
        'tokens': tokens,
        'success': True
    }
    
    print('CHAT_RESULT_START')
    print(json.dumps(result))
    print('CHAT_RESULT_END')
    
except Exception as e:
    error_result = {
        'message': f'Sorry, I encountered an error: {str(e)}',
        'tokens': 0,
        'success': False
    }
    
    print('CHAT_RESULT_START')
    print(json.dumps(error_result))
    print('CHAT_RESULT_END')
";
        
        $tempFile = '/tmp/agent_chat_' . $session['id'] . '.py';
        file_put_contents($tempFile, $chatScript);
        
        $output = shell_exec("/app/venv/bin/python {$tempFile} 2>&1");
        unlink($tempFile);
        
        $startPos = strpos($output, 'CHAT_RESULT_START');
        $endPos = strpos($output, 'CHAT_RESULT_END');
        
        if ($startPos !== false && $endPos !== false) {
            $jsonStr = substr($output, $startPos + 18, $endPos - $startPos - 18);
            $result = json_decode(trim($jsonStr), true);
            
            if ($result) {
                return $result;
            }
        }
        
        return [
            'message' => "I'm having trouble responding right now. Please try again.",
            'tokens' => 0,
            'success' => false
        ];
    }
    
    private function formatHistoryForAgent($history) {
        $formatted = [];
        foreach ($history as $msg) {
            if ($msg['sender'] === 'user') {
                $formatted[] = "User: " . $msg['message'];
                if ($msg['response']) {
                    $formatted[] = "Agent: " . $msg['response'];
                }
            }
        }
        return implode("\n", array_slice($formatted, -10));
    }
    
    public function getChatHistory($sessionId, $limit = 50) {
        $result = $this->db->executeSQL(
            "SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at DESC LIMIT ?",
            [$sessionId, $limit]
        );
        return array_reverse($result[0]['data'] ?? []);
    }
    
    public function getUserSessions($userId) {
        $result = $this->db->executeSQL("
            SELECT cs.*, a.name as agent_name, a.role as agent_role,
                   COUNT(cm.id) as message_count,
                   MAX(cm.created_at) as last_message
            FROM chat_sessions cs
            JOIN agents a ON cs.agent_id = a.id
            LEFT JOIN chat_messages cm ON cs.id = cm.session_id
            WHERE cs.user_id = ?
            GROUP BY cs.id
            ORDER BY cs.created_at DESC
        ", [$userId]);
        
        return $result[0]['data'] ?? [];
    }
    
    public function getAvailableAgents() {
        return $this->agent->getAll();
    }
    
    public function getSession($sessionId) {
        $result = $this->db->executeSQL("
            SELECT cs.*, a.name as agent_name, a.role as agent_role
            FROM chat_sessions cs
            JOIN agents a ON cs.agent_id = a.id
            WHERE cs.id = ?
        ", [$sessionId]);
        
        return $result[0]['data'][0] ?? null;
    }
}