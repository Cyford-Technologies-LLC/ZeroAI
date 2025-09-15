<?php
require_once '../bootstrap.php';
require_once '../src/Core/InputValidator.php';

use ZeroAI\Core\DatabaseManager;
use ZeroAI\Core\InputValidator;

$db = DatabaseManager::getInstance();

// Create users table if not exists
$db->query("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Get admin credentials from environment or prompt
$adminUsername = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPassword = getenv('ADMIN_PASSWORD');

// If no password in environment, generate a secure one
if (empty($adminPassword)) {
    $adminPassword = generateSecurePassword();
    echo "Generated secure admin password: $adminPassword\n";
    echo "Please save this password securely and set ADMIN_PASSWORD environment variable.\n\n";
}

// Validate inputs
$adminUsername = InputValidator::sanitize($adminUsername);
if (strlen($adminPassword) < 8) {
    die("Error: Password must be at least 8 characters long.\n");
}

// Delete existing admin user
$db->query("DELETE FROM users WHERE username = ?", [$adminUsername]);

// Create new admin user with secure password hashing
$hashedPassword = password_hash($adminPassword, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536, // 64 MB
    'time_cost' => 4,       // 4 iterations
    'threads' => 3          // 3 threads
]);

$result = $db->query(
    "INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')",
    [$adminUsername, $hashedPassword]
);

if (!isset($result[0]['error'])) {
    echo "Admin user created successfully!\n";
    echo "Username: $adminUsername\n";
    if (!getenv('ADMIN_PASSWORD')) {
        echo "Password: $adminPassword (SAVE THIS SECURELY!)\n";
    } else {
        echo "Password: [From environment variable]\n";
    }
} else {
    echo "Error creating admin user: " . $result[0]['error'] . "\n";
}

// Show all users (without sensitive data)
echo "\nCurrent users:\n";
$users = $db->query("SELECT id, username, role, created_at FROM users");
if (!empty($users[0]['data'])) {
    foreach ($users[0]['data'] as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}\n";
    }
}

/**
 * Generate a cryptographically secure password
 */
function generateSecurePassword($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}
?>

