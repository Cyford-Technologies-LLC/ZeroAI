<?php
require_once '../bootstrap.php';

use ZeroAI\Core\DatabaseManager;

$db = DatabaseManager::getInstance();

echo "Available databases:\n";
$databases = $db->getDatabases();
foreach ($databases as $dbName) {
    echo "- $dbName\n";
}

echo "\nChecking for users in each database:\n";
foreach ($databases as $dbName) {
    echo "\n=== Database: $dbName ===\n";
    
    // Check if users table exists
    $tables = $db->listTables($dbName);
    $hasUsersTable = false;
    foreach ($tables as $table) {
        if ($table['name'] === 'users') {
            $hasUsersTable = true;
            break;
        }
    }
    
    if ($hasUsersTable) {
        echo "Users table found. Current users:\n";
        $users = $db->query("SELECT id, username, role, created_at FROM users", $dbName);
        if (!empty($users[0]['data'])) {
            foreach ($users[0]['data'] as $user) {
                echo "  ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}\n";
            }
        } else {
            echo "  No users found\n";
        }
    } else {
        echo "No users table in this database\n";
    }
}
?>

