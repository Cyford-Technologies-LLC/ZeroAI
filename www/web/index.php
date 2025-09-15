<?php
session_start();

// Load error logging
require_once __DIR__ . '/../admin/includes/autoload.php';

// Log web portal access
try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->info('Web portal accessed', [
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_id' => session_id()
    ]);
} catch (Exception $e) {
    error_log('Logger failed: ' . $e->getMessage());
}

// Check if user is logged in (admin or web session)
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['web_logged_in'])) {
    try {
        $logger->warning('Unauthorized web portal access', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'redirect_to' => '/web/login.php'
        ]);
    } catch (Exception $e) {
        error_log('Logger failed: ' . $e->getMessage());
    }
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['admin_user'] ?? $_SESSION['web_user'] ?? 'User';

$pageTitle = 'ZeroAI User Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .navbar-brand { font-weight: bold; }
        .feature-card { transition: transform 0.3s; height: 100%; }
        .feature-card:hover { transform: translateY(-5px); }
        .feature-icon { font-size: 3rem; color: #0d6efd; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/web">
                <i class="bi bi-robot"></i> ZeroAI Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/web">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/web/ai_center.php">AI Center</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/">Admin</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/admin/settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/admin/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="card-title">Welcome to ZeroAI User Portal</h1>
                        <p class="card-text lead">Manage your AI workforce and projects from this central dashboard.</p>
                        
                        <div class="d-flex flex-wrap gap-2">
                            <a href="/web/ai_center.php" class="btn btn-primary">
                                <i class="bi bi-robot"></i> AI Community Center
                            </a>
                            <a href="/admin/agents.php" class="btn btn-outline-primary">
                                <i class="bi bi-gear"></i> Manage Agents
                            </a>
                            <a href="/admin/dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-graph-up"></i> System Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4 mt-4">
            <div class="col-md-4">
                <div class="card h-100 feature-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-robot"></i>
                        </div>
                        <h5 class="card-title">AI Agents</h5>
                        <p class="card-text">Browse and assign AI agents from the community pool to your projects.</p>
                        <a href="/web/ai_center.php" class="btn btn-primary">Browse Agents</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 feature-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h5 class="card-title">Analytics</h5>
                        <p class="card-text">Monitor your AI workforce performance and resource usage.</p>
                        <a href="/admin/dashboard.php" class="btn btn-primary">View Analytics</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 feature-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-gear"></i>
                        </div>
                        <h5 class="card-title">Configuration</h5>
                        <p class="card-text">Configure your AI agents and system settings.</p>
                        <a href="/admin/agents.php" class="btn btn-primary">Configure</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>