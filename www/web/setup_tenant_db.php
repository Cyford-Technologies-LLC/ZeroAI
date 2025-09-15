<?php
// Multi-Tenant Database Setup
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Tenants table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        domain TEXT UNIQUE,
        secret_key TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Update companies table to include tenant_id
    $pdo->exec("ALTER TABLE companies ADD COLUMN tenant_id INTEGER");
    
    // Insert sample tenants
    $pdo->exec("INSERT OR IGNORE INTO tenants (id, name, domain) VALUES 
        (1, 'Acme Corporation', 'acme.com'),
        (2, 'Global Solutions', 'globalsolutions.com'),
        (3, 'Tech Innovators', 'techinnovators.com')
    ");
    
    // Update existing companies with tenant_id
    $pdo->exec("UPDATE companies SET tenant_id = 1 WHERE tenant_id IS NULL AND id <= 1");
    $pdo->exec("UPDATE companies SET tenant_id = 2 WHERE tenant_id IS NULL AND id = 2");
    $pdo->exec("UPDATE companies SET tenant_id = 3 WHERE tenant_id IS NULL AND id >= 3");
    
    echo "Multi-tenant database setup completed successfully!";
    
} catch (Exception $e) {
    echo "Error setting up multi-tenant database: " . $e->getMessage();
}
?>

