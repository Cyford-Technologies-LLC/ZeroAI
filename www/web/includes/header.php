<?php
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
            background: rgba(255,255,255,0.1);
        }
    </style>

</head>
<body>
    <!-- Left Sidebar -->
    <div id="sidebar" style="position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; background: #1e293b; color: white; transition: left 0.3s ease; z-index: 1000; overflow-y: auto;">
        <div style="padding: 20px; border-bottom: 1px solid #334155;">
            <h6 style="margin: 0; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem;">Menu</h6>
        </div>
        <div id="sidebar-content" style="padding: 20px;">
            <!-- Dynamic content based on active page -->
        </div>
    </div>
    
    <!-- Overlay -->
    <div id="sidebar-overlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); display: none; z-index: 999;"></div>
    
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
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }
    });
    </script>
