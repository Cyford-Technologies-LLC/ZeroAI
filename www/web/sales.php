<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Sales - ZeroAI CRM';
$currentPage = 'sales';

include __DIR__ . '/includes/header.php';
?>
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="sidebar-group">
                <h3>Sales</h3>
                <a href="/web/leads.php">📋 Leads</a>
                <a href="/web/opportunities.php">💰 Opportunities</a>
                <a href="/web/quotes.php">📄 Quotes</a>
                <a href="/web/proposals.php">📝 Proposals</a>
            </div>
        </div>
        <div class="main-content">
            <div class="container">
                <div class="card">
                    <h3>Sales Dashboard</h3>
                    <p>Manage your sales pipeline, leads, opportunities, quotes, and proposals.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>