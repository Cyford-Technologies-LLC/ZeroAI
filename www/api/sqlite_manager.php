<?php
class SQLiteManager {
    public static function executeSQL($sql, $dbPath = '/app/data/main.db') {
        try {
            $db = new SQLite3($dbPath);
            
            // Handle multiple statements
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
        } catch (Exception $e) {
            return [['error' => $e->getMessage()]];
        }
    }
    
    public static function listTables($dbPath = '/app/data/main.db') {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
        return self::executeSQL($sql, $dbPath);
    }
    
    public static function describeTable($table, $dbPath = '/app/data/main.db') {
        $sql = "PRAGMA table_info($table)";
        return self::executeSQL($sql, $dbPath);
    }
    
    public static function createDatabase($dbPath) {
        try {
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $db = new SQLite3($dbPath);
            $db->close();
            return [['success' => "Database created: $dbPath"]];
        } catch (Exception $e) {
            return [['error' => $e->getMessage()]];
        }
    }
}
?>