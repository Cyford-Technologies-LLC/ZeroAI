<?php
namespace ZeroAI\Core;

use ZeroAI\API\{ChatAPI, ClaudeContextAPI, ClaudeMemoryAPI, ChatHistoryAPI, MemoryDataAPI};

class APIRouter {
    public function route($path) {
        switch ($path) {
            case '/chat_v2':
                $api = new ChatAPI();
                return $api->handleRequest();
                
            case '/claude_context_api':
                $api = new ClaudeContextAPI();
                return $api->handleRequest();
                
            case '/claude_memory_api':
                $api = new ClaudeMemoryAPI();
                return $api->handleRequest();
                
            case '/save_chat_history':
                $api = new ChatHistoryAPI();
                return $api->handleRequest();
                
            case '/get_memory_data':
                $api = new MemoryDataAPI();
                return $api->handleRequest();
                
            case '/claude_memory_init':
                header('Content-Type: application/json');
                echo json_encode(ClaudeMemoryInit::initialize());
                break;
                
            case '/check_command_permission':
                // Legacy function support
                require_once __DIR__ . '/CommandPermission.php';
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found']);
        }
    }
}
