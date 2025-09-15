<?php 
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

require_once 'includes/autoload.php';

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// Simple Ollama status check
function getOllamaStatus() {
    $url = 'http://ollama:11434';
    try {
        $context = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents($url . '/api/tags', false, $context);
        if ($response === false) {
            return ['available' => false, 'url' => $url, 'models' => [], 'model_count' => 0, 'total_size' => 0];
        }
        $data = json_decode($response, true);
        $models = $data['models'] ?? [];
        return [
            'available' => true,
            'url' => $url,
            'models' => $models,
            'model_count' => count($models),
            'total_size' => array_sum(array_column($models, 'size'))
        ];
    } catch (Exception $e) {
        return ['available' => false, 'url' => $url, 'models' => [], 'model_count' => 0, 'total_size' => 0];
    }
}

$ollamaStatus = getOllamaStatus();

try {
    if (class_exists('ZeroAI\Core\System')) {
        $systemInfo = ZeroAI\Core\System::getSystemInfo();
    } else {
        $systemInfo = [];
    }
    
    if (class_exists('ZeroAI\Core\DatabaseManager')) {
        $db = ZeroAI\Core\DatabaseManager::getInstance();
        $userResult = $db->select('users');
        $userStats = ['total' => is_array($userResult) ? count($userResult) : 0, 'admin' => 1];
    } else {
        $userStats = ['total' => 0, 'admin' => 1];
    }
    
    $agentStats = ['total' => 0, 'active' => 0];
    $recentLogs = [];
    
} catch (Exception $e) {
    $userStats = ['total' => 0, 'admin' => 1];
    $agentStats = ['total' => 0, 'active' => 0];
    $recentLogs = [];
}

$pageTitle = 'Admin Dashboard - ZeroAI';
$currentPage = 'dashboard';

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
    <h3>🤖 Local Ollama (Fallback Only)</h3>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $ollamaStatus['available'] ? '#ffc107' : '#dc3545' ?>;"></span>
            <strong><?= $ollamaStatus['available'] ? 'Standby' : 'Offline' ?></strong>
            <span style="color: #666;"><?= $ollamaStatus['url'] ?></span>
            <span style="background: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 12px; font-size: 11px;">BACKUP</span>
        </div>
        <div style="font-size: 0.9em; color: #666;">
            <?= $ollamaStatus['model_count'] ?> models | <?= formatBytes($ollamaStatus['total_size']) ?>
        </div>
    </div>
    <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin-bottom: 10px; font-size: 0.9em;">
        ⚠️ <strong>Note:</strong> Dynamic router uses most powerful peers first. Local Ollama only used as emergency fallback.
    </div>
    <?php if ($ollamaStatus['available'] && !empty($ollamaStatus['models'])): ?>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach (array_slice($ollamaStatus['models'], 0, 6) as $model): ?>
                <span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;">
                    <?= htmlspecialchars($model['name']) ?>
                </span>
            <?php endforeach; ?>
            <?php if (count($ollamaStatus['models']) > 6): ?>
                <span style="color: #666; font-size: 0.85em;">+<?= count($ollamaStatus['models']) - 6 ?> more</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3>🌐 Peer Network Status</h3>
        <label style="font-size: 12px;">
            <input type="checkbox" id="enable-peer-monitoring" checked onchange="togglePeerMonitoring()"> Enable Monitoring
        </label>
    </div>
    <div id="peers-loading">Loading peer information...</div>
    <div id="peers-disabled" style="display: none; color: #666; font-style: italic;">Peer monitoring disabled for better performance</div>
    <div id="peers-table" style="display: none;">
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Peer Name</th>
                    <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Address</th>
                    <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Status</th>
                    <th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Models</th>
                    <th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Memory</th>
                    <th style="padding: 8px; text-align: right; border: 1px solid #ddd;">GPU</th>
                    <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Last Check</th>
                </tr>
            </thead>
            <tbody id="peers-tbody">
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>System Status</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">🟢 <strong>Database:</strong> Connected</div>
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">🟢 <strong>Portal:</strong> Active (OOP System)</div>
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">👤 <strong>User:</strong> <?= htmlspecialchars($_SESSION['admin_user']) ?></div>
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">👥 <strong>Total Users:</strong> <?= $userStats['total'] ?> (<?= $userStats['admin'] ?> admins)</div>
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">🤖 <strong>Total Agents:</strong> <?= $agentStats['total'] ?> (<?= $agentStats['active'] ?> active)</div>
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">💾 <strong>Memory:</strong> <?= ini_get('memory_limit') ?></div>
        <div style="padding: 8px; background: #f8f9fa; border-radius: 4px;">💿 <strong>PHP Version:</strong> <?= phpversion() ?></div>
    </div>
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

let peerMonitoringEnabled = true;
let peerUpdateInterval;

function togglePeerMonitoring() {
    peerMonitoringEnabled = document.getElementById('enable-peer-monitoring').checked;
    
    if (peerMonitoringEnabled) {
        document.getElementById('peers-disabled').style.display = 'none';
        document.getElementById('peers-loading').style.display = 'block';
        updatePeerStats();
        peerUpdateInterval = setInterval(updatePeerStats, 30000);
    } else {
        clearInterval(peerUpdateInterval);
        document.getElementById('peers-loading').style.display = 'none';
        document.getElementById('peers-table').style.display = 'none';
        document.getElementById('peers-disabled').style.display = 'block';
    }
}

function updatePeerStats() {
    if (!peerMonitoringEnabled) return;
    
    fetch('/admin/peers_api.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('peers-tbody');
                let html = '';
                
                data.peers.forEach(peer => {
                    const statusColor = peer.status === 'online' ? '#4caf50' : '#f44336';
                    const statusIcon = peer.status === 'online' ? '🟢' : '🔴';
                    const gpuText = peer.gpu_available ? `🟢 ${peer.gpu_memory_gb}GB` : '🔴 No GPU';
                    
                    html += `
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">${peer.name}</td>
                            <td style="padding: 8px; border: 1px solid #ddd;">${peer.ip}:${peer.port}</td>
                            <td style="padding: 8px; text-align: center; border: 1px solid #ddd; color: ${statusColor};">
                                ${statusIcon} ${peer.status.toUpperCase()}
                            </td>
                            <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${peer.models.length}</td>
                            <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${peer.memory_gb}GB</td>
                            <td style="padding: 8px; text-align: right; border: 1px solid #ddd;">${gpuText}</td>
                            <td style="padding: 8px; text-align: center; border: 1px solid #ddd; font-size: 11px;">${peer.last_check || 'Never'}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
                document.getElementById('peers-loading').style.display = 'none';
                document.getElementById('peers-table').style.display = 'block';
            }
        })
        .catch(() => {
            document.getElementById('peers-loading').textContent = 'Failed to load peer information';
        });
}

setInterval(updateStats, 5000);
setInterval(updateTokenStats, 60000);
updateStats();
updateTokenStats();

// Initialize peer monitoring
togglePeerMonitoring();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


