<?php
session_start();

$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove query parameters for routing
$path = strtok($path, '?');

switch ($path) {
    case '/':
    case '/admin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleAdminLogin();
        } else {
            include __DIR__ . '/admin/login.php';
        }
        break;
        
    case '/admin/dashboard':
        requireAdminAuth();
        include __DIR__ . '/admin/dashboard.php';
        break;
        
    case '/admin/users':
        requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleUserAction();
        } else {
            include __DIR__ . '/admin/users.php';
        }
        break;
        
    case '/admin/agents':
        requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleAgentAction();
        } else {
            include __DIR__ . '/admin/agents.php';
        }
        break;
        
    case '/web':
    case '/web/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleWebLogin();
        } else {
            include __DIR__ . '/web/login.php';
        }
        break;
        
    case '/web/frontend':
        requireWebAuth();
        include __DIR__ . '/web/frontend.php';
        break;
        
    case '/web/logout':
        session_destroy();
        header('Location: /web');
        exit;
        break;
        
    case '/admin/logout':
        session_destroy();
        header('Location: /admin');
        exit;
        break;
        
    default:
        http_response_code(404);
        echo "<h1>404 Not Found</h1><p>Path: " . htmlspecialchars($path) . "</p>";
        break;
}

function requireAdminAuth() {
    if (!isset($_SESSION['admin_logged_in'])) {
        header('Location: /admin');
        exit;
    }
}

function requireWebAuth() {
    if (!isset($_SESSION['web_logged_in'])) {
        header('Location: /web');
        exit;
    }
}

function handleAdminLogin() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple auth - replace with database later
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        header('Location: /admin/dashboard');
        exit;
    } else {
        $error = 'Invalid credentials';
        include __DIR__ . '/admin/login.php';
    }
}

function handleWebLogin() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple auth - replace with database later
    if ($username === 'user' && $password === 'user123') {
        $_SESSION['web_logged_in'] = true;
        $_SESSION['web_user'] = $username;
        header('Location: /web/frontend');
        exit;
    } else {
        $error = 'Invalid credentials';
        include __DIR__ . '/web/login.php';
    }
}

function handleUserAction() {
    // User management logic here
    header('Location: /admin/users');
    exit;
}

function handleAgentAction() {
    // Agent management logic here
    header('Location: /admin/agents');
    exit;
}
?>