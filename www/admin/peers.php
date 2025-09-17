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
        <div class="card-header">
            <h3>Model Auto-Install Rules</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_model_rules">
                
                <?php $rules = $peerManager->getModelRules(); ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label><strong>All Peers:</strong></label>
                        <div>
                            <label><input type="checkbox" name="all_peers[]" value="llama3.2:1b" <?= in_array('llama3.2:1b', $rules['all_peers'] ?? []) ? 'checked' : '' ?>> llama3.2:1b (1.3GB)</label><br>
                            <label><input type="checkbox" name="all_peers[]" value="llama3.2:3b" <?= in_array('llama3.2:3b', $rules['all_peers'] ?? []) ? 'checked' : '' ?>> llama3.2:3b (2GB)</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label><strong>Memory < 4GB:</strong></label>
                        <div>
                            <label><input type="checkbox" name="memory_low[]" value="llama3.2:1b" <?= in_array('llama3.2:1b', $rules['memory_low'] ?? []) ? 'checked' : '' ?>> llama3.2:1b (1.3GB)</label>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label><strong>Memory 4-8GB:</strong></label>
                        <div>
                            <label><input type="checkbox" name="memory_medium[]" value="llama3.2:3b" <?= in_array('llama3.2:3b', $rules['memory_medium'] ?? []) ? 'checked' : '' ?>> llama3.2:3b (2GB)</label><br>
                            <label><input type="checkbox" name="memory_medium[]" value="mistral:7b" <?= in_array('mistral:7b', $rules['memory_medium'] ?? []) ? 'checked' : '' ?>> mistral:7b (4.1GB)</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label><strong>Memory > 8GB:</strong></label>
                        <div>
                            <label><input type="checkbox" name="memory_high[]" value="llama3.1:8b" <?= in_array('llama3.1:8b', $rules['memory_high'] ?? []) ? 'checked' : '' ?>> llama3.1:8b (4.7GB)</label><br>
                            <label><input type="checkbox" name="memory_high[]" value="codellama:7b" <?= in_array('codellama:7b', $rules['memory_high'] ?? []) ? 'checked' : '' ?>> codellama:7b (3.8GB)</label><br>
                            <label><input type="checkbox" name="memory_high[]" value="mixtral:8x7b" <?= in_array('mixtral:8x7b', $rules['memory_high'] ?? []) ? 'checked' : '' ?>> mixtral:8x7b (26GB)</label>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label><strong>GPU VRAM <= 16GB:</strong></label>
                        <div>
                            <label><input type="checkbox" name="gpu_low[]" value="llama3.1:8b-instruct-fp16" <?= in_array('llama3.1:8b-instruct-fp16', $rules['gpu_low'] ?? []) ? 'checked' : '' ?>> llama3.1:8b-instruct-fp16 (8GB)</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label><strong>GPU VRAM > 16GB:</strong></label>
                        <div>
                            <label><input type="checkbox" name="gpu_high[]" value="llama3.1:70b-instruct-fp16" <?= in_array('llama3.1:70b-instruct-fp16', $rules['gpu_high'] ?? []) ? 'checked' : '' ?>> llama3.1:70b-instruct-fp16 (40GB)</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                    Save Auto-Install Rules
                </button>
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
                                    <td><?= htmlspecialchars($peer['name']) ?></td>
                                    <td><?= htmlspecialchars($peer['ip']) ?>:<?= $peer['port'] ?></td>
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
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this peer?')">
                                                <input type="hidden" name="action" value="delete_peer">
                                                <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                <button type="submit" style="background: #dc3545; color: white; border: 1px solid #dc3545; padding: 4px 8px; border-radius: 4px; cursor: pointer;" title="Delete Peer">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="8" style="background: #f8f9fa; padding: 10px;">
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
                                                    <span class="badge bg-secondary" style="margin: 2px; font-size: 10px;"><?= htmlspecialchars($modelName) ?> ✓</span>
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