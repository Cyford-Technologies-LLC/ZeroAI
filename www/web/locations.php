<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Locations - ZeroAI CRM';
$currentPage = 'locations';
$companyId = $_GET['company_id'] ?? null;

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Get company info
$company = null;
if ($companyId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Company not found";
    }
}

include __DIR__ . '/includes/header.php';
?>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Locations<?= $company ? ' - ' . htmlspecialchars($company['name']) : '' ?></div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/projects.php">Projects</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser) ?>!</span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/ai_workshop.php" class="header-btn">🤖 AI Workshop</a>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="sidebar-group">
                <h3>CRM</h3>
                <a href="/web/companies.php">Companies</a>
                <?php if ($companyId): ?>
                    <a href="/web/employees.php?company_id=<?= $companyId ?>" style="padding-left: 40px;">👥 Employees</a>
                    <a href="/web/contacts.php?company_id=<?= $companyId ?>" style="padding-left: 40px;">📞 Contacts</a>
                    <a href="/web/locations.php?company_id=<?= $companyId ?>" class="active" style="padding-left: 40px;">📍 Locations</a>
                <?php else: ?>
                    <a href="/web/contacts.php">Contacts</a>
                <?php endif; ?>
                <a href="/web/projects.php">Projects</a>
            </div>
        </div>
        <div class="main-content">
            <div class="container">
                <?php if ($company): ?>
                    <div class="card">
                        <h3>Locations for <?= htmlspecialchars($company['name']) ?></h3>
                        <p>Location management functionality coming soon...</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3>No Company Selected</h3>
                        <p>Please select a company from the <a href="/web/companies.php">Companies</a> page to manage locations.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>