<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Add missing columns to companies table
    $columns = ['address', 'user_id', 'created_by'];
    
    foreach ($columns as $column) {
        try {
            if ($column === 'user_id') {
                $pdo->exec("ALTER TABLE companies ADD COLUMN user_id INTEGER");
            } elseif ($column === 'created_by') {
                $pdo->exec("ALTER TABLE companies ADD COLUMN created_by INTEGER");
            } else {
                $pdo->exec("ALTER TABLE companies ADD COLUMN $column TEXT");
            }
            echo "Added column: $column\n";
        } catch (Exception $e) {
            echo "Column $column already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Companies table updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>