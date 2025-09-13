<?php 
use ZeroAI\Core\System;
use ZeroAI\Models\User;
use ZeroAI\Models\Agent;
use ZeroAI\Models\Logs;
use ZeroAI\Services\UserService;
use ZeroAI\Services\AgentService;

$system = System::getInstance();
$userService = new UserService();
$agentService = new AgentService();
$logsModel = new Logs();

$pageTitle = 'Admin Dashboard - ZeroAI';
$currentPage = 'dashboard';

// Get system stats
$users = $userService->getAllUsers();
$agents = $agentService->getAllAgents();
$recentLogs = $logsModel->getRecentLogs('ai', 5);
$userStats = $userService->getUserStats();
$agentStats = $agentService->getAgentStats();

include __DIR__ . '/includes/header.php'; 
?>

<h1>System Overview</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" id="cpu">0%</div>
        <div class="stat-label">CPU Usage</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="memory">0%</div>
        <div class="stat-label">Memory Usage</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="disk">0</div>
        <div class="stat-label">Disk I/O</div>
    </div>
</div>

<div class="card">
    <h3>System Status</h3>
    <p>🟢 Database: Connected</p>
    <p>🟢 Portal: Active (OOP System)</p>
    <p>👤 User: <?= $_SESSION['admin_user'] ?></p>
    <p>👥 Total Users: <?= $userStats['total'] ?> (<?= $userStats['admin'] ?> admins)</p>
    <p>🤖 Total Agents: <?= $agentStats['total'] ?> (<?= $agentStats['active'] ?> active)</p>
    <p>💾 Memory: <?= ini_get('memory_limit') ?></p>
    <p>💿 PHP Version: <?= phpversion() ?></p>
</div>

<div class="card">
    <h3>Recent Activity</h3>
    <?php if (!empty($recentLogs)): ?>
        <?php foreach (array_slice($recentLogs, 0, 5) as $log): ?>
            <p style="font-size: 12px; color: #666; margin: 5px 0;"><?= htmlspecialchars($log) ?></p>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No recent activity</p>
    <?php endif; ?>
</div>

<script>
function updateStats() {
    fetch('/api/system_stats.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('cpu').textContent = data.cpu + '%';
            document.getElementById('memory').textContent = data.memory + '%';
            document.getElementById('disk').textContent = data.disk_reads + data.disk_writes;
        })
        .catch(() => {});
}
setInterval(updateStats, 2000);
updateStats();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>