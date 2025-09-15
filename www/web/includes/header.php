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
$currentPage = 'companies';

// Get user's organization_id
$userOrgId = 1; // Default
try {
    require_once __DIR__ . '/../config/database.php';
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


// Use existing Company model
try {
    require_once __DIR__ . '/../src/Models/Company.php';
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


$pageTitle = $pageTitle ?? 'ZeroAI CRM';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/fontawesome.min.css" rel="stylesheet">
    <link href="/assets/css/crm-custom.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --light: #f8fafc;
            --dark: #0f172a;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--dark);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.15);
            padding: 0.75rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 6px;
            margin: 0 0.25rem;
            transition: all 0.2s ease;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
        }

        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        .table thead th {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border: none;
            font-weight: 600;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .alert {
            border: none;
            border-radius: 8px;
        }

        .alert-success {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            color: #065f46;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            color: #991b1b;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s ease;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>

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
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <button style="background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; padding: 8px;"
                    onclick="toggleSidebar()">☰
            </button>
            <div style="font-size: 1.5rem; font-weight: bold;">🏢 ZeroAI CRM</div>
        </div>
        <div style="display: flex; gap: 20px;">
            <a href="/web/index.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'crm_dashboard' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📊
                Dashboard</a>
            <a href="/web/companies.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'companies' ? 'background: rgba(255,255,255,0.2);' : '' ?>">🏢
                Companies</a>
            <a href="/web/contacts.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'contacts' ? 'background: rgba(255,255,255,0.2);' : '' ?>">👥
                Contacts</a>
            <a href="/web/sales.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'sales' ? 'background: rgba(255,255,255,0.2);' : '' ?>">💰
                Sales</a>
            <a href="/web/projects.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'projects' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📋
                Projects</a>
            <a href="/web/tasks.php"
               style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'tasks' ? 'background: rgba(255,255,255,0.2);' : '' ?>">✅
                Tasks</a>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: rgba(255,255,255,0.9);">👤 <?= htmlspecialchars($currentUser ?? 'User') ?></span>
            <?php if (isset($isAdmin) && $isAdmin): ?><a href="/admin/dashboard.php"
                                                         style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">⚙️
                Admin</a><?php endif; ?>
            <a href="/web/ai_workshop.php"
               style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🤖
                AI</a>
            <a href="/web/logout.php"
               style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🚪
                Logout</a>
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
        const currentPage = '<?= $currentPage ?? '' ?>';
        const content = document.getElementById('sidebar-content');

        if (!content) return;

        let links = '';

        if (currentPage === 'companies') {
            links = `
                <a href="/web/employees.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">👥 Employees</a>
                <a href="/web/locations.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📍 Locations</a>
                <a href="/web/social_media.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📱 Social Media</a>
            `;
        } else if (currentPage === 'projects') {
            links = `
                <a href="/web/tasks.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">✅ Tasks</a>
                <a href="/web/features.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">✨ Features</a>
                <a href="/web/bugs.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">🐛 Bugs</a>
            `;
        } else if (currentPage === 'sales') {
            links = `
                <a href="/web/leads.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📋 Leads</a>
                <a href="/web/opportunities.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">💰 Opportunities</a>
                <a href="/web/quotes.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📄 Quotes</a>
            `;
        } else {
            links = '<p style="color: #94a3b8; font-size: 0.9rem;">No sub-menu available for ' + currentPage + '</p>';
        }

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
