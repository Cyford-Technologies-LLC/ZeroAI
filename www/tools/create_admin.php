<?php
require_once '../bootstrap.php';

use ZeroAI\Core\DatabaseManager;

$db = new DatabaseManager();

// Create users table if not exists
$db->executeSQL("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Delete existing admin user
$db->executeSQL("DELETE FROM users WHERE username = 'admin'");

// Create new admin user
$password = password_hash('admin123', PASSWORD_DEFAULT);
$result = $db->executeSQL("INSERT INTO users (username, password, role) VALUES ('admin', '$password', 'admin')");

if (!isset($result[0]['error'])) {
    echo "Admin user created successfully!\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
} else {
    echo "Error creating admin user: " . $result[0]['error'] . "\n";
}

// Show all users
echo "\nCurrent users:\n";
$users = $db->executeSQL("SELECT id, username, role, created_at FROM users");
if (!empty($users[0]['data'])) {
    foreach ($users[0]['data'] as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}\n";
    }
}
?>