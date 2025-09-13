<?php
// Simple router for ZeroAI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

// Route to appropriate files
switch ($uri) {
    case '':
    case 'home':
        require 'landing.php';
        break;
        
    case 'admin':
        header('Location: /admin/login.php');
        break;
        
    case 'web':
        if (file_exists('web/index.php')) {
            require 'web/index.php';
        } else {
            echo "User portal coming soon...";
        }
        break;
        
    default:
        // Check if it's an admin route
        if (str_starts_with($uri, 'admin/')) {
            $adminFile = str_replace('admin/', 'admin/', $uri) . '.php';
            if (file_exists($adminFile)) {
                require $adminFile;
            } else {
                http_response_code(404);
                echo "Admin page not found: $uri";
            }
        } else {
            http_response_code(404);
            echo "Page not found: $uri";
        }
        break;
}
?>