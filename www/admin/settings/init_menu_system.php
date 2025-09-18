<?php
// Initialize Menu System - Run this once to set up the menu system
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../web/includes/menu_system.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Initialize menu system (this will create the table and default data)
    $menuSystem = new MenuSystem($pdo);
    
    echo "Menu system initialized successfully!<br>";
    echo "Default menus have been created.<br>";
    echo "<a href='/admin/settings/menu_manager.php'>Go to Menu Manager</a><br>";
    echo "<a href='/web/companies.php'>Test Companies Page</a>";
    
} catch (Exception $e) {
    echo "Error initializing menu system: " . $e->getMessage();
}
?>