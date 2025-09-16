<?php
// Quick fix to add email column to users table
require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Add email column if it doesn't exist
    $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
    echo "Email column added successfully!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "Email column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>