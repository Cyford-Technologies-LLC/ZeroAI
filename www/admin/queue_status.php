<?php
require_once 'includes/autoload.php';

$pageTitle = 'Queue Status - ZeroAI';
$currentPage = 'queue';
include __DIR__ . '/includes/header.php';

$queue = \ZeroAI\Core\QueueManager::getInstance();
$queueSize = $queue->size();
?>

<h1>📋 Queue Status</h1>

<div class="card">
    <h3>Queue Statistics</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="padding: 15px; background: #007bff; color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold;"><?= $queueSize ?></div>
            <div>Pending Jobs</div>
        </div>
        <div style="padding: 15px; background: #28a745; color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold;"><?= file_exists('/app/logs/queue.log') ? count(file('/app/logs/queue.log')) : 0 ?></div>
            <div>Total Processed</div>
        </div>
        <div style="padding: 15px; background: #17a2b8; color: white; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold;"><?= date('H:i:s') ?></div>
            <div>Current Time</div>
        </div>
    </div>
</div>

<div class="card">
    <h3>Queue Actions</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="clearQueue()" class="btn-danger">Clear Queue</button>
        <button onclick="testQueue()" class="btn-success">Test Queue</button>
        <button onclick="location.reload()" class="btn-secondary">Refresh</button>
    </div>
</div>

<div class="card">
    <h3>Recent Queue Log</h3>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; max-height: 300px; overflow-y: auto;">
        <?php
        $logFile = '/app/logs/queue.log';
        if (file_exists($logFile)) {
            $lines = array_slice(file($logFile), -20);
            foreach ($lines as $line) {
                echo htmlspecialchars($line) . "<br>";
            }
        } else {
            echo "No log file found";
        }
        ?>
    </div>
</div>

<script>
function clearQueue() {
    if (confirm('Clear all pending queue jobs?')) {
        fetch('/admin/queue_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'clear'})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to clear queue');
            }
        });
    }
}

function testQueue() {
    fetch('/admin/queue_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'test'})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Test job added to queue');
            location.reload();
        } else {
            alert('Failed to add test job');
        }
    });
}

// Auto-refresh every 30 seconds
setInterval(() => location.reload(), 30000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


