<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    restoreFromBackup();
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function restoreFromBackup() {
    $input = json_decode(file_get_contents('php://input'), true);
    $backup_name = $input['backup_name'] ?? '';
    $restore_database = $input['restore_database'] ?? false;
    $restore_config = $input['restore_config'] ?? false;
    $restore_knowledge = $input['restore_knowledge'] ?? false;
    
    if (!$backup_name) {
        echo json_encode(['success' => false, 'error' => 'No backup specified']);
        return;
    }
    
    $backup_file = '../backups/' . $backup_name . '.zip';
    if (!file_exists($backup_file)) {
        echo json_encode(['success' => false, 'error' => 'Backup file not found']);
        return;
    }
    
    $temp_dir = '../temp/restore_' . uniqid();
    if (!mkdir($temp_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Cannot create temp directory']);
        return;
    }
    
    try {
        // Extract backup
        $zip = new ZipArchive();
        if ($zip->open($backup_file) !== TRUE) {
            throw new Exception('Cannot open backup file');
        }
        
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Check backup metadata
        $metadata_file = $temp_dir . '/backup_info.json';
        if (file_exists($metadata_file)) {
            $metadata = json_decode(file_get_contents($metadata_file), true);
            // Could validate backup version here
        }
        
        $restored_items = [];
        
        // Restore database
        if ($restore_database && file_exists($temp_dir . '/database.sql')) {
            if (restoreDatabase($temp_dir . '/database.sql')) {
                $restored_items[] = 'database';
            } else {
                throw new Exception('Database restore failed');
            }
        }
        
        // Restore config files
        if ($restore_config) {
            if (is_dir($temp_dir . '/config')) {
                copyDirectory($temp_dir . '/config', '../config');
                $restored_items[] = 'config';
            }
            
            if (file_exists($temp_dir . '/.env')) {
                copy($temp_dir . '/.env', '../.env');
            }
        }
        
        // Restore knowledge base
        if ($restore_knowledge && is_dir($temp_dir . '/knowledge')) {
            // Backup current knowledge if it exists
            if (is_dir('../knowledge')) {
                $backup_current = '../knowledge_backup_' . date('Y-m-d_H-i-s');
                rename('../knowledge', $backup_current);
            }
            
            copyDirectory($temp_dir . '/knowledge', '../knowledge');
            $restored_items[] = 'knowledge';
        }
        
        // Clean up temp directory
        removeDirectory($temp_dir);
        
        echo json_encode([
            'success' => true, 
            'restored' => $restored_items,
            'message' => 'Restore completed successfully'
        ]);
        
    } catch (Exception $e) {
        // Clean up temp directory on error
        if (is_dir($temp_dir)) {
            removeDirectory($temp_dir);
        }
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function restoreDatabase($sql_file) {
    try {
        $pdo = new PDO("sqlite:../config/zeroai.db");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Drop existing tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS $table");
        }
        
        // Execute backup SQL
        $sql = file_get_contents($sql_file);
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollback();
        }
        return false;
    }
}

function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item, $target);
        }
    }
}

function removeDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item);
        } else {
            unlink($item);
        }
    }
    
    rmdir($dir);
}
?>