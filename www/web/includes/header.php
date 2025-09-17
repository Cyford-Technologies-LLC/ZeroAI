<?php
require_once __DIR__ . '/csp.php';

session_start();
require_once __DIR__ . '/../../src/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = $pageTitle ?? 'ZeroAI CRM';
$currentPage = $currentPage ?? '';

// Get user's organization_id
$userOrgId = 1;
try {
    require_once __DIR__ . '/../../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT organization_id FROM users WHERE username = ?");
    $stmt->execute([$currentUser]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userOrgId = $user['organization_id'] ?? 1;
    }
} catch (Exception $e) {
    // Use default
}

// Get companies
try {
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT * FROM companies ORDER BY created_at DESC");
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE organization_id = ?");
        $stmt->execute([$userOrgId]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $companies = [];
}

// Get current context for dynamic sidebar
$currentContext = 'main';
$contextId = null;

if (strpos($_SERVER['REQUEST_URI'], '/projects.php') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/bugs.php') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/features.php') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/team.php') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/releases.php') !== false) {
    $currentContext = 'projects';
    $contextId = $_GET['project_id'] ?? null;
} elseif (strpos($_SERVER['REQUEST_URI'], '/companies.php') !== false) {
    $currentContext = 'companies';
    $contextId = $_GET['company_id'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <meta name="description" content="ZeroAI CRM - Manage your business efficiently with our powerful CRM system.">
    <meta name="keywords" content="CRM, Customer Relationship Management, ZeroAI, Business Management">
    <meta name="author" content="ZeroAI">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/assets/frontend/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/frontend/css/fontawesome.min.css" rel="stylesheet">
    <link href="/assets/frontend/css/crm-frontend.css" rel="stylesheet">
    <script src="/assets/frontend/js/header.js" defer></script>
    <script src="/assets/frontend/js/forms.js" defer></script>
    <script src="/assets/frontend/js/bootstrap-collapse.js" defer></script>
    
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        
        .layout-container {
            display: grid;
            grid-template-areas: 
                "header header header"
                "sidebar main right"
                "footer footer footer";
            grid-template-rows: 70px 1fr auto;
            grid-template-columns: 250px 1fr 0px;
            height: 100vh;
            transition: grid-template-columns 0.3s ease;
        }
        
        .layout-container.sidebar-closed {
            grid-template-columns: 0px 1fr 0px;
        }
        
        .header-section { grid-area: header; z-index: 1001; }
        .sidebar-section { grid-area: sidebar; z-index: 1000; overflow: hidden; }
        .main-section { grid-area: main; overflow-y: auto; padding: 20px; }
        .footer-section { grid-area: footer; background: #f8f9fa; border-top: 1px solid #dee2e6; min-height: 50px; }
        
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .profile-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            border-radius: 4px;
            z-index: 1002;
        }
        
        .profile-dropdown:hover .profile-dropdown-content {
            display: block;
        }
        
        .profile-dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .profile-dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        
        @media (max-width: 768px) {
            .layout-container {
                grid-template-columns: 0px 1fr 0px;
            }
            .sidebar-section {
                position: fixed;
                left: -250px;
                top: 70px;
                width: 250px;
                height: calc(100vh - 70px);
                transition: left 0.3s ease;
            }
            .sidebar-section.mobile-open {
                left: 0;
            }
        }
    </style>
</head>
<body>
<div class="layout-container" id="layoutContainer">
    <!-- Header -->
    <div class="header-section" style="background: #2563eb; color: white; display: flex; align-items: center; padding: 0 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <button style="background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 12px; border-radius: 4px; margin-right: 20px;"
                onclick="toggleSidebar()">☰</button>
        <div style="font-size: 1.5rem; font-weight: bold; margin-right: auto;">
            <i class="fas fa-chart-line"></i> ZeroAI CRM
        </div>
        <div style="display: flex; gap: 20px; margin-right: 20px;">
            <a href="/web/index.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">📊 Dashboard</a>
            <a href="/web/companies.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">🏢 Companies</a>
            <a href="/web/sales.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">💰 Sales</a>
            <a href="/web/projects.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">📋 Projects</a>
            <a href="/web/ai_workshop.php" style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none;">🤖 AI</a>
            <?php if ($isAdmin): ?><a href="/admin/dashboard.php" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none;">⚙️ Admin</a><?php endif; ?>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="profile-dropdown">
            <button style="background: #6366f1; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer;">
                👤 Profile
            </button>
            <div class="profile-dropdown-content">
                <div style="padding: 12px 16px; border-bottom: 1px solid #eee; font-weight: bold;">
                    Welcome, <?= htmlspecialchars($currentUser) ?>!
                </div>
                <a href="/web/profile.php">👤 My Profile</a>
                <a href="/web/settings.php">⚙️ Settings</a>
                <?php if ($isAdmin): ?>
                    <a href="/admin/dashboard.php">🔧 Admin Panel</a>
                <?php endif; ?>
                <div style="border-top: 1px solid #eee;"></div>
                <a href="/web/logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <!-- Dynamic Sidebar -->
    <div class="sidebar-section" id="sidebar" style="background: #1e293b; color: white; overflow-y: auto;">
        <div style="padding: 20px; border-bottom: 1px solid #334155;">
            <h6 style="margin: 0; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem;">
                <?php
                switch($currentContext) {
                    case 'projects': echo 'Project Menu'; break;
                    case 'companies': echo 'Company Menu'; break;
                    default: echo 'Main Menu'; break;
                }
                ?>
            </h6>
        </div>
        <div id="sidebar-content" style="padding: 20px;">
            <?php if ($currentContext === 'projects'): ?>
                <!-- Project Context Menu -->
                <div style="margin-bottom: 20px;">
                    <h6 style="color: #94a3b8; margin-bottom: 10px;">Project Navigation</h6>
                    <a href="/web/projects.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📋 All Projects</a>
                    <?php if ($contextId): ?>
                        <a href="/web/project_view.php?id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">📊 Project Overview</a>
                        <a href="/web/tasks.php?project_id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">✅ Tasks</a>
                        <a href="/web/bugs.php?project_id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">🐛 Bugs</a>
                        <a href="/web/features.php?project_id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">✨ Features</a>
                        <a href="/web/team.php?project_id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">👥 Team</a>
                        <a href="/web/releases.php?project_id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">🚀 Releases</a>
                        <a href="/web/documents.php?project_id=<?= htmlspecialchars($contextId) ?>" style="color: white; text-decoration: none; display: block; padding: 8px 0; padding-left: 20px;">📄 Documents</a>
                    <?php endif; ?>
                </div>
            <?php elseif ($currentContext === 'companies'): ?>
                <!-- Company Context Menu -->
                <div style="margin-bottom: 20px;">
                    <h6 style="color: #94a3b8; margin-bottom: 10px;">Company Navigation</h6>
                    <a href="/web/companies.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">🏢 All Companies</a>
                    <a href="/web/contacts.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">👥 Contacts</a>
                    <a href="/web/documents.php?context=companies" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📄 Documents</a>
                </div>
            <?php else: ?>
                <!-- Main Menu -->
                <div style="margin-bottom: 20px;">
                    <h6 style="color: #94a3b8; margin-bottom: 10px;">Navigation</h6>
                    <a href="/web/index.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📊 Dashboard</a>
                    <a href="/web/companies.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">🏢 Companies</a>
                    <a href="/web/contacts.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">👥 Contacts</a>
                    <a href="/web/projects.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📋 Projects</a>
                    <a href="/web/tasks.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">✅ Tasks</a>
                    <a href="/web/sales.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">💰 Sales</a>
                </div>
            <?php endif; ?>
            
            <div style="margin-bottom: 20px;">
                <h6 style="color: #94a3b8; margin-bottom: 10px;">Quick Actions</h6>
                <a href="/web/companies.php#add" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Company</a>
                <a href="/web/projects.php#add" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Project</a>
                <a href="/web/contacts.php#add" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Contact</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-section" id="mainContent">

<script>
function toggleSidebar() {
    const container = document.getElementById('layoutContainer');
    const sidebar = document.getElementById('sidebar');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
    } else {
        container.classList.toggle('sidebar-closed');
    }
}
</script>