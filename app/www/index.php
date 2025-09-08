<?php
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

switch ($path) {
    case '/':
    case '/admin':
        include __DIR__ . '/admin/login.php';
        break;
    case '/admin/dashboard':
        include __DIR__ . '/admin/dashboard.php';
        break;
    case '/admin/users':
        include __DIR__ . '/admin/users.php';
        break;
    case '/admin/agents':
        include __DIR__ . '/admin/agents.php';
        break;
    case '/admin/logout.php':
        include __DIR__ . '/admin/logout.php';
        break;
    case '/web':
    case '/web/login':
        include __DIR__ . '/web/login.php';
        break;
    case '/web/frontend':
        include __DIR__ . '/web/frontend.php';
        break;
    case '/web/logout.php':
        include __DIR__ . '/web/logout.php';
        break;
    default:
        http_response_code(404);
        echo "<h1>404 Not Found</h1><p>Path: $path</p>";
        break;
}
?>