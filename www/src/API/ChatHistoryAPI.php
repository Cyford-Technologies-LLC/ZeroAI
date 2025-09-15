<?php
namespace ZeroAI\API;

class ChatHistoryAPI {
    public function handleRequest() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $chatHistory = $input['history'] ?? [];
        
        $formattedHistory = "=== Claude Chat History - " . date('Y-m-d H:i:s') . " ===\n\n";
        foreach ($chatHistory as $entry) {
            $timestamp = isset($entry['timestamp']) ? date('Y-m-d H:i:s', strtotime($entry['timestamp'])) : 'Unknown';
            $formattedHistory .= "[{$timestamp}] {$entry['sender']}:\n{$entry['message']}\n\n";
        }
        $formattedHistory .= "=== End ===\n\n";
        
        $historyFile = '/app/knowledge/internal_crew/agent_learning/self/claude/chat_history.txt';
        if (!is_dir(dirname($historyFile))) {
            mkdir(dirname($historyFile), 0777, true);
        }
        
        file_put_contents($historyFile, $formattedHistory, FILE_APPEND);
        echo json_encode(['success' => true]);
    }
}


