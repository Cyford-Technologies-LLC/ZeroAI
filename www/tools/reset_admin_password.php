<?php
require_once '../bootstrap.php';

use ZeroAI\Core\DatabaseManager;

$db = new DatabaseManager();

// Get current admin user
$users = $db->executeSQL("SELECT id, username, password FROM users WHERE username = 'admin'");

if (!empty($users[0]['data'])) {
    $admin = $users[0]['data'][0];
    echo "Found admin user (ID: {$admin['id']})\n";
    
    // Reset password to 'admin123'
    $newPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $result = $db->executeSQL("UPDATE users SET password = '$newPassword' WHERE id = {$admin['id']}");
    
    if (!isset($result[0]['error'])) {
        echo "Password reset successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "Error resetting password: " . $result[0]['error'] . "\n";
    }
} else {
    echo "No admin user found\n";
}
?>