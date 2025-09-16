<?php
require_once 'src/Core/DatabaseManager.php';

try {
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    
    echo "=== USERS ===\n";
    $users = $db->query("SELECT id, username, email, role, organization_id FROM users ORDER BY id DESC LIMIT 5");
    foreach ($users as $user) {
        echo "User ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}, Org: {$user['organization_id']}\n";
    }
    
    echo "\n=== COMPANIES ===\n";
    $companies = $db->query("SELECT id, name, email, user_id, created_by, organization_id FROM companies ORDER BY id DESC LIMIT 5");
    foreach ($companies as $company) {
        echo "Company ID: {$company['id']}, Name: {$company['name']}, Email: {$company['email']}, User ID: {$company['user_id']}, Created By: {$company['created_by']}, Org: {$company['organization_id']}\n";
    }
    
    echo "\n=== COMPANY-USER LINKS ===\n";
    $links = $db->query("SELECT c.name, c.email, u.username FROM companies c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.id DESC LIMIT 5");
    foreach ($links as $link) {
        echo "Company: {$link['name']}, Email: {$link['email']}, Owner: {$link['username']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>