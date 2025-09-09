<?php
// Enable error reporting if setting is on
if (isset($_SESSION['display_errors']) && $_SESSION['display_errors']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

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
            $error = $_SESSION['login_error'] ?? null;
            unset($_SESSION['login_error']);
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
        
    case '/admin/settings':
        requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleSettingsAction();
        } else {
            include __DIR__ . '/admin/settings.php';
        }
        break;
        
    case '/admin/crews':
        requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleCrewAction();
        } else {
            include __DIR__ . '/admin/crews.php';
        }
        break;
        
    case '/admin/tasks':
        requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleTaskAction();
        } else {
            include __DIR__ . '/admin/tasks.php';
        }
        break;
        
    case '/admin/knowledge':
        requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleKnowledgeAction();
        } else {
            include __DIR__ . '/admin/knowledge.php';
        }
        break;
        
    case '/admin/monitoring':
        requireAdminAuth();
        include __DIR__ . '/admin/monitoring.php';
        break;
        
    case '/admin/chat':
        requireAdminAuth();
        include __DIR__ . '/admin/chat.php';
        break;
        
    case '/admin/config':
        requireAdminAuth();
        include __DIR__ . '/admin/config.php';
        break;
        
    case '/admin/claude':
        requireAdminAuth();
        include __DIR__ . '/admin/claude.php';
        break;
        
    case '/admin/cloud_settings':
        requireAdminAuth();
        include __DIR__ . '/admin/cloud_settings.php';
        break;
        
    case '/admin/claude_settings':
        requireAdminAuth();
        include __DIR__ . '/admin/claude_settings.php';
        break;
        
    case '/admin/claude_chat':
        requireAdminAuth();
        include __DIR__ . '/admin/claude_chat.php';
        break;
        
    case '/admin/test_dynamic_agents':
        requireAdminAuth();
        include __DIR__ . '/admin/test_dynamic_agents.php';
        break;
        
    case '/admin/crew_chat':
        requireAdminAuth();
        include __DIR__ . '/admin/crew_chat.php';
        break;
        
    case '/admin/crewai':
        requireAdminAuth();
        include __DIR__ . '/admin/crewai.php';
        break;
        
    case '/admin/crew_stream.php':
        requireAdminAuth();
        include __DIR__ . '/admin/crew_stream.php';
        break;
        
    case '/web':
    case '/web/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleWebLogin();
        } else {
            $error = $_SESSION['web_login_error'] ?? null;
            unset($_SESSION['web_login_error']);
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
        // Set error in session and redirect
        $_SESSION['login_error'] = 'Invalid credentials';
        header('Location: /admin');
        exit;
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
        $_SESSION['web_login_error'] = 'Invalid credentials';
        header('Location: /web');
        exit;
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

function handleSettingsAction() {
    if (isset($_POST['display_errors'])) {
        $_SESSION['display_errors'] = $_POST['display_errors'] === '1';
    }
    $_SESSION['settings_message'] = 'Settings saved successfully';
    header('Location: /admin/settings');
    exit;
}

function handleCrewAction() {
    if ($_POST['action'] === 'create_crew') {
        // Create crew logic here
        $_SESSION['crew_message'] = 'Crew created successfully';
    }
    header('Location: /admin/crews');
    exit;
}

function handleTaskAction() {
    if ($_POST['action'] === 'create_task') {
        // Create task logic here
        $_SESSION['task_message'] = 'Task created successfully';
    }
    header('Location: /admin/tasks');
    exit;
}

function handleKnowledgeAction() {
    if ($_POST['action'] === 'add_knowledge') {
        // Add knowledge logic here
        $_SESSION['knowledge_message'] = 'Knowledge added successfully';
    }
    header('Location: /admin/knowledge');
    exit;
}
?>