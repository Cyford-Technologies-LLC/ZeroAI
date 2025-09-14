<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Kill any running Python crew processes
    $killCmd = "pkill -f 'run_dev_ops.py' 2>/dev/null";
    shell_exec($killCmd);
    
    // Also kill any hanging Python processes
    $killPythonCmd = "pkill -f 'python.*run/internal' 2>/dev/null";
    shell_exec($killPythonCmd);
    
    echo json_encode([
        'success' => true,
        'message' => 'All crew processes stopped successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to stop processes: ' . $e->getMessage()
    ]);
}
?>