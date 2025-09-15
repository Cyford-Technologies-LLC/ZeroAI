<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = "AI Providers";
$currentPage = 'ai_providers';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../src/Providers/AI/AIProviderFactory.php';
use ZeroAI\Providers\AI\AIProviderFactory;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">AI Provider Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach (AIProviderFactory::getAvailableProviders() as $provider): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between">
                                    <h6><?= ucfirst($provider) ?></h6>
                                    <span class="badge badge-secondary" id="status-<?= $provider ?>">Not Configured</span>
                                </div>
                                <div class="card-body">
                                    <form id="form-<?= $provider ?>">
                                        <div class="form-group">
                                            <label>API Key</label>
                                            <input type="password" class="form-control" id="apikey-<?= $provider ?>" placeholder="Enter API key">
                                        </div>
                                        <div class="form-group">
                                            <label>Default Model</label>
                                            <select class="form-control" id="model-<?= $provider ?>">
                                                <option value="">Select model after API key validation</option>
                                            </select>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="enabled-<?= $provider ?>">
                                            <label class="form-check-label">Enabled</label>
                                        </div>
                                        <button type="button" class="btn btn-primary" onclick="testProvider('<?= $provider ?>')">Test & Save</button>
                                        <button type="button" class="btn btn-info" onclick="loadModels('<?= $provider ?>')">Load Models</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testProvider(provider) {
    const apiKey = document.getElementById(`apikey-${provider}`).value;
    if (!apiKey) {
        alert('Please enter an API key');
        return;
    }
    
    fetch('/admin/ai_provider_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'test',
            provider: provider,
            apiKey: apiKey
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById(`status-${provider}`).textContent = 'Connected';
            document.getElementById(`status-${provider}`).className = 'badge badge-success';
            loadModels(provider);
            saveProvider(provider);
        } else {
            document.getElementById(`status-${provider}`).textContent = 'Error';
            document.getElementById(`status-${provider}`).className = 'badge badge-danger';
            alert('Connection failed: ' + data.error);
        }
    });
}

function loadModels(provider) {
    const apiKey = document.getElementById(`apikey-${provider}`).value;
    if (!apiKey) return;
    
    fetch('/admin/ai_provider_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'models',
            provider: provider,
            apiKey: apiKey
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById(`model-${provider}`);
            select.innerHTML = '';
            data.models.forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                select.appendChild(option);
            });
        }
    });
}

function saveProvider(provider) {
    const config = {
        apiKey: document.getElementById(`apikey-${provider}`).value,
        model: document.getElementById(`model-${provider}`).value,
        enabled: document.getElementById(`enabled-${provider}`).checked
    };
    
    fetch('/admin/ai_provider_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'save',
            provider: provider,
            config: config
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Provider configuration saved');
        }
    });
}

// Load existing configurations
document.addEventListener('DOMContentLoaded', function() {
    fetch('/admin/ai_provider_api.php?action=load')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Object.keys(data.providers).forEach(provider => {
                const config = data.providers[provider];
                if (config.apiKey) {
                    document.getElementById(`apikey-${provider}`).value = config.apiKey;
                    document.getElementById(`enabled-${provider}`).checked = config.enabled;
                    document.getElementById(`status-${provider}`).textContent = 'Configured';
                    document.getElementById(`status-${provider}`).className = 'badge badge-success';
                    loadModels(provider);
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


