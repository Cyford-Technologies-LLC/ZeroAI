<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

try {
    $db = new \ZeroAI\Core\DatabaseManager();
    
    // Get token usage stats for different time periods
    $stats = [
        'hour' => getTokenStats($db, '1 HOUR'),
        'day' => getTokenStats($db, '1 DAY'), 
        'week' => getTokenStats($db, '7 DAY'),
        'total' => getTokenStats($db, null)
    ];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getTokenStats($db, $period) {
    $whereClause = $period ? "WHERE timestamp >= datetime('now', '-$period')" : "";
    
    // Try to get data from Python token tracker first
    $pythonResult = shell_exec("cd /app && /app/venv/bin/python3 -c \"from src.database.token_tracking import tracker; print(tracker.get_usage_stats('$period'))\" 2>/dev/null");
    
    if ($pythonResult) {
        $result = [['data' => json_decode($pythonResult, true) ?: []]];
    } else {
        // Fallback to direct database query
        $result = $db->executeSQL("SELECT model, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(total_tokens) as total_tokens, SUM(cost) as cost_usd, COUNT(*) as requests FROM claude_token_usage $whereClause GROUP BY model ORDER BY total_tokens DESC", 'main');
    }
    
    $models = $result[0]['data'] ?? [];
    
    // Calculate totals
    $totalTokens = 0;
    $totalCost = 0;
    $totalRequests = 0;
    
    foreach ($models as $model) {
        $totalTokens += $model['total_tokens'];
        $totalCost += $model['cost_usd'];
        $totalRequests += $model['requests'];
    }
    
    return [
        'models' => $models,
        'total_tokens' => $totalTokens,
        'total_cost' => $totalCost,
        'total_requests' => $totalRequests
    ];
}
?>