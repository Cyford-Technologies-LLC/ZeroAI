<?php

use ZeroAI\Models\Company;

session_start();
require_once __DIR__ . '/../../src/autoload.php';


if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Companies - ZeroAI CRM';
$currentPage = '/';

// Get user's organization_id
$userOrgId = 1; // Default
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


// Use an existing Company model
try {
    require_once __DIR__ . '/../../src/Models/Company.php';
    $companyModel = new Company();

    if ($isAdmin) {
        $companies = $companyModel->getAll();
    } else {
        $companies = $companyModel->findByTenant($userOrgId);
    }
} catch (Exception $e) {
    $companies = [];
    $error = "Database error: " . $e->getMessage();
}

// Get company data from existing companies array
$company = null;
$companyId = null;

if (!empty($companies)) {
    $company = $companies[0]; // Use first company
    $companyId = $company['id'];
}

$pageTitle = $pageTitle ?? 'ZeroAI CRM';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $pageTitle ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="ZeroAI CRM - Zero Cost, Zero Cloud, Zero Limits. Manage your business with our powerful CRM system.">
    <meta name="keywords" content="CRM, Customer Relationship Management, ZeroAI, Business Management, Projects, Companies, Contacts">
    <meta name="author" content="ZeroAI">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="ZeroAI CRM - Zero Cost, Zero Cloud, Zero Limits. Manage your business efficiently.">
    <meta property="og:image" content="/assets/frontend/images/zeroai-logo.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <meta property="twitter:title" content="<?= $pageTitle ?>">
    <meta property="twitter:description" content="ZeroAI CRM - Zero Cost, Zero Cloud, Zero Limits.">
    <meta property="twitter:image" content="/assets/frontend/images/zeroai-logo.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <link href="/assets/frontend/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/frontend/css/fontawesome.min.css" rel="stylesheet">
    <link href="/assets/frontend/css/crm-frontend.css" rel="stylesheet">
    <script src="/assets/frontend/js/header.js" defer></script>
    
    <style>
        @media (max-width: 768px) {
            .navbar-nav { flex-direction: column; width: 100%; }
            .navbar-nav .nav-link { padding: 0.5rem 0.75rem; }
            .mobile-menu { display: block !important; }
            .desktop-menu { display: none !important; }
            .logo-text { font-size: 1.2rem !important; }
        }
        
        @media (min-width: 769px) {
            .mobile-menu { display: none !important; }
            .desktop-menu { display: flex !important; }
        }
        
        .header-responsive {
            transition: all 0.3s ease;
        }
        
        .content-shift {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        
        .sidebar-open .header-responsive {
            margin-left: 250px;
        }
    </style>


</head>
<body>
<!-- Left Sidebar -->
<div id="sidebar"
     style="position: fixed; left: -250px; top: 70px; width: 250px; height: calc(100vh - 70px); background: #1e293b; color: white; transition: left 0.3s ease; z-index: 1000; overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.1);">
    <div style="padding: 20px; border-bottom: 1px solid #334155;">
        <h6 style="margin: 0; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem;">Menu</h6>
    </div>
    <div id="sidebar-content" style="padding: 20px;">
        <!-- Dynamic content based on active page -->
    </div>
</div>

<!-- Overlay -->
<div id="sidebar-overlay"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); display: none; z-index: 999;"></div>

<!-- Top Navigation -->
<div id="main-header" class="header-responsive" style="background: #2563eb; color: white; padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1001;">
    <div style="width: 100%; display: flex; align-items: center; padding: 0 20px;">
        <button style="background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 12px; border-radius: 4px; transition: background 0.2s ease; margin-right: 20px;"
                onclick="toggleSidebar()" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='none'">☰
        </button>
        <div class="logo-text" style="font-size: 1.5rem; font-weight: bold; margin-right: auto;">🏢 ZeroAI CRM</div>
        <div class="desktop-menu" style="display: flex; gap: 20px; margin-right: 20px;">
            <a href="/web/index.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; white-space: nowrap; <?= ($currentPage ?? '') === 'crm_dashboard' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📊 Dashboard</a>
            <a href="/web/companies.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; white-space: nowrap; <?= ($currentPage ?? '') === 'companies' ? 'background: rgba(255,255,255,0.2);' : '' ?>">🏢 Companies</a>
            <a href="/web/sales.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; white-space: nowrap; <?= ($currentPage ?? '') === 'sales' ? 'background: rgba(255,255,255,0.2);' : '' ?>">💰 Sales</a>
            <a href="/web/projects.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; white-space: nowrap; <?= ($currentPage ?? '') === 'projects' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📋 Projects</a>
            <a href="/web/ai_workshop.php"
               style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; margin-left: 20px; white-space: nowrap;">🤖 AI</a>
            <?php if (isset($isAdmin) && $isAdmin): ?><a href="/admin/dashboard.php"
                                                         style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; white-space: nowrap;">⚙️ Admin</a><?php endif; ?>
        </div>
        <button style="background: #6366f1; color: white; padding: 6px 12px; border-radius: 4px; border: none; font-size: 0.9rem; cursor: pointer;"
                onclick="toggleSidebar(); updateSidebarForProfile();">👤 Profile</button>
    </div>
</div>

<!-- Content starts below header -->
<div style="margin-top: 70px;">