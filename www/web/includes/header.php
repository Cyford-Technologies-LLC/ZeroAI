<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/app/www/logs/php_errors.log');

require_once __DIR__ . '/csp.php';
require_once __DIR__ . '/menu_system.php';

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

    // Check if organization_id column exists, create if not
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN organization_id VARCHAR(10) UNIQUE");
    } catch (Exception $e) {
        // Column already exists
    }

    // Update existing records with organization_id = 1 to unique IDs
    $stmt = $pdo->prepare("SELECT id, username, organization_id FROM users WHERE organization_id = '1' OR organization_id IS NULL");
    $stmt->execute();
    $usersToUpdate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usersToUpdate as $userToUpdate) {
        // Generate unique 10-digit organization ID
        do {
            $newOrgId = str_pad(mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE organization_id = ?");
            $checkStmt->execute([$newOrgId]);
        } while ($checkStmt->fetch());
        
        // Update user
        $updateStmt = $pdo->prepare("UPDATE users SET organization_id = ? WHERE id = ?");
        $updateStmt->execute([$newOrgId, $userToUpdate['id']]);
        
        // Update companies for this user
        $companyStmt = $pdo->prepare("UPDATE companies SET organization_id = ? WHERE organization_id = '1' AND (created_by = ? OR user_id = ?)");
        $companyStmt->execute([$newOrgId, $userToUpdate['username'], $userToUpdate['id']]);
        
        // Update contacts for this user
        $contactStmt = $pdo->prepare("UPDATE contacts SET organization_id = ? WHERE organization_id = '1' AND created_by = ?");
        $contactStmt->execute([$newOrgId, $userToUpdate['username']]);
        
        // Update projects for this user
        $projectStmt = $pdo->prepare("UPDATE projects SET organization_id = ? WHERE organization_id = '1' AND created_by = ?");
        $projectStmt->execute([$newOrgId, $userToUpdate['username']]);
        
        error_log("[DEBUG] Updated organization_id for user {$userToUpdate['username']} (ID: {$userToUpdate['id']}) from 1 to {$newOrgId}");
    }

    $stmt = $pdo->prepare("SELECT organization_id FROM users WHERE username = ?");
    $stmt->execute([$currentUser]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userOrgId = $user['organization_id'] ?? 1;
    }
    
    // Initialize menu system
    $menuSystem = new MenuSystem($pdo);
} catch (Exception $e) {
    // Use default
}

// Get companies from tenant database
try {
    require_once __DIR__ . '/../../src/Services/CRMHelper.php';
    $crmHelper = new \ZeroAI\Services\CRMHelper($userOrgId);
    $companies = $crmHelper->getCompanies();
} catch (Exception $e) {
    error_log("Header: Failed to load companies from tenant DB: " . $e->getMessage());
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
} elseif (strpos($_SERVER['REQUEST_URI'], '/companies.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/contacts.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/locations.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/employees.php') !== false) {
    $currentContext = 'companies';
    $contextId = $_GET['company_id'] ?? null;
} elseif (strpos($_SERVER['REQUEST_URI'], '/sales.php') !== false) {
    $currentContext = 'sales';
    $contextId = null;
} elseif (strpos($_SERVER['REQUEST_URI'], '/marketing.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/campaigns.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/email_marketing.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/analytics.php') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/lead_generation.php') !== false) {
    $currentContext = 'marketing';
    $contextId = null;
} elseif (strpos($_SERVER['REQUEST_URI'], '/ai_') !== false) {
    $currentContext = 'ai';
    $contextId = null;
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
    <link href="/web/includes/mobile-fixes.css" rel="stylesheet">
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
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #0056b3;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            border-radius: 4px;
            z-index: 1002;
            top: 100%;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-content a {
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            display: block;
        }
        
        .dropdown-content a:hover {
            background-color: rgba(255,255,255,0.1);
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
                grid-template-rows: auto 1fr auto;
            }
            .header-section {
                padding: 0 10px;
            }
            .header-section > div:first-child {
                font-size: 1.2rem;
            }
            .header-section > div:nth-child(2) {
                display: none;
            }
            .sidebar-section {
                position: fixed;
                left: -250px;
                top: 70px;
                width: 250px;
                height: calc(100vh - 70px);
                transition: left 0.3s ease;
                z-index: 1050;
            }
            .sidebar-section.mobile-open {
                left: 0;
            }
            .main-section {
                padding: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .header-section {
                padding: 0 5px;
            }
            .header-section > div:first-child {
                font-size: 1rem;
            }
            .main-section {
                padding: 5px;
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
            <img src="/assets/frontend/images/icons/logo.svg" width="24" height="24" style="margin-right: 8px;"> ZeroAI CRM
            <span style="font-size: 0.8rem; color: #94a3b8; margin-left: 10px;">Org: <?= htmlspecialchars($userOrgId) ?></span>
        </div>
        <div style="display: flex; gap: 20px; margin-right: 20px;">
            <?= isset($menuSystem) ? $menuSystem->renderHeaderMenu($currentPage) : '' ?>
            <div class="dropdown">
                <a href="#" style="background: <?= in_array($currentPage, ['integrations', 'integration_marketplace']) ? '#0056b3' : 'rgba(255,255,255,0.1)' ?>; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none;">🔌 Integrations</a>
                <div class="dropdown-content">
                    <a href="/web/integrations.php">My Integrations</a>
                    <a href="/web/integration_marketplace.php">Marketplace</a>
                </div>
            </div>
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
                    case 'sales': echo 'Sales Menu'; break;
                    case 'marketing': echo 'Marketing Menu'; break;
                    case 'ai': echo 'AI Menu'; break;
                    default: echo 'Main Menu'; break;
                }
                ?>
            </h6>
        </div>
        <div id="sidebar-content" style="padding: 20px;">
            <?= isset($menuSystem) ? $menuSystem->renderSidebarMenu($currentContext, $contextId) : '' ?>
            
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
        
        // Add/remove overlay
        let overlay = document.getElementById('mobileOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'mobileOverlay';
            overlay.className = 'mobile-overlay';
            overlay.onclick = () => toggleSidebar();
            document.body.appendChild(overlay);
        }
        overlay.classList.toggle('show');
    } else {
        container.classList.toggle('sidebar-closed');
    }
}
</script>