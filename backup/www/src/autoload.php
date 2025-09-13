<?php
/**
 * Simple autoloader for ZeroAI classes
 */

spl_autoload_register(function ($className) {
    // Convert namespace to file path
    $className = str_replace('ZeroAI\\', '', $className);
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $file = __DIR__ . DIRECTORY_SEPARATOR . $className . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});
?>