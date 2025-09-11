<?php
header('Content-Type: application/json');
require_once __DIR__ . '/sqlite_manager.php';

try {
    // Initialize table if not exists
    SQLiteManager::executeSQL("
        CREATE TABLE IF NOT EXISTS claude_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_id TEXT UNIQUE NOT NULL,
            display_name TEXT NOT NULL,
            is_default INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Add default models if table is empty
    $count = SQLiteManager::executeSQL("SELECT COUNT(*) as count FROM claude_models")[0]['data'][0]['count'] ?? 0;
    if ($count == 0) {
        $defaultModels = [
            ['claude-3-5-sonnet-20241022', 'Claude 3.5 Sonnet', 1],
            ['claude-3-opus-20240229', 'Claude 3 Opus', 0],
            ['claude-3-sonnet-20240229', 'Claude 3 Sonnet', 0],
            ['claude-3-haiku-20240307', 'Claude 3 Haiku', 0]
        ];
        
        foreach ($defaultModels as $model) {
            SQLiteManager::executeSQL(
                "INSERT INTO claude_models (model_id, display_name, is_default) VALUES (?, ?, ?)",
                $model
            );
        }
    }
    
    $models = SQLiteManager::executeSQL("SELECT * FROM claude_models ORDER BY is_default DESC, display_name ASC")[0]['data'] ?? [];
    
    echo json_encode([
        'success' => true,
        'models' => $models
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>