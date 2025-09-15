<?php
session_start();

// Check authentication
if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';

// Get CRM stats
$stats = ['companies' => 0, 'contacts' => 0, 'projects' => 0, 'tasks' => 0];
$recentActivities = [];

try {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check if tables exist and get stats
    $tables = ['companies', 'contacts', 'projects', 'tasks'];
    foreach ($tables as $table) {
        try {
            if ($table === 'tasks') {
                $count = $pdo->query("SELECT COUNT(*) FROM $table WHERE status != 'completed'")->fetchColumn();
            } else {
                $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            }
            $stats[$table] = $count ?: 0;
        } catch (Exception $e) {
            $stats[$table] = 0;
        }
    }
    
    // Get recent activities
    try {
        $recentActivities = $pdo->query("
            SELECT 'project' as type, name as title, created_at, status 
            FROM projects 
            ORDER BY created_at DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $recentActivities = [];
    }
    
} catch (Exception $e) {
    // Keep default values
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroAI CRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .sidebar { min-height: 100vh; background: #f8f9fa; }
        .main-content { padding: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-3">
                <h5 class="text-primary mb-4">
                    <i class="bi bi-robot"></i> ZeroAI CRM
                </h5>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="/web/"><i class="bi bi-house"></i> Dashboard</a>
                    <a class="nav-link" href="/web/dashboard.php"><i class="bi bi-building-gear"></i> Multi-Tenant CRM</a>
                    <a class="nav-link" href="/web/companies.php"><i class="bi bi-building"></i> Companies</a>
                    <a class="nav-link" href="/web/contacts.php"><i class="bi bi-people"></i> Contacts</a>
                    <a class="nav-link" href="/web/projects.php"><i class="bi bi-folder"></i> Projects</a>
                    <a class="nav-link" href="/web/tasks.php"><i class="bi bi-check-square"></i> Tasks</a>
                    <a class="nav-link" href="/web/ai_center.php"><i class="bi bi-robot"></i> AI Center</a>
                    <hr>
                    <a class="nav-link" href="/admin/"><i class="bi bi-gear"></i> Admin</a>
                    <a class="nav-link" href="/web/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>CRM Dashboard</h2>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser) ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/admin/settings.php">Settings</a></li>
                            <li><a class="dropdown-item" href="/web/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?= $stats['companies'] ?></h3>
                                        <p class="mb-0">Companies</p>
                                    </div>
                                    <i class="bi bi-building display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?= $stats['contacts'] ?></h3>
                                        <p class="mb-0">Contacts</p>
                                    </div>
                                    <i class="bi bi-people display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?= $stats['projects'] ?></h3>
                                        <p class="mb-0">Projects</p>
                                    </div>
                                    <i class="bi bi-folder display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?= $stats['tasks'] ?></h3>
                                        <p class="mb-0">Active Tasks</p>
                                    </div>
                                    <i class="bi bi-check-square display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-lightning"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="/web/companies.php?action=add" class="btn btn-primary w-100 mb-2">
                                            <i class="bi bi-plus"></i> Add Company
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="/web/contacts.php?action=add" class="btn btn-success w-100 mb-2">
                                            <i class="bi bi-person-plus"></i> Add Contact
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="/web/projects.php?action=add" class="btn btn-info w-100 mb-2">
                                            <i class="bi bi-folder-plus"></i> New Project
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="/web/tasks.php?action=add" class="btn btn-warning w-100 mb-2">
                                            <i class="bi bi-plus-square"></i> Add Task
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-clock-history"></i> Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivities)): ?>
                                    <p class="text-muted">No recent activity</p>
                                    <?php if ($stats['companies'] == 0 && $stats['projects'] == 0): ?>
                                        <div class="alert alert-info mt-3">
                                            <strong>Getting Started:</strong><br>
                                            <a href="/web/setup_crm.php" class="btn btn-sm btn-primary">Setup CRM Database</a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                    <small><?= date('M j, Y', strtotime($activity['created_at'])) ?></small>
                                                </div>
                                                <p class="mb-1">
                                                    <span class="badge bg-secondary"><?= ucfirst($activity['type']) ?></span>
                                                    <span class="badge bg-primary"><?= ucfirst($activity['status'] ?? 'active') ?></span>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-robot"></i> AI Assistant</h5>
                            </div>
                            <div class="card-body">
                                <p>Your AI workforce is ready to help with:</p>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check text-success"></i> Data analysis</li>
                                    <li><i class="bi bi-check text-success"></i> Report generation</li>
                                    <li><i class="bi bi-check text-success"></i> Task automation</li>
                                    <li><i class="bi bi-check text-success"></i> Customer insights</li>
                                </ul>
                                <a href="/web/ai_center.php" class="btn btn-primary w-100">
                                    <i class="bi bi-robot"></i> Launch AI Center
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>