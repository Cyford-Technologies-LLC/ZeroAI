<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->info('Starting tenant database schema update');
    
    $dataDir = '/app/data/companies';
    
    if (!is_dir($dataDir)) {
        $logger->error('Data directory not found', ['dir' => $dataDir]);
        exit(1);
    }
    
    $directories = scandir($dataDir);
    $updated = 0;
    
    foreach ($directories as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $orgPath = $dataDir . '/' . $dir;
        $dbPath = $orgPath . '/crm.db';
        
        if (!is_dir($orgPath) || !file_exists($dbPath)) {
            continue;
        }
        
        try {
            $logger->info('Updating tenant database schema', ['org_id' => $dir, 'db_path' => $dbPath]);
            
            $pdo = new PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Add missing columns to companies table
            $alterSql = "
            ALTER TABLE companies ADD COLUMN website TEXT;
            ALTER TABLE companies ADD COLUMN industry TEXT;
            ALTER TABLE companies ADD COLUMN notes TEXT;
            ";
            
            // Execute each ALTER statement separately to handle existing columns
            $statements = explode(';', $alterSql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (empty($stmt)) continue;
                
                try {
                    $pdo->exec($stmt);
                } catch (Exception $e) {
                    // Column might already exist, continue
                    $logger->debug('Column might already exist', ['stmt' => $stmt, 'error' => $e->getMessage()]);
                }
            }
            
            $updated++;
            $logger->info('Updated tenant database schema', ['org_id' => $dir]);
            
        } catch (Exception $e) {
            $logger->error('Failed to update tenant database', [
                'org_id' => $dir,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    $logger->info('Tenant database schema update completed', ['updated_count' => $updated]);
    echo "Updated {$updated} tenant databases\n";
    
} catch (Exception $e) {
    if (isset($logger)) {
        $logger->error('Schema update script failed', ['error' => $e->getMessage()]);
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}