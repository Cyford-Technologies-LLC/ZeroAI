<?php 
require_once 'includes/autoload.php';

use ZeroAI\Core\System;
use ZeroAI\Core\DatabaseManager;

$system = System::getInstance();
$db = new DatabaseManager();

$pageTitle = 'Admin Dashboard - ZeroAI';
$currentPage = 'dashboard';

// Get basic stats
$userResult = $db->executeSQL("SELECT COUNT(*) as total FROM users");
$userStats = ['total' => $userResult[0]['data'][0]['total'] ?? 0, 'admin' => 1];
$agentStats = ['total' => 0, 'active' => 0];
$recentLogs = [];

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
    <h3>💰 Claude Token Usage & Costs</h3>
    <div id="token-stats-loading">Loading token statistics...</div>
    <div id="token-stats" style="display: none;">
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Period</th>
                    <th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Tokens</th>
                    <th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Cost (USD)</th>
                    <th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Requests</th>
                </tr>
            </thead>
            <tbody id="token-stats-body">
            </tbody>
        </table>
        
        <h4 style="margin-top: 20px;">📊 Usage by Model (Today)</h4>
        <div id="model-stats" style="font-size: 12px;"></div>
    </div>
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
    fetch('/admin/system_stats.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cpu').textContent = (data.stats.cpu_usage * 100).toFixed(1) + '%';
                document.getElementById('memory').textContent = (data.stats.memory_usage / 1024 / 1024).toFixed(0) + 'MB';
                document.getElementById('disk').textContent = (data.stats.disk_usage / 1024 / 1024 / 1024).toFixed(1) + 'GB';
            }
        })
        .catch(() => {});
}

function updateTokenStats() {
    fetch('/admin/token_usage_api.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const stats = data.stats;
                const tbody = document.getElementById('token-stats-body');
                
                tbody.innerHTML = `
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">Last Hour</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.hour.total_tokens.toLocaleString()}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">$${stats.hour.total_cost.toFixed(4)}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.hour.total_requests}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">Today</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.day.total_tokens.toLocaleString()}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">$${stats.day.total_cost.toFixed(4)}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.day.total_requests}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">This Week</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.week.total_tokens.toLocaleString()}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">$${stats.week.total_cost.toFixed(2)}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.week.total_requests}</td>
                    </tr>
                    <tr style="background: #f9f9f9; font-weight: bold;">
                        <td style="padding: 8px; border: 1px solid #ddd;">All Time</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.total.total_tokens.toLocaleString()}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">$${stats.total.total_cost.toFixed(2)}</td>
                        <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${stats.total.total_requests}</td>
                    </tr>
                `;
                
                // Show model breakdown for today
                const modelStats = document.getElementById('model-stats');
                let modelHtml = '';
                stats.day.models.forEach(model => {
                    modelHtml += `<div style="margin: 5px 0; padding: 5px; background: #f8f9fa; border-radius: 4px;">
                        <strong>${model.model}</strong>: ${model.total_tokens.toLocaleString()} tokens ($${model.cost_usd.toFixed(4)}) - ${model.requests} requests
                    </div>`;
                });
                modelStats.innerHTML = modelHtml || '<p>No usage today</p>';
                
                document.getElementById('token-stats-loading').style.display = 'none';
                document.getElementById('token-stats').style.display = 'block';
            }
        })
        .catch(() => {
            document.getElementById('token-stats-loading').textContent = 'Failed to load token statistics';
        });
}

setInterval(updateStats, 2000);
setInterval(updateTokenStats, 30000);
updateStats();
updateTokenStats();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>