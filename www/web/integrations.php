<?php
$pageTitle = 'Integrations - ZeroAI CRM';
$currentPage = 'integrations';

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    ob_start();
    include __DIR__ . '/includes/header.php';
    
    try {
        require_once __DIR__ . '/../src/Services/IntegrationManager.php';
        // Ensure userOrgId is just the organization ID, not a path
        $orgId = is_string($userOrgId) && strpos($userOrgId, '/') !== false ? basename(dirname($userOrgId)) : $userOrgId;
        $integrationManager = new \ZeroAI\Services\IntegrationManager($orgId);
        
        if ($_POST['action'] === 'create') {
            $config = [];
            if ($_POST['type'] === 'api') {
                $config = [
                    'api_url' => $_POST['api_url'] ?? '',
                    'api_key' => $_POST['api_key'] ?? '',
                    'headers' => $_POST['headers'] ?? ''
                ];
            } elseif ($_POST['type'] === 'webhook') {
                $config = [
                    'webhook_url' => $_POST['webhook_url'] ?? '',
                    'secret' => $_POST['secret'] ?? ''
                ];
            }
            
            $integrationManager->addIntegration($_POST['name'], $_POST['type'], $config);
            ob_end_clean();
            header('Location: /web/integrations.php?success=added');
            exit;
        }
        
        if ($_POST['action'] === 'toggle') {
            $integrationManager->toggleIntegration($_POST['integration_id'], $_POST['status']);
            ob_end_clean();
            header('Location: /web/integrations.php?success=toggled');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            $integrationManager->deleteIntegration($_POST['integration_id']);
            ob_end_clean();
            header('Location: /web/integrations.php?success=deleted');
            exit;
        }
    } catch (Exception $e) {
        ob_end_clean();
        $error = "Error: " . $e->getMessage();
    }
} else {
    include __DIR__ . '/includes/header.php';
}

// Get integrations
try {
    require_once __DIR__ . '/../src/Services/IntegrationManager.php';
    
    // Debug the userOrgId value
    error_log("[DEBUG] Integrations: Raw userOrgId: " . var_export($userOrgId, true));
    
    // Ensure userOrgId is just the organization ID, not a path
    $orgId = is_string($userOrgId) && strpos($userOrgId, '/') !== false ? basename(dirname($userOrgId)) : $userOrgId;
    
    error_log("[DEBUG] Integrations: Final orgId: " . var_export($orgId, true));
    
    $integrationManager = new \ZeroAI\Services\IntegrationManager($orgId);
    $integrations = $integrationManager->getIntegrations();
} catch (Exception $e) {
    $integrations = [];
    $error = "Error loading integrations: " . $e->getMessage();
    error_log("[ERROR] Integrations page: " . $e->getMessage());
    error_log("[ERROR] Integrations page trace: " . $e->getTraceAsString());
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added': $success = 'Integration added successfully!'; break;
        case 'toggled': $success = 'Integration status updated!'; break;
        case 'deleted': $success = 'Integration deleted successfully!'; break;
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5>
            <button class="btn btn-link p-0 text-decoration-none" type="button" onclick="toggleCollapse('addIntegrationForm')">
                <i class="fas fa-plus-circle"></i> Add New Integration
            </button>
        </h5>
    </div>
    <div class="collapse" id="addIntegrationForm" style="display: none;">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Integration Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Integration Type</label>
                        <select class="form-select" name="type" onchange="showTypeFields(this.value)" required>
                            <option value="">Select Type</option>
                            <option value="api">API Integration</option>
                            <option value="webhook">Webhook</option>
                            <option value="database">Database</option>
                            <option value="file">File Import</option>
                        </select>
                    </div>
                </div>
                
                <div id="apiFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">API URL</label>
                            <input type="url" class="form-control" name="api_url">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" class="form-control" name="api_key">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Headers (JSON)</label>
                            <textarea class="form-control" name="headers" rows="3" placeholder='{"Authorization": "Bearer token"}'></textarea>
                        </div>
                    </div>
                </div>
                
                <div id="webhookFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" class="form-control" name="webhook_url">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Secret Key</label>
                            <input type="text" class="form-control" name="secret">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Integration</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Active Integrations</h5>
    </div>
    <div class="card-body">
        <?php if (empty($integrations)): ?>
            <p>No integrations configured. Add your first integration above.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integrations as $integration): ?>
                            <tr>
                                <td><?= htmlspecialchars($integration['name']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= strtoupper($integration['type']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $integration['status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($integration['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($integration['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="integration_id" value="<?= $integration['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $integration['status'] === 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-<?= $integration['status'] === 'active' ? 'warning' : 'success' ?> btn-sm">
                                                <?= $integration['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                        <button onclick="deleteIntegration(<?= $integration['id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
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

<script>
function toggleCollapse(id) {
    const element = document.getElementById(id);
    element.style.display = element.style.display === 'none' ? 'block' : 'none';
}

function showTypeFields(type) {
    document.getElementById('apiFields').style.display = 'none';
    document.getElementById('webhookFields').style.display = 'none';
    
    if (type === 'api') {
        document.getElementById('apiFields').style.display = 'block';
    } else if (type === 'webhook') {
        document.getElementById('webhookFields').style.display = 'block';
    }
}

function deleteIntegration(id) {
    if (confirm('Are you sure you want to delete this integration?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="integration_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>