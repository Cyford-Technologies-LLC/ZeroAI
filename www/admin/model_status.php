<?php
require_once 'includes/autoload.php';

use ZeroAI\Core\PeerManager;

$pageTitle = 'Model Installation Status - ZeroAI';
$currentPage = 'model_status';

$peerManager = PeerManager::getInstance();

// Get all log files
$logDir = __DIR__ . '/../../logs/';
$logFiles = [];

if (is_dir($logDir)) {
    $files = glob($logDir . 'model_install_*.log');
    foreach ($files as $file) {
        $jobId = basename($file, '.log');
        $jobId = str_replace('model_install_', '', $jobId);
        $status = $peerManager->getInstallationStatus($jobId);
        $logFiles[] = [
            'job_id' => $jobId,
            'file' => $file,
            'status' => $status,
            'modified' => filemtime($file)
        ];
    }
    
    // Sort by modification time (newest first)
    usort($logFiles, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

include __DIR__ . '/includes/header.php';
?>

<h1>Model Installation Status</h1>

<div class="card mb-4">
    <div class="card-header">
        <h5>Active & Recent Installations</h5>
    </div>
    <div class="card-body">
        <?php if (empty($logFiles)): ?>
            <p class="text-muted">No model installations found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logFiles as $logFile): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($logFile['job_id']) ?></code></td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    if ($logFile['status']['status'] === 'completed') $statusClass = 'success';
                                    elseif ($logFile['status']['status'] === 'error') $statusClass = 'danger';
                                    elseif ($logFile['status']['status'] === 'running') $statusClass = 'primary';
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($logFile['status']['status']) ?></span>
                                </td>
                                <td><?= date('Y-m-d H:i:s', $logFile['modified']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewLog('<?= $logFile['job_id'] ?>')">View Log</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Viewer Modal -->
<div class="modal fade" id="logModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Installation Log</h5>
                <button type="button" class="close" data-dismiss="modal" onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body">
                <div id="logContent" style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; font-size: 12px;">
                    Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button type="button" class="btn btn-primary" onclick="refreshLog()">Refresh</button>
            </div>
        </div>
    </div>
</div>

<script>
function closeModal() {
    const modal = document.getElementById('logModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.body.style.overflow = '';
    currentJobId = null;
}

// Close modal when clicking outside
document.getElementById('logModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<script>
let currentJobId = null;
let logModal = null;

// Initialize modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('logModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        logModal = new bootstrap.Modal(modalElement);
    } else {
        // Fallback for older Bootstrap or jQuery
        logModal = {
            show: function() {
                $('#logModal').modal('show');
            },
            hide: function() {
                $('#logModal').modal('hide');
            }
        };
    }
});

function viewLog(jobId) {
    currentJobId = jobId;
    document.getElementById('logContent').textContent = 'Loading...';
    
    // Show modal using direct DOM manipulation
    const modal = document.getElementById('logModal');
    modal.style.display = 'block';
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    refreshLog();
}

function refreshLog() {
    if (!currentJobId) return;
    
    fetch(`model_install_api.php?action=get_status&job_id=${currentJobId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('logContent').textContent = data.status.log || 'No log content available';
            } else {
                document.getElementById('logContent').textContent = 'Error: ' + data.error;
            }
        })
        .catch(error => {
            document.getElementById('logContent').textContent = 'Error loading log: ' + error.message;
        });
}

// Auto-refresh every 5 seconds for running jobs
setInterval(() => {
    if (currentJobId) {
        refreshLog();
    }
}, 5000);
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal.show {
    display: block !important;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 10px;
    pointer-events: none;
}

.modal-lg {
    max-width: 800px;
    margin: 30px auto;
}

.modal-content {
    position: relative;
    background-color: #fff;
    border: 1px solid rgba(0,0,0,.2);
    border-radius: 6px;
    box-shadow: 0 3px 9px rgba(0,0,0,.5);
    pointer-events: auto;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 15px;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 15px;
    border-top: 1px solid #dee2e6;
    gap: 10px;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>