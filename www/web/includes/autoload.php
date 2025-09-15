<?php
// Autoload for Web CRM
spl_autoload_register(function ($class) {
    $prefix = 'ZeroAI\\Web\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Also load admin autoload for shared classes
require_once __DIR__ . '/../../admin/includes/autoload.php';
?>

