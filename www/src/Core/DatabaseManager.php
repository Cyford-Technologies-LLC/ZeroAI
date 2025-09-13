<?php
namespace ZeroAI\Core;

class DatabaseManager {
    private $databases = [];
    private $dataDir = '/app/data';
    
    public function __construct() {
        $this->scanDatabases();
    }
    
    private function scanDatabases(): void {
        try {
            if (!is_dir($this->dataDir)) {
                mkdir($this->dataDir, 0777, true);
            }
            
            $files = glob($this->dataDir . '/*.db');
            foreach ($files as $file) {
                $name = basename($file, '.db');
                $this->databases[$name] = $file;
            }
            
            // Ensure main database exists
            if (!isset($this->databases['main'])) {
                $this->createDatabase('main');
            }
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('Database scan failed', ['error' => $e->getMessage()]);
        }
    }
    
    public function getConnection(string $dbName = 'main'): \SQLite3 {
        try {
            if (!isset($this->databases[$dbName])) {
                throw new \Exception("Database not found: $dbName");
            }
            
            return new \SQLite3($this->databases[$dbName]);
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('Database connection failed', [
                'database' => $dbName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function executeSQL(string $sql, string $dbName = 'main'): array {
        try {
            $db = $this->getConnection($dbName);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $results = [];
            
            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                
                $result = $db->query($statement);
                
                if ($result === false) {
                    $results[] = ['error' => $db->lastErrorMsg()];
                } elseif ($result === true) {
                    $results[] = ['success' => 'Query executed', 'changes' => $db->changes()];
                } else {
                    $rows = [];
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $rows[] = $row;
                    }
                    $results[] = ['data' => $rows, 'count' => count($rows)];
                }
            }
            
            $db->close();
            return $results;
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('SQL execution failed', [
                'database' => $dbName,
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
            return [['error' => $e->getMessage()]];
        }
    }
    
    public function createDatabase(string $name): bool {
        try {
            $dbPath = $this->dataDir . "/$name.db";
            $db = new \SQLite3($dbPath);
            
            // Initialize with basic tables
            $db->exec("
                CREATE TABLE IF NOT EXISTS system_info (
                    key TEXT PRIMARY KEY,
                    value TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                
                INSERT OR REPLACE INTO system_info (key, value) VALUES 
                ('created_at', datetime('now')),
                ('version', '1.0');
            ");
            
            $db->close();
            $this->databases[$name] = $dbPath;
            
            \ZeroAI\Core\Logger::getInstance()->info("Database created: $name");
            return true;
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('Database creation failed', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function listTables(string $dbName = 'main'): array {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
        $result = $this->executeSQL($sql, $dbName);
        return $result[0]['data'] ?? [];
    }
    
    public function describeTable(string $table, string $dbName = 'main'): array {
        $sql = "PRAGMA table_info($table)";
        $result = $this->executeSQL($sql, $dbName);
        return $result[0]['data'] ?? [];
    }
    
    public function getDatabases(): array {
        return array_keys($this->databases);
    }
    
    public function getDatabasePath(string $name): ?string {
        return $this->databases[$name] ?? null;
    }
    
    public function backupDatabase(string $dbName, ?string $backupPath = null): bool {
        try {
            if (!isset($this->databases[$dbName])) {
                throw new \Exception("Database not found: $dbName");
            }
            
            $sourcePath = $this->databases[$dbName];
            $backupPath = $backupPath ?: $this->dataDir . "/backup_{$dbName}_" . date('Y-m-d_H-i-s') . '.db';
            
            $result = copy($sourcePath, $backupPath);
            
            if ($result) {
                \ZeroAI\Core\Logger::getInstance()->info("Database backed up: $dbName", ['backup_path' => $backupPath]);
            }
            
            return $result;
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('Database backup failed', [
                'database' => $dbName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}