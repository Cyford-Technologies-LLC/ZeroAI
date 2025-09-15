<?php
// ZeroAI Autoloader (Load first)
spl_autoload_register(function ($class) {
    // Remove ZeroAI namespace prefix
    $class = ltrim($class, 'ZeroAI\\');
    
    // Convert namespace to file path
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
});

// Load common classes
require_once __DIR__ . '/../config/database.php';


