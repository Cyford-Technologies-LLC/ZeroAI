<?php
$pageTitle = 'Integration Marketplace - ZeroAI CRM';
$currentPage = 'integration_marketplace';

include __DIR__ . '/includes/header.php';

// Available integrations catalog
$availableIntegrations = [
    [
        'id' => 'salesforce',
        'name' => 'Salesforce',
        'description' => 'Sync contacts, leads, and opportunities with Salesforce CRM',
        'category' => 'CRM',
        'icon' => '☁️',
        'type' => 'api',
        'fields' => ['api_url', 'username', 'password', 'security_token']
    ],
    [
        'id' => 'hubspot',
        'name' => 'HubSpot',
        'description' => 'Connect with HubSpot for marketing automation and lead management',
        'category' => 'Marketing',
        'icon' => '🎯',
        'type' => 'api',
        'fields' => ['api_key', 'portal_id']
    ],
    [
        'id' => 'mailchimp',
        'name' => 'Mailchimp',
        'description' => 'Email marketing and newsletter management',
        'category' => 'Email',
        'icon' => '📧',
        'type' => 'api',
        'fields' => ['api_key', 'server_prefix']
    ],
    [
        'id' => 'slack',
        'name' => 'Slack',
        'description' => 'Send notifications and updates to Slack channels',
        'category' => 'Communication',
        'icon' => '💬',
        'type' => 'webhook',
        'fields' => ['webhook_url', 'channel']
    ],
    [
        'id' => 'zapier',
        'name' => 'Zapier',
        'description' => 'Connect with 5000+ apps through Zapier automation',
        'category' => 'Automation',
        'icon' => '⚡',
        'type' => 'webhook',
        'fields' => ['webhook_url']
    ],
    [
        'id' => 'quickbooks',
        'name' => 'QuickBooks',
        'description' => 'Sync invoices and financial data with QuickBooks',
        'category' => 'Accounting',
        'icon' => '💰',
        'type' => 'api',
        'fields' => ['client_id', 'client_secret', 'company_id']
    ],
    [
        'id' => 'google_sheets',
        'name' => 'Google Sheets',
        'description' => 'Export and sync data with Google Sheets',
        'category' => 'Data',
        'icon' => '📊',
        'type' => 'api',
        'fields' => ['service_account_key', 'spreadsheet_id']
    ],
    [
        'id' => 'microsoft_teams',
        'name' => 'Microsoft Teams',
        'description' => 'Send notifications to Microsoft Teams channels',
        'category' => 'Communication',
        'icon' => '🏢',
        'type' => 'webhook',
        'fields' => ['webhook_url']
    ]
];

// Get user's existing integrations
try {
    require_once __DIR__ . '/../src/Services/IntegrationManager.php';
    $integrationManager = new \ZeroAI\Services\IntegrationManager($userOrgId);
    $userIntegrations = $integrationManager->getIntegrations();
    $installedIds = array_column($userIntegrations, 'type');
} catch (Exception $e) {
    $userIntegrations = [];
    $installedIds = [];
}

// Handle installation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'install') {
    try {
        $integrationId = $_POST['integration_id'];
        $integration = array_filter($availableIntegrations, fn($i) => $i['id'] === $integrationId)[0] ?? null;
        
        if ($integration) {
            $config = [];
            foreach ($integration['fields'] as $field) {
                if (isset($_POST[$field])) {
                    $config[$field] = $_POST[$field];
                }
            }
            
            $integrationManager->addIntegration($integration['name'], $integration['id'], $config);
            header('Location: /web/integration_marketplace.php?success=installed');
            exit;
        }
    } catch (Exception $e) {
        $error = "Installation failed: " . $e->getMessage();
    }
}

$success = '';
if (isset($_GET['success']) && $_GET['success'] === 'installed') {
    $success = 'Integration installed successfully!';
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Integration Marketplace</h2>
        <p class="text-muted">Connect ZeroAI CRM with your favorite tools and platforms</p>
    </div>
</div>

<div class="row">
    <?php foreach ($availableIntegrations as $integration): ?>
        <?php $isInstalled = in_array($integration['id'], $installedIds); ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span style="font-size: 2rem; margin-right: 10px;"><?= $integration['icon'] ?></span>
                        <div>
                            <h5 class="card-title mb-1"><?= htmlspecialchars($integration['name']) ?></h5>
                            <small class="text-muted"><?= htmlspecialchars($integration['category']) ?></small>
                        </div>
                    </div>
                    <p class="card-text"><?= htmlspecialchars($integration['description']) ?></p>
                </div>
                <div class="card-footer">
                    <?php if ($isInstalled): ?>
                        <button class="btn btn-success btn-sm" disabled>
                            ✓ Installed
                        </button>
                        <a href="/web/integrations.php" class="btn btn-outline-primary btn-sm">
                            Configure
                        </a>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="showInstallModal('<?= $integration['id'] ?>')">
                            Install
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Install Modal -->
<div class="modal fade" id="installModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Install Integration</h5>
                <button type="button" class="btn-close" onclick="closeModal('installModal')"></button>
            </div>
            <form method="POST" id="installForm">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="integration_id" id="modalIntegrationId">
                <div class="modal-body">
                    <div id="modalContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('installModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Install</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const integrations = <?= json_encode($availableIntegrations) ?>;

function showInstallModal(integrationId) {
    const integration = integrations.find(i => i.id === integrationId);
    if (!integration) return;
    
    document.getElementById('modalIntegrationId').value = integrationId;
    
    let content = `<h6>${integration.icon} ${integration.name}</h6>`;
    content += `<p>${integration.description}</p>`;
    content += `<hr><h6>Configuration</h6>`;
    
    integration.fields.forEach(field => {
        const label = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const type = field.includes('password') || field.includes('secret') || field.includes('key') ? 'password' : 'text';
        content += `
            <div class="mb-3">
                <label class="form-label">${label}</label>
                <input type="${type}" class="form-control" name="${field}" required>
            </div>
        `;
    });
    
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('installModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('installModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>