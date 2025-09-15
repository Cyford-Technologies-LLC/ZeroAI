<?php
namespace ZeroAI\Core;

use ZeroAI\API\{ChatAPI, ClaudeContextAPI, ClaudeMemoryAPI, ChatHistoryAPI, MemoryDataAPI};

class AdminAPI {
    public function handle($endpoint) {
        header('Content-Type: application/json');
        
        switch ($endpoint) {
            // Chat & Claude APIs
            case 'chat':
                $api = new ChatAPI();
                return $api->handleRequest();
                
            case 'claude-context':
                $api = new ClaudeContextAPI();
                return $api->handleRequest();
                
            case 'claude-memory':
                $api = new ClaudeMemoryAPI();
                return $api->handleRequest();
                
            case 'chat-history':
                $api = new ChatHistoryAPI();
                return $api->handleRequest();
                
            case 'memory-data':
                $api = new MemoryDataAPI();
                return $api->handleRequest();
                
            case 'memory-init':
                echo json_encode(ClaudeMemoryInit::initialize());
                break;
                
            // System APIs
            case 'system-stats':
                echo json_encode($this->getSystemStats());
                break;
                
            case 'token-usage':
                echo json_encode($this->getTokenUsage());
                break;
                
            case 'peers':
                $peerManager = PeerManager::getInstance();
                echo json_encode(['success' => true, 'peers' => $peerManager->getPeers()]);
                break;
                
            case 'peer-discovery':
                $peerManager = PeerManager::getInstance();
                $result = $peerManager->runPeerDiscovery();
                echo json_encode(['success' => $result]);
                break;
                
            // User Management
            case 'users':
                echo json_encode($this->handleUsers());
                break;
                
            case 'login':
                echo json_encode($this->handleLogin());
                break;
                
            // Agent Management
            case 'agents':
                echo json_encode($this->handleAgents());
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Admin endpoint not found']);
        }
    }
    
    private function getSystemStats() {
        try {
            return [
                'success' => true,
                'stats' => [
                    'cpu_usage' => sys_getloadavg()[0] ?? 0,
                    'memory_usage' => memory_get_usage(true),
                    'disk_usage' => disk_total_space('.') - disk_free_space('.')
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function getTokenUsage() {
        try {
            $db = DatabaseManager::getInstance();
            $result = $db->executeSQL("SELECT SUM(input_tokens) as input, SUM(output_tokens) as output, SUM(cost) as cost FROM claude_token_usage WHERE DATE(timestamp) = DATE('now')");
            
            return [
                'success' => true,
                'stats' => [
                    'hour' => ['total_tokens' => 0, 'total_cost' => 0, 'total_requests' => 0, 'models' => []],
                    'day' => ['total_tokens' => $result[0]['input'] + $result[0]['output'], 'total_cost' => $result[0]['cost'], 'total_requests' => 1, 'models' => []],
                    'week' => ['total_tokens' => 0, 'total_cost' => 0, 'total_requests' => 0, 'models' => []],
                    'total' => ['total_tokens' => 0, 'total_cost' => 0, 'total_requests' => 0, 'models' => []]
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function handleUsers() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $users = $db->select('users');
                return ['success' => true, 'users' => $users];
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('users', $data);
                return ['success' => $result, 'message' => $result ? 'User created' : 'Failed to create user'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
    
    private function handleLogin() {
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if ($username === 'admin' && $password === 'admin') {
            session_start();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            return ['success' => true, 'message' => 'Login successful'];
        }
        
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    private function handleAgents() {
        $method = $_SERVER['REQUEST_METHOD'];
        $db = DatabaseManager::getInstance();
        
        switch ($method) {
            case 'GET':
                $id = $_GET['id'] ?? null;
                if ($id) {
                    $agent = $db->select('agents', ['id' => $id]);
                    return ['success' => true, 'agent' => $agent ? $agent[0] : null];
                } else {
                    $agents = $db->select('agents');
                    return ['success' => true, 'agents' => $agents];
                }
                
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $db->insert('agents', $data);
                return ['success' => $result, 'message' => $result ? 'Agent created' : 'Failed to create agent'];
                
            default:
                return ['success' => false, 'error' => 'Method not allowed'];
        }
    }
}
