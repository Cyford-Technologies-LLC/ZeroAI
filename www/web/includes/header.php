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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="/assets/frontend/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/frontend/css/fontawesome.min.css" rel="stylesheet">
    <link href="/assets/frontend/css/crm-frontend.css" rel="stylesheet">


</head>
<body>
<!-- Left Sidebar -->
<div id="sidebar"
     style="position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; background: #1e293b; color: white; transition: left 0.3s ease; z-index: 1000; overflow-y: auto;">
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
<div style="background: #2563eb; color: white; padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="width: 100%; display: flex; align-items: center; padding: 0 20px;">
        <button style="background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 12px; border-radius: 4px; transition: background 0.2s ease; margin-right: 20px;"
                onclick="toggleSidebar()" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='none'">☰
        </button>
        <div style="font-size: 1.5rem; font-weight: bold; margin-right: auto;">🏢 ZeroAI CRM</div>
        <div style="display: flex; gap: 20px; margin-right: 20px;">
            <a href="/web/index.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'crm_dashboard' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📊
                Dashboard</a>
            <a href="/web/companies.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'companies' ? 'background: rgba(255,255,255,0.2);' : '' ?>">🏢
                Companies</a>
            <a href="/web/sales.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'sales' ? 'background: rgba(255,255,255,0.2);' : '' ?>">💰
                Sales</a>
            <a href="/web/projects.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'projects' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📋
                Projects</a>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/web/ai_workshop.php"
               style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🤖
                AI</a>
            <?php if (isset($isAdmin) && $isAdmin): ?><a href="/admin/dashboard.php"
                                                         style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">⚙️
                Admin</a><?php endif; ?>
            <button style="background: #6366f1; color: white; padding: 6px 12px; border-radius: 4px; border: none; font-size: 0.9rem; cursor: pointer;"
                    onclick="toggleSidebar(); updateSidebarForProfile();">👤
                Profile</button>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        console.log('Toggle sidebar clicked');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (!sidebar || !overlay) {
            console.log('Sidebar elements not found');
            return;
        }

        const isOpen = sidebar.style.left === '0px';

        if (isOpen) {
            sidebar.style.left = '-250px';
            overlay.style.display = 'none';
        } else {
            sidebar.style.left = '0px';
            overlay.style.display = 'block';
            updateSidebarContent();
        }
    }

    function updateSidebarContent() {
        const currentPage = window.location.pathname;
        const content = document.getElementById('sidebar-content');

        if (!content) return;

        let links = '';

        if (currentPage.includes('/companies') || currentPage.includes('/contacts') || currentPage.includes('/employees') || currentPage.includes('/locations')) {
            links = `
                <a href="/web/contacts.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">👥 Contacts</a>
                <a href="/web/employees.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">👨‍💼 Employees</a>
                <a href="/web/locations.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📍 Locations</a>
            `;
        } else if (currentPage.includes('/projects') || currentPage.includes('/tasks') || currentPage.includes('/features') || currentPage.includes('/bugs')) {
            links = `
                <a href="/web/tasks.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">✅ Tasks</a>
                <a href="/web/features.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">✨ Features</a>
                <a href="/web/bugs.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">🐛 Bugs</a>
            `;
        } else if (currentPage.includes('/sales') || currentPage.includes('/leads') || currentPage.includes('/opportunities') || currentPage.includes('/quotes')) {
            links = `
                <a href="/web/leads.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📋 Leads</a>
                <a href="/web/opportunities.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">💰 Opportunities</a>
                <a href="/web/quotes.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📄 Quotes</a>
            `;
        } else {
            links = '<p style="color: #94a3b8; font-size: 0.9rem;">No sub-menu available</p>';
        }

        content.innerHTML = links;
    }

    function updateSidebarForProfile() {
        const content = document.getElementById('sidebar-content');
        if (!content) return;

        const links = `
            <div style="padding: 12px 0; border-bottom: 1px solid #334155; color: #e2e8f0;">
                <strong>👤 <?= htmlspecialchars($currentUser ?? 'User') ?></strong>
            </div>
            <a href="/web/profile.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">⚙️ Settings</a>
            <a href="/web/logout.php" style="display: block; color: #f87171; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">🚪 Logout</a>
        `;
        
        content.innerHTML = links;
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function () {
        const overlay = document.getElementById('sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }
    });
</script>
