<?php
// Initialize companies table for CRM
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Create companies table
    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        ein TEXT,
        business_id TEXT,
        email TEXT,
        phone TEXT,
        website TEXT,
        linkedin TEXT,
        address TEXT,
        industry TEXT,
        about TEXT,
        organization_id INTEGER DEFAULT 1,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "Companies table created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>