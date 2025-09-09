<?php
// ZeroAI Portal Bootstrap
session_start();

// Autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Configuration
define('BASE_PATH', __DIR__);
define('DB_PATH', '/app/data/zeroai.db');

// Initialize application
$app = new Core\Application();
$app->run();
?>