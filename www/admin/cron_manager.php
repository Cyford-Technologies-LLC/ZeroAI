<?php 
$pageTitle = 'Cron Manager - ZeroAI';
$currentPage = 'cron_manager';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../bootstrap.php';

use ZeroAI\Core\CronManager;

$cronManager = new CronManager();

if ($_POST) {
    if (isset($_POST['add_job'])) {
        $cronManager->addJob($_POST['name'], $_POST['command'], $_POST['schedule']);
    } elseif (isset($_POST['toggle_job'])) {
        $cronManager->toggleJob($_POST['job_id'], $_POST['enabled']);
    } elseif (isset($_POST['delete_job'])) {
        $cronManager->deleteJob($_POST['job_id']);
    }
}

$jobs = $cronManager->getJobs();
?>

<h1>🕐 Cron Job Manager</h1>

<div class="card">
    <h3>Add New Cron Job</h3>
    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 2fr 1fr auto; gap: 10px; align-items: end;">
            <div>
                <label>Name:</label>
                <input type="text" name="name" required>
            </div>
            <div>
                <label>Command:</label>
                <input type="text" name="command" required placeholder="python3 /app/src/agents/peer_monitor_agent.py">
            </div>
            <div>
                <label>Schedule:</label>
                <select name="schedule">
                    <option value="*/5 * * * *">Every 5 minutes</option>
                    <option value="0 * * * *">Every hour</option>
                    <option value="0 0 * * *">Daily</option>
                </select>
            </div>
            <button type="submit" name="add_job" class="btn-success">Add Job</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Scheduled Jobs</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 8px; border: 1px solid #ddd;">Name</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Command</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Schedule</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Last Run</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Next Run</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Status</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job): ?>
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($job['name']) ?></td>
                <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace; font-size: 11px;"><?= htmlspecialchars($job['command']) ?></td>
                <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($job['schedule']) ?></td>
                <td style="padding: 8px; border: 1px solid #ddd;"><?= $job['last_run'] ?: 'Never' ?></td>
                <td style="padding: 8px; border: 1px solid #ddd;"><?= $job['next_run'] ?></td>
                <td style="padding: 8px; border: 1px solid #ddd;">
                    <?= $job['enabled'] ? '🟢 Enabled' : '🔴 Disabled' ?>
                </td>
                <td style="padding: 8px; border: 1px solid #ddd;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                        <input type="hidden" name="enabled" value="<?= $job['enabled'] ? 0 : 1 ?>">
                        <button type="submit" name="toggle_job" class="btn-warning" style="padding: 2px 6px; font-size: 10px;">
                            <?= $job['enabled'] ? 'Disable' : 'Enable' ?>
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                        <button type="submit" name="delete_job" class="btn-danger" style="padding: 2px 6px; font-size: 10px;" onclick="return confirm('Delete this job?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>📋 Recommended Jobs</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Peer Monitor</strong><br>
            <code>python3 /app/src/agents/peer_monitor_agent.py</code><br>
            <small>Monitors peer status in background</small>
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Database Cleanup</strong><br>
            <code>python3 /app/scripts/cleanup_old_logs.py</code><br>
            <small>Removes old log entries</small>
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>System Health Check</strong><br>
            <code>python3 /app/scripts/health_check.py</code><br>
            <small>Monitors system resources</small>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


