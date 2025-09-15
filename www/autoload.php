<?php
// ZeroAI OOP Autoloader
spl_autoload_register(function ($className) {
    // Handle Core namespace
    if (str_starts_with($className, 'Core\\')) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $className) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    // Handle other namespaces
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// Initialize environment
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

