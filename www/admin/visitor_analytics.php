<?php 
session_start();
$pageTitle = 'Visitor Analytics - ZeroAI';
$currentPage = 'analytics';

require_once __DIR__ . '/includes/autoload.php';
use ZeroAI\Core\{DatabaseManager, VisitorTracker};

$db = DatabaseManager::getInstance();
$tracker = new VisitorTracker();

// Get analytics data
$stats = $tracker->getStats();
$topVisitors = $tracker->getTopVisitors(15);
$recentLogins = $tracker->getRecentLogins(25);
$failedLogins = $tracker->getFailedLogins(24);

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>📊 Visitor Analytics</h1>
    <div>
        <button onclick="refreshData()" class="btn btn-primary">🔄 Refresh</button>
        <button onclick="exportData()" class="btn btn-success">📥 Export</button>
    </div>
</div>

<!-- Stats Overview -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card" style="background: #007bff;">
        <div class="stat-value"><?= number_format($stats['total_visitors']) ?></div>
        <div class="stat-label">Total Visitors</div>
    </div>
    <div class="stat-card" style="background: #28a745;">
        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
        <div class="stat-label">Registered Users</div>
    </div>
    <div class="stat-card" style="background: #17a2b8;">
        <div class="stat-value"><?= number_format($stats['today_visits']) ?></div>
        <div class="stat-label">Today's Visits</div>
    </div>
    <div class="stat-card" style="background: #28a745;">
        <div class="stat-value"><?= number_format($stats['successful_logins_today']) ?></div>
        <div class="stat-label">Successful Logins Today</div>
    </div>
    <div class="stat-card" style="background: #dc3545;">
        <div class="stat-value"><?= number_format($stats['failed_logins_today']) ?></div>
        <div class="stat-label">Failed Logins Today</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
    <!-- Top Visitors -->
    <div class="card">
        <h3>🏆 Top Visitors</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">IP Address</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Username</th>
                        <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Visits</th>
                        <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topVisitors as $visitor): ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace;"><?= htmlspecialchars($visitor['ip_address']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">
                            <?= $visitor['username'] ? htmlspecialchars($visitor['username']) : '<em>Anonymous</em>' ?>
                        </td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <span style="background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                <?= number_format($visitor['visit_count']) ?>
                            </span>
                        </td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd; font-size: 12px;">
                            <?= date('M j, H:i', strtotime($visitor['last_seen'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Failed Login Attempts -->
    <div class="card">
        <h3>🚨 Failed Login Attempts (24h)</h3>
        <?php if (empty($failedLogins)): ?>
            <div style="text-align: center; padding: 20px; color: #28a745;">
                <i style="font-size: 2em;">✅</i>
                <p>No failed login attempts in the last 24 hours!</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8d7da;">
                            <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">IP Address</th>
                            <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Username</th>
                            <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Attempts</th>
                            <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Last Attempt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedLogins as $attempt): ?>
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace;"><?= htmlspecialchars($attempt['ip_address']) ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($attempt['username'] ?? 'Unknown') ?></td>
                            <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                                <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                    <?= $attempt['attempts'] ?>
                                </span>
                            </td>
                            <td style="padding: 8px; text-align: center; border: 1px solid #ddd; font-size: 12px;">
                                <?= date('M j, H:i', strtotime($attempt['last_attempt'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Login Activity -->
<div class="card">
    <h3>🔐 Recent Login Activity</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Time</th>
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">IP Address</th>
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Username</th>
                    <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Status</th>
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Failure Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogins as $login): ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">
                        <?= date('M j, H:i:s', strtotime($login['attempt_time'])) ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace;"><?= htmlspecialchars($login['ip_address']) ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($login['username'] ?? 'Unknown') ?></td>
                    <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                        <?php if ($login['success']): ?>
                            <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">✓ SUCCESS</span>
                        <?php else: ?>
                            <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">✗ FAILED</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">
                        <?= $login['failure_reason'] ? htmlspecialchars($login['failure_reason']) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function refreshData() {
    location.reload();
}

function exportData() {
    window.open('/admin/export_analytics.php', '_blank');
}

// Auto-refresh every 30 seconds
setInterval(refreshData, 30000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>