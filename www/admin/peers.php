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
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sync"></i> Refresh Status
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
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Peer
                        </button>
                    </div>
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
                                        <small><?= implode(', ', array_slice($peer['models'], 0, 2)) ?>
                                        <?= count($peer['models']) > 2 ? '...' : '' ?></small>
                                    </td>
                                    <td><small><?= $peer['last_check'] ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="test_peer">
                                                <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                <button type="submit" class="btn btn-outline-info" title="Test Peer">
                                                    <i class="fas fa-vial"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this peer?')">
                                                <input type="hidden" name="action" value="delete_peer">
                                                <input type="hidden" name="peer_ip" value="<?= $peer['ip'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Peer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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