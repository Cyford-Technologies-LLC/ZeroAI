<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

try {
    $stats = [
        'cpu_usage' => sys_getloadavg()[0] ?? 0,
        'memory_usage' => memory_get_usage(true),
        'disk_usage' => disk_free_space('/'),
        'agents_count' => 0,
        'crews_running' => 0
    ];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
