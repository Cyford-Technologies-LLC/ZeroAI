<?php
namespace ZeroAI\Services;

use ZeroAI\Core\DatabaseManager;

class SettingsService {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->initTable();
    }
    
    private function initTable() {
        $this->db->executeSQL("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT UNIQUE NOT NULL,
                value TEXT,
                category TEXT DEFAULT 'general',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function getSetting(string $key, string $default = ''): string {
        $result = $this->db->executeSQL("SELECT value FROM settings WHERE key = '$key'");
        return $result[0]['data'][0]['value'] ?? $default;
    }
    
    public function setSetting(string $key, string $value, string $category = 'general'): bool {
        $result = $this->db->executeSQL("
            INSERT OR REPLACE INTO settings (key, value, category, updated_at) 
            VALUES ('$key', '$value', '$category', datetime('now'))
        ");
        return !isset($result[0]['error']);
    }
    
    public function getAllSettings(): array {
        $result = $this->db->executeSQL("SELECT * FROM settings ORDER BY category, key");
        return $result[0]['data'] ?? [];
    }
    
    public function getSettingsByCategory(string $category): array {
        $result = $this->db->executeSQL("SELECT * FROM settings WHERE category = '$category' ORDER BY key");
        return $result[0]['data'] ?? [];
    }
    
    public function deleteSetting(string $key): bool {
        $result = $this->db->executeSQL("DELETE FROM settings WHERE key = '$key'");
        return !isset($result[0]['error']);
    }
}
