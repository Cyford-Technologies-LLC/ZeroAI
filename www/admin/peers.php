<?php 
require_once 'includes/autoload.php';

$pageTitle = 'Peer Management - ZeroAI';
$currentPage = 'peers';

use ZeroAI\Core\PeerManager;

$peerManager = PeerManager::getInstance();

$message = '';
$messageType = 'success';

if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'add_peer':
                $name = trim($_POST['peer_name']);
                $ip = trim($_POST['peer_ip']);
                $port = (int)($_POST['peer_port'] ?? 8080);
                
                if (empty($name) || empty($ip)) {
                    throw new Exception('Name and IP are required');
                }
                
                $peerManager->addPeer($name, $ip, $port);
                $message = "Peer '{$name}' added successfully!";
                break;
                
            case 'delete_peer':
                $ip = $_POST['peer_ip'];
                $peerManager->deletePeer($ip);
                $message = "Peer deleted successfully!";
                break;
                
            case 'refresh_peers':
                $peerManager->refreshPeers();
                $message = "Peer status refreshed!";
                break;
                
            case 'test_peer':
                $ip = $_POST['peer_ip'];
                $model = $_POST['test_model'] ?? 'llama3.2:1b';
                $result = $peerManager->testPeer($ip, $model);
                $message = $result ? "Peer test successful!" : "Peer test failed!";
                $messageType = $result ? 'success' : 'error';
                break;
                
            case 'install_model':
                $ip = $_POST['peer_ip'];
                $model = $_POST['model_name'];
                $jobId = $peerManager->startModelInstallation($ip, $model);
                $message = "Model '{$model}' installation started! Job ID: {$jobId}";
                $messageType = 'success';
                break;
                
            case 'remove_model':
                $ip = $_POST['peer_ip'];
                $model = $_POST['model_name'];
                $result = $peerManager->removeModel($ip, $model);
                $message = $result ? "Model '{$model}' removed successfully!" : "Model removal failed!";
                $messageType = $result ? 'success' : 'error';
                break;
                
            case 'save_model_rules':
                $rules = [
                    'all_peers' => $_POST['all_peers'] ?? [],
                    'memory_low' => $_POST['memory_low'] ?? [],
                    'memory_medium' => $_POST['memory_medium'] ?? [],
                    'memory_high' => $_POST['memory_high'] ?? [],
                    'gpu_low' => $_POST['gpu_low'] ?? [],
                    'gpu_high' => $_POST['gpu_high'] ?? []
                ];
                $peerManager->saveModelRules($rules);
                $message = "Model auto-install rules saved successfully!";
                break;
                
            case 'apply_auto_install':
                try {
                    $jobs = $peerManager->applyAutoInstallRules();
                    $message = "Started " . count($jobs) . " model installations based on auto-install rules! <a href='model_status.php' class='btn btn-sm btn-info ms-2'>📊 View Progress</a>";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Failed to apply auto-install rules: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'export_models':
                $exportData = $peerManager->exportModels();
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="zeroai-models-' . date('Y-m-d-H-i-s') . '.json"');
                echo $exportData;
                exit;
                
            case 'import_models':
                if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('No file uploaded or upload error');
                }
                $jsonData = file_get_contents($_FILES['import_file']['tmp_name']);
                $peerManager->importModels($jsonData);
                $message = "Models imported successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get peers
$peers = $peerManager->getPeers();

include __DIR__ . '/includes/header.php';
?>

<h1>Peer Network Management</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>Add New Peer</h3>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="refresh_peers">
                <button type="submit" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    🔄 Refresh Status
                </button>
            </form>
        </div>
        <div class="card-body">
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="add_peer">
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" name="peer_name" class="form-control" placeholder="Peer Name" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="peer_ip" class="form-control" placeholder="IP Address" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="peer_port" class="form-control" placeholder="Port" value="8080">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" style="background: #28a745; color: white; border: 1px solid #28a745; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                            + Add Peer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h3>Connected Peers</h3>
        </div>
        <div class="card-body">
            <?php if (empty($peers)): ?>
                <p class="text-muted">No peers configured. Add a peer above to get started.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>IP:Port</th>
                                <th>Health URL</th>
                                <th>Status</th>
                                <th>GPU</th>
                                <th>Memory</th>
                                <th>Models</th>
                                <th>Last Check</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peers as $peer): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($peer['name']) ?>
                                        <?php if (isset($peer['is_local']) && $peer['is_local']): ?>
                                            <span class="badge bg-primary" style="font-size: 10px;">LOCAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($peer['ip']) ?>:<?= $peer['port'] ?></td>
                                    <td><a href="http://<?= $peer['ip'] ?>:<?= $peer['port'] ?>/health" target="_blank">http://<?= $peer['ip'] ?>:<?= $peer['port'] ?>/health</a></td>
                                    <td>
                                        <span class="badge bg-<?= $peer['status'] === 'online' ? 'success' : 'secondary' ?>">
                                            <?= $peer['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($peer['gpu_available']): ?>
                                            <i class="fas fa-check text-success"></i> <?= $peer['gpu_memory_gb'] ?>GB
                                        <?php else: ?>
                                            <i class="fas fa-times text-muted"></i> No
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $peer['memory_gb'] ?>GB</td>
                                    <td>
                                        <?php $installedModels = $peerManager->getInstalledModels($peer['ip']); ?>
                                        <small><?= implode(', ', array_slice($installedModels, 0, 5)) ?>
                                        <?= count($installedModels) > 5 ? '...' : '' ?></small>
                                    </td>
                                    <td><small><?= $peer['last_check'] ?></small></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="test_peer">
                                                <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                <button type="submit" style="background: #17a2b8; color: white; border: 1px solid #17a2b8; padding: 4px 8px; border-radius: 4px; cursor: pointer;" title="Test Peer">
                                                    🧪
                                                </button>
                                            </form>
                                            <?php if (!isset($peer['is_local']) || !$peer['is_local']): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this peer?')">
                                                    <input type="hidden" name="action" value="delete_peer">
                                                    <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                    <button type="submit" style="background: #dc3545; color: white; border: 1px solid #dc3545; padding: 4px 8px; border-radius: 4px; cursor: pointer;" title="Delete Peer">
                                                        🗑️
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="9" style="background: #f8f9fa; padding: 10px;">
                                        <strong>Recommended Models (RAM: <?= $peer['memory_gb'] ?>GB, GPU: <?= $peer['gpu_available'] ? 'Yes' : 'No' ?>):</strong><br>
                                        <?php 
                                        $recommended = $peerManager->getRecommendedModels($peer['memory_gb'], $peer['gpu_available'], $peer['gpu_memory_gb']);
                                        if (empty($recommended)): ?>
                                            <small class="text-muted">No models recommended for this peer's specs</small>
                                        <?php else:
                                            foreach ($recommended as $modelName => $specs): 
                                                if (!in_array($modelName, $installedModels)): ?>
                                                    <form method="POST" style="display: inline-block; margin: 2px;">
                                                        <input type="hidden" name="action" value="install_model">
                                                        <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                        <input type="hidden" name="model_name" value="<?= $modelName ?>">
                                                        <button type="button" onclick="installModelWithProgress('<?= $peer['ip'] ?>', '<?= $modelName ?>')" style="background: #28a745; color: white; border: 1px solid #28a745; padding: 2px 6px; border-radius: 3px; cursor: pointer; font-size: 12px;" 
                                                                title="<?= htmlspecialchars($specs['description']) ?> (<?= $specs['size_gb'] ?>GB)">
                                                            + <?= htmlspecialchars($modelName) ?>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary" style="margin: 2px; font-size: 10px;">
                                                        <?= htmlspecialchars($modelName) ?> ✓
                                                        <form method="POST" style="display: inline; margin-left: 5px;" onsubmit="return confirm('Remove model <?= $modelName ?>?')">
                                                            <input type="hidden" name="action" value="remove_model">
                                                            <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                            <input type="hidden" name="model_name" value="<?= $modelName ?>">
                                                            <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 10px;" title="Remove model">×</button>
                                                        </form>
                                                    </span>
                                                <?php endif;
                                            endforeach;
                                        endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Model Auto-Install Rules -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>Model Auto-Install Rules</h3>
            <div>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="export_models">
                    <button type="submit" style="background: #6c757d; color: white; border: 1px solid #6c757d; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-right: 5px;">
                        📥 Export
                    </button>
                </form>
                <button onclick="document.getElementById('importFile').click()" style="background: #6c757d; color: white; border: 1px solid #6c757d; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    📤 Import
                </button>
                <form method="POST" enctype="multipart/form-data" style="display: none;">
                    <input type="hidden" name="action" value="import_models">
                    <input type="file" id="importFile" name="import_file" accept=".json" onchange="this.form.submit()">
                </form>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_model_rules">
                
                <?php $rules = $peerManager->getModelRules(); ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label><strong>All Peers:</strong></label>
                        <div id="all_peers_models">
                            <?php foreach ($rules['all_peers'] ?? [] as $model): ?>
                                <div class="mb-1">
                                    <input type="text" name="all_peers[]" value="<?= htmlspecialchars($model) ?>" style="width: 200px; padding: 2px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addModel('all_peers')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">+ Add Model</button>
                    </div>
                    
                    <div class="col-md-6">
                        <label><strong>Memory < 4GB:</strong></label>
                        <div id="memory_low_models">
                            <?php foreach ($rules['memory_low'] ?? [] as $model): ?>
                                <div class="mb-1">
                                    <input type="text" name="memory_low[]" value="<?= htmlspecialchars($model) ?>" style="width: 200px; padding: 2px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addModel('memory_low')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">+ Add Model</button>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label><strong>Memory 4-7.5GB:</strong></label>
                        <div id="memory_medium_models">
                            <?php foreach ($rules['memory_medium'] ?? [] as $model): ?>
                                <div class="mb-1">
                                    <input type="text" name="memory_medium[]" value="<?= htmlspecialchars($model) ?>" style="width: 200px; padding: 2px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addModel('memory_medium')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">+ Add Model</button>
                    </div>
                    
                    <div class="col-md-6">
                        <label><strong>Memory > 7.5GB:</strong></label>
                        <div id="memory_high_models">
                            <?php foreach ($rules['memory_high'] ?? [] as $model): ?>
                                <div class="mb-1">
                                    <input type="text" name="memory_high[]" value="<?= htmlspecialchars($model) ?>" style="width: 200px; padding: 2px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addModel('memory_high')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">+ Add Model</button>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label><strong>GPU VRAM < 14GB:</strong></label>
                        <div id="gpu_low_models">
                            <?php foreach ($rules['gpu_low'] ?? [] as $model): ?>
                                <div class="mb-1">
                                    <input type="text" name="gpu_low[]" value="<?= htmlspecialchars($model) ?>" style="width: 200px; padding: 2px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addModel('gpu_low')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">+ Add Model</button>
                    </div>
                    
                    <div class="col-md-6">
                        <label><strong>GPU VRAM >= 14GB:</strong></label>
                        <div id="gpu_high_models">
                            <?php foreach ($rules['gpu_high'] ?? [] as $model): ?>
                                <div class="mb-1">
                                    <input type="text" name="gpu_high[]" value="<?= htmlspecialchars($model) ?>" style="width: 200px; padding: 2px;">
                                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addModel('gpu_high')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">+ Add Model</button>
                    </div>
                </div>
                
                <script src="js/model-install.js"></script>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        Save Auto-Install Rules
                    </button>
                </div>
            </form>
            
            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="apply_auto_install">
                <button type="submit" style="background: #28a745; color: white; border: 1px solid #28a745; padding: 8px 16px; border-radius: 4px; cursor: pointer;" onclick="return confirm('This will install models on all online peers based on the rules above. Continue?')">
                    🚀 Apply Auto-Install Rules Now
                </button>
                <a href="model_status.php" target="_blank" style="background: #17a2b8; color: white; border: 1px solid #17a2b8; padding: 8px 16px; border-radius: 4px; text-decoration: none; margin-left: 10px;">
                    📊 View Installation Status
                </a>
                <small style="margin-left: 10px; color: #666;">Install models on all peers based on the rules above</small>
            </form>
        </div>
    </div>

    <!-- Model Performance Comparison Charts -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>📊 Model Performance Comparison</h3>
        </div>
        <div class="card-body">
            <!-- Vision Models -->
            <h4>🖼️ Vision Models (Image Analysis)</h4>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Size</th>
                            <th>Min RAM</th>
                            <th>Rec VRAM</th>
                            <th>Speed</th>
                            <th>Quality</th>
                            <th>Use Case</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>moondream:1.8b</code></td>
                            <td>1.7GB</td>
                            <td>4GB</td>
                            <td>2GB</td>
                            <td>⚡⚡⚡</td>
                            <td>⭐⭐</td>
                            <td>Basic image understanding</td>
                        </tr>
                        <tr>
                            <td><code>llava-phi3:3.8b</code></td>
                            <td>2.9GB</td>
                            <td>6GB</td>
                            <td>4GB</td>
                            <td>⚡⚡</td>
                            <td>⭐⭐⭐</td>
                            <td>Compact vision processing</td>
                        </tr>
                        <tr>
                            <td><code>bakllava:7b</code></td>
                            <td>4.1GB</td>
                            <td>8GB</td>
                            <td>6GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐</td>
                            <td>Alternative vision architecture</td>
                        </tr>
                        <tr>
                            <td><code>llava:7b</code></td>
                            <td>4.7GB</td>
                            <td>8GB</td>
                            <td>6GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐</td>
                            <td>Image analysis, visual Q&A</td>
                        </tr>
                        <tr>
                            <td><code>llava-llama3:8b</code></td>
                            <td>5.5GB</td>
                            <td>12GB</td>
                            <td>8GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐⭐</td>
                            <td>Latest architecture, improved reasoning</td>
                        </tr>
                        <tr>
                            <td><code>llava:13b</code></td>
                            <td>7.3GB</td>
                            <td>16GB</td>
                            <td>12GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐⭐</td>
                            <td>Enhanced image understanding</td>
                        </tr>
                        <tr>
                            <td><code>llava:34b</code></td>
                            <td>19GB</td>
                            <td>32GB</td>
                            <td>24GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐⭐</td>
                            <td>Professional-grade visual analysis</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Text Models -->
            <h4>📝 Text Models (General Purpose)</h4>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Size</th>
                            <th>Min RAM</th>
                            <th>Speed</th>
                            <th>Quality</th>
                            <th>Use Case</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>llama3.2:1b</code></td>
                            <td>1.3GB</td>
                            <td>2GB</td>
                            <td>⚡⚡⚡</td>
                            <td>⭐⭐</td>
                            <td>Ultra-fast responses, basic tasks</td>
                        </tr>
                        <tr>
                            <td><code>llama3.2:3b</code></td>
                            <td>2.0GB</td>
                            <td>4GB</td>
                            <td>⚡⚡</td>
                            <td>⭐⭐⭐</td>
                            <td>Resume analysis, skill extraction</td>
                        </tr>
                        <tr>
                            <td><code>mistral:7b</code></td>
                            <td>4.1GB</td>
                            <td>8GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐</td>
                            <td>Resume optimization, professional writing</td>
                        </tr>
                        <tr>
                            <td><code>llama3.1:8b</code></td>
                            <td>4.7GB</td>
                            <td>8GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐</td>
                            <td>Comprehensive text analysis, ATS optimization</td>
                        </tr>
                        <tr>
                            <td><code>codellama:7b</code></td>
                            <td>3.8GB</td>
                            <td>8GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐</td>
                            <td>Technical resumes, code analysis</td>
                        </tr>
                        <tr>
                            <td><code>llama3.1:70b</code></td>
                            <td>40GB</td>
                            <td>64GB</td>
                            <td>⚡</td>
                            <td>⭐⭐⭐⭐⭐</td>
                            <td>Enterprise-grade text processing</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Document Processing -->
            <h4>📄 Document Processing Models</h4>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Workflow</th>
                            <th>Models</th>
                            <th>Total RAM</th>
                            <th>Use Case</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Resume Images</strong></td>
                            <td><code>llava:7b</code> → <code>llama3.1:8b</code></td>
                            <td>16GB</td>
                            <td>Scanned resumes, PDF screenshots</td>
                        </tr>
                        <tr>
                            <td><strong>Text Resumes</strong></td>
                            <td><code>llama3.1:8b</code> → <code>mistral:7b</code></td>
                            <td>12GB</td>
                            <td>Text-based resume optimization</td>
                        </tr>
                        <tr>
                            <td><strong>Technical Docs</strong></td>
                            <td><code>llava:7b</code> → <code>codellama:7b</code></td>
                            <td>14GB</td>
                            <td>Developer resumes, technical analysis</td>
                        </tr>
                        <tr>
                            <td><strong>Lightweight</strong></td>
                            <td><code>moondream:1.8b</code> → <code>llama3.2:3b</code></td>
                            <td>6GB</td>
                            <td>Quick document scanning</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <small class="text-muted">
                    <strong>Legend:</strong> 
                    ⚡⚡⚡ = Very Fast | ⚡⚡ = Fast | ⚡ = Moderate | 
                    ⭐⭐⭐⭐⭐ = Excellent | ⭐⭐⭐⭐ = Good | ⭐⭐⭐ = Fair | ⭐⭐ = Basic
                </small>
            </div>
        </div>
    </div>

    <!-- Model Installation Progress Modal -->
    <div id="installModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; max-height: 70%; overflow-y: auto;">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
                <h3>Installing Model</h3>
                <button onclick="closeInstallModal()" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; float: right;">×</button>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Model:</strong> <span id="installModelName"></span><br>
                <strong>Peer:</strong> <span id="installPeerIp"></span>
            </div>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">
                <div id="installProgress">Starting installation...</div>
            </div>
            <div style="margin-top: 15px; text-align: center;">
                <small style="color: #666;">This may take several minutes depending on model size...</small>
            </div>
        </div>
    </div>


<style>
.table th, .table td {
    vertical-align: middle;
    font-size: 13px;
}
.table code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>