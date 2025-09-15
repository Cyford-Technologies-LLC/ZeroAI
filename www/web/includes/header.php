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
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        
        .layout-container {
            display: grid;
            grid-template-areas: 
                "header header header"
                "sidebar main right"
                "footer footer footer";
            grid-template-rows: 70px 1fr 60px;
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
        .right-section { grid-area: right; }
        .footer-section { grid-area: footer; background: #f8f9fa; border-top: 1px solid #dee2e6; }
        
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
    <!-- TOP SECTION -->
    <div class="header-section" style="background: #2563eb; color: white; display: flex; align-items: center; padding: 0 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <button style="background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 12px; border-radius: 4px; transition: background 0.2s ease; margin-right: 20px;"
                onclick="toggleSidebar()" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='none'">☰</button>
        <div style="font-size: 1.5rem; font-weight: bold; margin-right: auto;">🏢 ZeroAI CRM</div>
        <div style="display: flex; gap: 20px; margin-right: 20px;">
            <a href="/web/index.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">📊 Dashboard</a>
            <a href="/web/companies.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">🏢 Companies</a>
            <a href="/web/sales.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">💰 Sales</a>
            <a href="/web/projects.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">📋 Projects</a>
            <a href="/web/ai_workshop.php" style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none;">🤖 AI</a>
            <?php if (isset($isAdmin) && $isAdmin): ?><a href="/admin/dashboard.php" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none;">⚙️ Admin</a><?php endif; ?>
        </div>
        <button style="background: #6366f1; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer;" onclick="toggleSidebar(); updateSidebarForProfile();">👤 Profile</button>
    </div>

    <!-- LEFT SECTION -->
    <div class="sidebar-section" id="sidebar" style="background: #1e293b; color: white; overflow-y: auto;">
        <div style="padding: 20px; border-bottom: 1px solid #334155;">
            <h6 style="margin: 0; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem;">Menu</h6>
        </div>
        <div id="sidebar-content" style="padding: 20px;">
            <!-- Dynamic content -->
        </div>
    </div>

    <!-- MAIN SECTION -->
    <div class="main-section" id="mainContent">
        <?php ob_start(); ?>

    <!-- RIGHT SECTION -->
    <div class="right-section">
        <!-- Future right panel content -->
    </div>

    <!-- FOOTER SECTION -->
    <div class="footer-section" style="display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 0.9rem;">
        © 2024 ZeroAI CRM - Zero Cost, Zero Cloud, Zero Limits
    </div>
</div>

<!-- Mobile Overlay -->
<div id="sidebar-overlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); display: none; z-index: 999;"></div>