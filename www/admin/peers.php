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
                $result = $peerManager->installModel($ip, $model);
                $message = $result ? "Model '{$model}' installation started!" : "Model installation failed!";
                $messageType = $result ? 'success' : 'error';
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
            <?= htmlspecialchars($message) ?>
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
                        <label><strong>Memory 4-8GB:</strong></label>
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
                        <label><strong>Memory > 8GB:</strong></label>
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
                        <label><strong>GPU VRAM <= 16GB:</strong></label>
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
                        <label><strong>GPU VRAM > 16GB:</strong></label>
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
                
                <script>
                function addModel(category) {
                    const container = document.getElementById(category + '_models');
                    const div = document.createElement('div');
                    div.className = 'mb-1';
                    div.innerHTML = `
                        <input type="text" name="${category}[]" placeholder="model:tag" style="width: 200px; padding: 2px;">
                        <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
                    `;
                    container.appendChild(div);
                }
                </script>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        Save Auto-Install Rules
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
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
                                        <small><?= implode(', ', array_slice($installedModels, 0, 2)) ?>
                                        <?= count($installedModels) > 2 ? '...' : '' ?></small>
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
                                                        <button type="submit" style="background: #28a745; color: white; border: 1px solid #28a745; padding: 2px 6px; border-radius: 3px; cursor: pointer; font-size: 12px;" 
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

<?php include __DIR__ . '/includes/footer.php'; ?>