<?php
session_start();

// Simple PHP router
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path if needed
$path = str_replace('/app/www', '', $path);

switch ($path) {
    case '/':
    case '/admin':
        include 'admin/login.php';
        break;
    case '/admin/dashboard':
        include 'admin/dashboard.php';
        break;
    case '/admin/users':
        include 'admin/users.php';
        break;
    case '/admin/agents':
        include 'admin/agents.php';
        break;
    case '/web':
    case '/web/login':
        include 'web/login.php';
        break;
    case '/web/frontend':
        include 'web/frontend.php';
        break;
    case '/api/login':
        include 'api/auth.php';
        break;
    case '/api/users':
        include 'api/users.php';
        break;
    default:
        http_response_code(404);
        echo "Page not found";
        break;
}
?>