<?php
class AgentChat {
    private $db;
    private $pythonPath = '/app/venv/bin/python';
    
    public function __construct() {
        $this->db = new PDO("sqlite:/app/data/agents.db");
        $this->initChatTables();
    }
    
    private function initChatTables() {
        $this->db->exec("
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
        
        $stmt = $this->db->prepare("
            INSERT INTO chat_sessions (agent_id, user_id, session_name) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$agentId, $userId, $sessionName]);
        
        return $this->db->lastInsertId();
    }
    
    public function sendMessage($sessionId, $message) {
        // Get session and agent info
        $stmt = $this->db->prepare("
            SELECT cs.*, a.name, a.role, a.goal, a.backstory, a.config 
            FROM chat_sessions cs 
            JOIN agents a ON cs.agent_id = a.id 
            WHERE cs.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            throw new Exception("Chat session not found");
        }
        
        // Save user message
        $stmt = $this->db->prepare("
            INSERT INTO chat_messages (session_id, sender, message) 
            VALUES (?, 'user', ?)
        ");
        $stmt->execute([$sessionId, $message]);
        $messageId = $this->db->lastInsertId();
        
        // Get chat history for context
        $history = $this->getChatHistory($sessionId, 10);
        
        // Execute agent response
        $response = $this->executeAgentChat($session, $message, $history);
        
        // Update message with response
        $stmt = $this->db->prepare("
            UPDATE chat_messages 
            SET response = ?, tokens_used = ? 
            WHERE id = ?
        ");
        $stmt->execute([$response['message'], $response['tokens'], $messageId]);
        
        return [
            'message_id' => $messageId,
            'response' => $response['message'],
            'tokens_used' => $response['tokens'],
            'agent_name' => $session['name']
        ];
    }
    
    private function executeAgentChat($session, $message, $history) {
        // Check if this is Claude AI Assistant
        if ($session['name'] === 'Claude AI Assistant') {
            return $this->executeClaude($session, $message, $history);
        }
        
        // Regular CrewAI agent execution
        $config = json_decode($session['config'], true);
        $historyStr = $this->formatHistoryForAgent($history);
        
        $chatScript = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from crewai import Agent
    from src.zeroai import ZeroAI
    import json
    
    # Initialize agent
    agent = Agent(
        role='{$session['role']}',
        goal='{$session['goal']}',
        backstory='{$session['backstory']}',
        memory=True,
        verbose=False,
        allow_delegation=False
    )
    
    # Prepare context with chat history
    context = '''
Previous conversation:
{$historyStr}

Current message: {$message}

Please respond as {$session['name']} ({$session['role']}). 
Keep responses conversational and helpful.
'''
    
    # Get response from agent
    response = agent.execute_task(context)
    
    # Estimate token usage (rough calculation)
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
        
        $output = shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
        
        // Parse response
        $startPos = strpos($output, 'CHAT_RESULT_START');
        $endPos = strpos($output, 'CHAT_RESULT_END');
        
        if ($startPos !== false && $endPos !== false) {
            $jsonStr = substr($output, $startPos + 18, $endPos - $startPos - 18);
            $result = json_decode(trim($jsonStr), true);
            
            if ($result) {
                return $result;
            }
        }
        
        // Fallback response
        return [
            'message' => "I'm having trouble responding right now. Please try again.",
            'tokens' => 0,
            'success' => false
        ];
    }
    
    private function executeClaude($session, $message, $history) {
        require_once __DIR__ . '/claude_integration.php';
        
        try {
            $claude = new ClaudeIntegration();
            
            // Build system prompt with agent configuration
            $systemPrompt = "You are Claude, integrated into the ZeroAI system.

Your Role: {$session['role']}
Your Goal: {$session['goal']}
Your Background: {$session['backstory']}

ZeroAI Context:
- ZeroAI is a zero-cost AI workforce platform that runs entirely on user's hardware
- It uses local Ollama models and CrewAI for agent orchestration
- You can access project files using @file, @list, @search commands
- You help with code review, system optimization, and development guidance
- The user is managing their AI workforce through the admin portal

Your capabilities in ZeroAI:
- Code review and architectural guidance
- Agent performance optimization
- Development workflow improvements
- Strategic technical recommendations

Respond as Claude with your configured personality and expertise. Be helpful, insightful, and focus on practical solutions for ZeroAI optimization.";
            
            // Add conversation history to message if available
            $historyStr = $this->formatHistoryForAgent($history);
            if ($historyStr) {
                $message = "Previous conversation context:\n{$historyStr}\n\nUser's current message: {$message}";
            }
            
            $response = $claude->chatWithClaude($message, $systemPrompt);
            
            return [
                'message' => $response['message'],
                'tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
                'success' => true
            ];
            
        } catch (Exception $e) {
            return [
                'message' => "Sorry, I encountered an error: " . $e->getMessage(),
                'tokens' => 0,
                'success' => false
            ];
        }
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
        return implode("\n", array_slice($formatted, -10)); // Last 10 exchanges
    }
    
    public function getChatHistory($sessionId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM chat_messages 
            WHERE session_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function getUserSessions($userId) {
        $stmt = $this->db->prepare("
            SELECT cs.*, a.name as agent_name, a.role as agent_role,
                   COUNT(cm.id) as message_count,
                   MAX(cm.created_at) as last_message
            FROM chat_sessions cs
            JOIN agents a ON cs.agent_id = a.id
            LEFT JOIN chat_messages cm ON cs.id = cm.session_id
            WHERE cs.user_id = ?
            GROUP BY cs.id
            ORDER BY cs.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAvailableAgents() {
        $stmt = $this->db->query("
            SELECT id, name, role, goal, status 
            FROM agents 
            WHERE status = 'active' 
            ORDER BY is_core DESC, name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSession($sessionId) {
        $stmt = $this->db->prepare("
            SELECT cs.*, a.name as agent_name, a.role as agent_role
            FROM chat_sessions cs
            JOIN agents a ON cs.agent_id = a.id
            WHERE cs.id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>