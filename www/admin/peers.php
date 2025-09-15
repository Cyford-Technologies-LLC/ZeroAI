<?php 
$pageTitle = 'Peer Resources - ZeroAI';
$currentPage = 'peers';
require_once __DIR__ . '/includes/autoload.php';

use ZeroAI\Core\OllamaService;

include __DIR__ . '/includes/header.php';

$ollama = new OllamaService();
$ollamaStatus = $ollama->getStatus();

function loadPeersConfig() {
    $configPath = '/app/config/peers.json';
    if (file_exists($configPath)) {
        $content = file_get_contents($configPath);
        return json_decode($content, true);
    }
    return ['peers' => []];
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function getTimeSince($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

$peersData = loadPeersConfig();
$peers = $peersData['peers'] ?? [];
?>

<h1>🌐 Peer Resources</h1>

<div class="card">
    <h3>🤖 Local Ollama Instance</h3>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $ollamaStatus['available'] ? '#28a745' : '#dc3545' ?>;"></span>
            <strong><?= $ollamaStatus['available'] ? 'Online' : 'Offline' ?></strong>
            <span style="color: #666;"><?= $ollamaStatus['url'] ?></span>
        </div>
        <span style="font-size: 0.9em; color: #666;">Last checked: <?= $ollamaStatus['last_checked'] ?></span>
    </div>
    
    <?php if ($ollamaStatus['available']): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div style="text-align: center; padding: 10px; background: #e8f5e8; border-radius: 6px;">
                <div style="font-size: 1.5em; font-weight: bold; color: #388e3c;"><?= $ollamaStatus['model_count'] ?></div>
                <div style="font-size: 0.9em; color: #666;">Models</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #e3f2fd; border-radius: 6px;">
                <div style="font-size: 1.2em; font-weight: bold; color: #1976d2;"><?= formatBytes($ollamaStatus['total_size']) ?></div>
                <div style="font-size: 0.9em; color: #666;">Total Size</div>
            </div>
        </div>
        
        <div>
            <h4>Available Models (<?= count($ollamaStatus['models']) ?>)</h4>
            <?php if (!empty($ollamaStatus['models'])): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">
                    <?php foreach ($ollamaStatus['models'] as $model): ?>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border-left: 3px solid #007cba;">
                            <div style="font-weight: bold; color: #007cba;"><?= htmlspecialchars($model['name']) ?></div>
                            <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                                Size: <?= formatBytes($model['size']) ?> | 
                                Modified: <?= date('M j, Y', strtotime($model['modified_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">⚠️ No models found. Run <code>ollama pull llama3.2:1b</code> to download a model.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;">❌ Ollama service is not available. Check if the container is running.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Peer Network Overview</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="text-align: center; padding: 15px; background: #e3f2fd; border-radius: 8px;">
            <h4 style="margin: 0; color: #1976d2;">Total Peers</h4>
            <p style="font-size: 2em; margin: 5px 0; font-weight: bold;"><?= count($peers) ?></p>
        </div>
        <div style="text-align: center; padding: 15px; background: #e8f5e8; border-radius: 8px;">
            <h4 style="margin: 0; color: #388e3c;">Available</h4>
            <p style="font-size: 2em; margin: 5px 0; font-weight: bold;"><?= count(array_filter($peers, function($p) { return $p['available']; })) ?></p>
        </div>
        <div style="text-align: center; padding: 15px; background: #fff3e0; border-radius: 8px;">
            <h4 style="margin: 0; color: #f57c00;">Total Models</h4>
            <p style="font-size: 2em; margin: 5px 0; font-weight: bold;"><?= array_sum(array_map(function($p) { return count($p['models'] ?? []); }, $peers)) ?></p>
        </div>
        <div style="text-align: center; padding: 15px; background: #fce4ec; border-radius: 8px;">
            <h4 style="margin: 0; color: #c2185b;">Total Memory</h4>
            <p style="font-size: 1.5em; margin: 5px 0; font-weight: bold;"><?= number_format(array_sum(array_map(function($p) { return $p['memory_gb'] ?? 0; }, $peers)), 1) ?>GB</p>
        </div>
    </div>
</div>

<?php if (empty($peers)): ?>
    <div class="card">
        <h3>No Peers Configured</h3>
        <p style="color: #856404; background: #fff3cd; padding: 15px; border-radius: 4px;">
            ⚠️ No peers found in <code>/app/config/peers.json</code>. 
            Configure peer connections to see distributed resources.
        </p>
    </div>
<?php else: ?>
    <?php foreach ($peers as $peer): ?>
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $peer['available'] ? '#28a745' : '#dc3545' ?>;"></span>
                        <?= htmlspecialchars($peer['name']) ?>
                    </h3>
                    <p style="margin: 5px 0; color: #666;">
                        <?= htmlspecialchars($peer['ip']) ?>:<?= $peer['port'] ?>
                        <span style="margin-left: 15px;">Last updated: <?= getTimeSince($peer['last_updated']) ?></span>
                    </p>
                </div>
                <div style="text-align: right;">
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.9em; background: <?= $peer['available'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $peer['available'] ? '#155724' : '#721c24' ?>;">
                        <?= $peer['available'] ? 'Online' : 'Offline' ?>
                    </span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div>
                    <strong>Load Average:</strong><br>
                    <span style="font-size: 1.2em; color: <?= $peer['load_avg'] > 80 ? '#dc3545' : ($peer['load_avg'] > 50 ? '#ffc107' : '#28a745') ?>;">
                        <?= number_format($peer['load_avg'], 1) ?>%
                    </span>
                </div>
                <div>
                    <strong>Memory:</strong><br>
                    <span style="font-size: 1.2em;"><?= number_format($peer['memory_gb'], 1) ?>GB</span>
                </div>
                <div>
                    <strong>CPU Cores:</strong><br>
                    <span style="font-size: 1.2em;"><?= $peer['cpu_cores'] ?></span>
                </div>
                <div>
                    <strong>GPU:</strong><br>
                    <span style="font-size: 1.2em; color: <?= $peer['gpu_available'] ? '#28a745' : '#6c757d' ?>;">
                        <?= $peer['gpu_available'] ? number_format($peer['gpu_memory_gb'], 1) . 'GB' : 'None' ?>
                    </span>
                </div>
            </div>
            
            <div>
                <h4>Available Models (<?= count($peer['models']) ?>)</h4>
                <?php if (!empty($peer['models'])): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($peer['models'] as $model): ?>
                            <span style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-size: 0.9em;">
                                <?= htmlspecialchars($model) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6c757d; font-style: italic;">No models available</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <h3>Quick Actions</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="refreshPage()" class="btn-primary">🔄 Refresh Status</button>
        <button onclick="testPeers()" class="btn-success">🧪 Test All Peers</button>
        <a href="/admin/localhost" class="btn-warning">🖥️ Local Host</a>
        <a href="/admin/monitoring" class="btn-primary">📊 Full Monitoring</a>
    </div>
</div>

<script>
function refreshPage() {
    location.reload();
}

function testPeers() {
    const peers = <?= json_encode($peers) ?>;
    let results = [];
    
    Promise.all(peers.map(peer => {
        return fetch(`http://${peer.ip}:${peer.port}/api/version`, {
            method: 'GET',
            timeout: 5000
        })
        .then(response => response.ok ? 'Online' : 'Error')
        .catch(() => 'Offline')
        .then(status => ({ name: peer.name, status }));
    }))
    .then(results => {
        const summary = results.map(r => `${r.name}: ${r.status}`).join('\n');
        alert('Peer Test Results:\n\n' + summary);
    })
    .catch(error => {
        alert('Error testing peers: ' + error.message);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>