<?php
namespace ZeroAI\Core;

use PDO;
use Exception;

class CommandPermission {
    public static function checkCommandPermission($command, $mode) {
        try {
            $dbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
            $pdo = new PDO("sqlite:$dbPath");
            
            $stmt = $pdo->prepare("SELECT allowed FROM command_permissions WHERE mode = ? AND command = ?");
            $stmt->execute([$mode, $command]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("PERMISSION DB - Command: $command, Mode: $mode, Result: " . ($result ? $result['allowed'] : 'NOT_FOUND'));
            
            return $result ? (bool)$result['allowed'] : false;
        } catch (Exception $e) {
            error_log("PERMISSION ERROR: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getPermissionError($command, $mode) {
        return "[RESTRICTED] The @$command command is not allowed in " . strtoupper($mode) . " mode.";
    }
}