<?php
header('Content-Type: application/json');

try {
    $db = new SQLite3('/app/data/zeroai.db');
    
    $action = $_GET['action'] ?? 'summary';
    
    switch ($action) {
        case 'summary':
            $result = $db->query('
                SELECT 
                    provider,
                    model,
                    SUM(input_tokens) as total_input,
                    SUM(output_tokens) as total_output,
                    SUM(total_tokens) as total_tokens,
                    SUM(cost) as total_cost,
                    COUNT(*) as requests,
                    DATE(created_at) as date
                FROM ai_usage 
                WHERE created_at >= datetime("now", "-7 days")
                GROUP BY provider, model, DATE(created_at)
                ORDER BY created_at DESC
            ');
            
            $data = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }
            
            echo json_encode(['success' => true, 'usage' => $data]);
            break;
            
        case 'today':
            $result = $db->query('
                SELECT 
                    provider,
                    model,
                    SUM(input_tokens) as total_input,
                    SUM(output_tokens) as total_output,
                    SUM(cost) as total_cost,
                    COUNT(*) as requests
                FROM ai_usage 
                WHERE DATE(created_at) = DATE("now")
                GROUP BY provider, model
                ORDER BY total_cost DESC
            ');
            
            $data = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }
            
            echo json_encode(['success' => true, 'usage' => $data]);
            break;
            
        case 'recent':
            $result = $db->query('
                SELECT * FROM ai_usage 
                ORDER BY created_at DESC 
                LIMIT 50
            ');
            
            $data = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }
            
            echo json_encode(['success' => true, 'usage' => $data]);
            break;
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


