<?php
function checkCommandPermission($command, $mode) {
    try {
        $dbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
        $pdo = new PDO("sqlite:$dbPath");
        
        $stmt = $pdo->prepare("SELECT allowed FROM command_permissions WHERE mode = ? AND command = ?");
        $stmt->execute([$mode, $command]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result['allowed'] : false;
    } catch (Exception $e) {
        // If permission check fails, default to restricted
        return false;
    }
}

function getPermissionError($command, $mode) {
    return "[RESTRICTED] The @$command command is not allowed in " . strtoupper($mode) . " mode.";
}
?>