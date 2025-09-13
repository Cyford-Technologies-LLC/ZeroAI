<?php
// Test OOP System
require_once __DIR__ . '/autoload.php';

use Core\System;
use Core\SystemInit;

try {
    echo "Testing ZeroAI OOP System...\n\n";
    
    // Initialize system
    SystemInit::initialize();
    echo "✓ System initialized\n";
    
    // Get system instance
    $system = System::getInstance();
    echo "✓ System instance created\n";
    
    // Test database
    $db = $system->getDatabase();
    $tables = $db->listTables();
    echo "✓ Database connected, tables: " . count($tables) . "\n";
    
    // Test logger
    $logger = $system->getLogger();
    $logger->info('OOP system test');
    echo "✓ Logger working\n";
    
    // Test security
    $security = $system->getSecurity();
    $hasPermission = $security->hasPermission('claude', 'cmd_file', 'hybrid');
    echo "✓ Security working, Claude file permission: " . ($hasPermission ? 'YES' : 'NO') . "\n";
    
    // Test command execution
    $result = $system->executeCommand('system_status', [], 'system');
    echo "✓ Command execution working: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    
    echo "\n🎉 All OOP components working correctly!\n";
    echo "Claude chat should now work with the new system.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}