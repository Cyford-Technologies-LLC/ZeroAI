<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$backup_dir = '../backups';

// Ensure backup directory exists
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

switch ($method) {
    case 'GET':
        if ($_GET['action'] === 'list') {
            listBackups();
        } elseif ($_GET['action'] === 'download') {
            downloadBackup($_GET['name']);
        }
        break;
    
    case 'POST':
        if ($_GET['action'] === 'upload') {
            uploadBackup();
        } else {
            createBackup();
        }
        break;
    
    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        deleteBackup($input['name']);
        break;
}

function listBackups() {
    global $backup_dir;
    
    $backups = [];
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/*.zip');
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file, '.zip'),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => formatBytes(filesize($file))
            ];
        }
    }
    
    echo json_encode(['success' => true, 'backups' => $backups]);
}

function createBackup() {
    global $backup_dir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $backup_name = $input['name'] ?? 'backup_' . date('Y-m-d_H-i-s');
    $include_database = $input['include_database'] ?? true;
    $include_config = $input['include_config'] ?? true;
    $include_knowledge = $input['include_knowledge'] ?? true;
    
    $backup_file = $backup_dir . '/' . $backup_name . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($backup_file, ZipArchive::CREATE) !== TRUE) {
        echo json_encode(['success' => false, 'error' => 'Cannot create backup file']);
        return;
    }
    
    try {
        // Add database backup
        if ($include_database) {
            $db_backup = createDatabaseBackup();
            if ($db_backup) {
                $zip->addFromString('database.sql', $db_backup);
            }
        }
        
        // Add config files
        if ($include_config) {
            addDirectoryToZip($zip, '../config', 'config');
            addDirectoryToZip($zip, '../.env', '.env');
        }
        
        // Add knowledge base
        if ($include_knowledge) {
            if (is_dir('../knowledge')) {
                addDirectoryToZip($zip, '../knowledge', 'knowledge');
            }
        }
        
        // Add backup metadata
        $metadata = [
            'created' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'includes' => [
                'database' => $include_database,
                'config' => $include_config,
                'knowledge' => $include_knowledge
            ]
        ];
        $zip->addFromString('backup_info.json', json_encode($metadata, JSON_PRETTY_PRINT));
        
        $zip->close();
        
        echo json_encode(['success' => true, 'backup_file' => $backup_name . '.zip']);
    } catch (Exception $e) {
        $zip->close();
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createDatabaseBackup() {
    try {
        $pdo = new PDO("sqlite:../config/zeroai.db");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $backup = "-- ZeroAI Database Backup\n";
        $backup .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Get all tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Get table schema
            $schema = $pdo->query("SELECT sql FROM sqlite_master WHERE name='$table'")->fetchColumn();
            $backup .= $schema . ";\n\n";
            
            // Get table data
            $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                foreach ($rows as $row) {
                    $columns = implode(',', array_keys($row));
                    $values = implode(',', array_map(function($v) { return "'" . str_replace("'", "''", $v) . "'"; }, array_values($row)));
                    $backup .= "INSERT INTO $table ($columns) VALUES ($values);\n";
                }
                $backup .= "\n";
            }
        }
        
        return $backup;
    } catch (Exception $e) {
        return false;
    }
}

function addDirectoryToZip($zip, $source, $destination) {
    if (is_file($source)) {
        $zip->addFile($source, $destination);
        return;
    }
    
    if (!is_dir($source)) return;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            $zip->addEmptyDir($destination . '/' . $iterator->getSubPathName());
        } elseif ($file->isFile()) {
            $zip->addFile($file, $destination . '/' . $iterator->getSubPathName());
        }
    }
}

function downloadBackup($name) {
    global $backup_dir;
    
    $file = $backup_dir . '/' . $name . '.zip';
    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup not found']);
        return;
    }
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '.zip"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
}

function deleteBackup($name) {
    global $backup_dir;
    
    $file = $backup_dir . '/' . $name . '.zip';
    if (file_exists($file)) {
        if (unlink($file)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete backup']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Backup not found']);
    }
}

function uploadBackup() {
    global $backup_dir;
    
    if (!isset($_FILES['backup_file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['backup_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload error']);
        return;
    }
    
    $filename = basename($file['name']);
    if (!str_ends_with($filename, '.zip')) {
        echo json_encode(['success' => false, 'error' => 'Only ZIP files are allowed']);
        return;
    }
    
    $destination = $backup_dir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>