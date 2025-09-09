<?php 
$pageTitle = 'Admin Dashboard - ZeroAI';
$currentPage = 'dashboard';
include __DIR__ . '/../includes/header.php'; 
?>

<h1>System Overview</h1>
    
<div class="card">
    <h3>System Status</h3>
    <p>🟢 Database: Connected</p>
    <p>🟢 Portal: Active</p>
    <p>👤 User: <?= $_SESSION['admin_user'] ?></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>