<?php 
$pageTitle = 'Local Host Resources - ZeroAI';
$currentPage = 'localhost';
include __DIR__ . '/includes/header.php';

// Get local system information
function getLocalSystemInfo() {
    $info = [
        'hostname' => gethostname(),
        'php_version' => phpversion(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'disk_free' => disk_free_space('.'),
        'disk_total' => disk_total_space('.'),
        'load_average' => sys_getloadavg(),
        'timestamp' => time()
    ];
    
    // Get Ollama models if available
    $info['ollama_models'] = getOllamaModels();
    $info['ollama_status'] = checkOllamaStatus();
    
    return $info;
}

function getOllamaModels() {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://ollama:11434/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return $data['models'] ?? [];
        }
    } catch (Exception $e) {
        // Ignore errors
    }
    return [];
}

function checkOllamaStatus() {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://ollama:11434/api/version');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    } catch (Exception $e) {
        return false;
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

$systemInfo = getLocalSystemInfo();
?>

<h1>🖥️ Local Host Resources</h1>

<div class="card">
    <h3>System Information</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <p><strong>Hostname:</strong> <?= htmlspecialchars($systemInfo['hostname']) ?></p>
            <p><strong>PHP Version:</strong> <?= htmlspecialchars($systemInfo['php_version']) ?></p>
            <p><strong>Memory Limit:</strong> <?= htmlspecialchars($systemInfo['memory_limit']) ?></p>
            <p><strong>Max Execution Time:</strong> <?= htmlspecialchars($systemInfo['max_execution_time']) ?>s</p>
        </div>
        <div>
            <p><strong>Disk Free:</strong> <?= formatBytes($systemInfo['disk_free']) ?></p>
            <p><strong>Disk Total:</strong> <?= formatBytes($systemInfo['disk_total']) ?></p>
            <p><strong>Load Average:</strong> <?= is_array($systemInfo['load_average']) ? implode(', ', array_map(function($x) { return number_format($x, 2); }, $systemInfo['load_average'])) : 'N/A' ?></p>
            <p><strong>Last Updated:</strong> <?= date('Y-m-d H:i:s', $systemInfo['timestamp']) ?></p>
        </div>
    </div>
</div>

<div class="card">
    <h3>Ollama Service Status</h3>
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
        <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $systemInfo['ollama_status'] ? '#28a745' : '#dc3545' ?>;"></span>
        <strong><?= $systemInfo['ollama_status'] ? 'Online' : 'Offline' ?></strong>
        <span style="color: #666;">(http://ollama:11434)</span>
    </div>
    
    <?php if ($systemInfo['ollama_status']): ?>
        <p style="color: #28a745;">✅ Ollama service is running and accessible</p>
    <?php else: ?>
        <p style="color: #dc3545;">❌ Ollama service is not accessible. Make sure it's running on port 11434.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Available Models</h3>
    <?php if (!empty($systemInfo['ollama_models'])): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
            <?php foreach ($systemInfo['ollama_models'] as $model): ?>
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #f9f9f9;">
                    <h4 style="margin: 0 0 10px 0; color: #007bff;"><?= htmlspecialchars($model['name']) ?></h4>
                    <p><strong>Size:</strong> <?= formatBytes($model['size']) ?></p>
                    <p><strong>Modified:</strong> <?= date('Y-m-d H:i:s', strtotime($model['modified_at'])) ?></p>
                    <?php if (isset($model['details']['family'])): ?>
                        <p><strong>Family:</strong> <?= htmlspecialchars($model['details']['family']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">
            ⚠️ No models found. <?= $systemInfo['ollama_status'] ? 'Run "ollama pull llama3.1:8b" to download a model.' : 'Start Ollama service first.' ?>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Quick Actions</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="refreshPage()" class="btn-primary">🔄 Refresh Status</button>
        <button onclick="testOllama()" class="btn-success">🧪 Test Ollama</button>
        <a href="/admin/monitoring" class="btn-warning">📊 Full Monitoring</a>
        <a href="/admin/peers" class="btn-primary">🌐 View Peers</a>
    </div>
</div>

<script>
function refreshPage() {
    location.reload();
}

function testOllama() {
    fetch('http://ollama:11434/api/version')
        .then(response => response.json())
        .then(data => {
            alert('✅ Ollama is working! Version: ' + data.version);
        })
        .catch(error => {
            alert('❌ Ollama test failed: ' + error.message);
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>