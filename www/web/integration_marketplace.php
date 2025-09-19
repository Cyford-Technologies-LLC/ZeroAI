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
    ],
    [
        'id' => 'stripe',
        'name' => 'Stripe',
        'description' => 'Process payments and manage subscriptions with Stripe',
        'category' => 'Payments',
        'icon' => '💳',
        'type' => 'api',
        'fields' => ['publishable_key', 'secret_key', 'webhook_secret']
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

<style>
.marketplace-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    margin: -20px -20px 2rem -20px;
}

.integration-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.integration-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.integration-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
}

.category-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
}

.category-crm { background: #e3f2fd; color: #1976d2; }
.category-marketing { background: #f3e5f5; color: #7b1fa2; }
.category-email { background: #e8f5e8; color: #388e3c; }
.category-communication { background: #fff3e0; color: #f57c00; }
.category-automation { background: #fce4ec; color: #c2185b; }
.category-accounting { background: #e0f2f1; color: #00695c; }
.category-data { background: #f1f8e9; color: #558b2f; }
.category-payments { background: #e8eaf6; color: #3f51b5; }

.install-btn {
    border-radius: 8px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.install-btn:hover {
    transform: translateY(-1px);
}

.status-installed {
    background: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.filter-tabs {
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 2rem;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    border: none;
    background: none;
    color: #666;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
}

.filter-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.stats-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    text-align: center;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="marketplace-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-3">🚀 Integration Marketplace</h1>
                <p class="lead mb-0">Connect ZeroAI CRM with your favorite tools and supercharge your workflow</p>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h3 class="text-primary"><?= count($availableIntegrations) ?></h3>
                    <p class="mb-0 text-muted">Available Integrations</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="filter-tabs">
    <button class="filter-tab active" onclick="filterIntegrations('all')">All</button>
    <button class="filter-tab" onclick="filterIntegrations('CRM')">CRM</button>
    <button class="filter-tab" onclick="filterIntegrations('Marketing')">Marketing</button>
    <button class="filter-tab" onclick="filterIntegrations('Payments')">Payments</button>
    <button class="filter-tab" onclick="filterIntegrations('Communication')">Communication</button>
    <button class="filter-tab" onclick="filterIntegrations('Automation')">Automation</button>
</div>

<div class="row" id="integrations-grid">
    <?php foreach ($availableIntegrations as $integration): ?>
        <?php $isInstalled = in_array($integration['id'], $installedIds); ?>
        <div class="col-md-6 col-lg-4 mb-4 integration-item" data-category="<?= $integration['category'] ?>">
            <div class="card integration-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="integration-icon bg-light">
                            <?= $integration['icon'] ?>
                        </div>
                        <span class="category-badge category-<?= strtolower($integration['category']) ?>">
                            <?= $integration['category'] ?>
                        </span>
                    </div>
                    
                    <h5 class="card-title mb-2"><?= htmlspecialchars($integration['name']) ?></h5>
                    <p class="card-text text-muted mb-4"><?= htmlspecialchars($integration['description']) ?></p>
                    
                    <?php if ($isInstalled): ?>
                        <div class="d-flex gap-2">
                            <div class="status-installed flex-grow-1 text-center">
                                <i class="fas fa-check me-1"></i>Installed
                            </div>
                            <a href="/web/integrations.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-cog me-1"></i>Configure
                            </a>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-primary install-btn w-100" onclick="showInstallModal('<?= $integration['id'] ?>')">
                            <i class="fas fa-download me-2"></i>Install Integration
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Install Modal -->
<div class="modal fade" id="installModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-puzzle-piece me-2"></i>Install Integration</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('installModal')"></button>
            </div>
            <form method="POST" id="installForm">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="integration_id" id="modalIntegrationId">
                <div class="modal-body p-4">
                    <div id="modalContent"></div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 1.5rem;">
                    <button type="button" class="btn btn-light" onclick="closeModal('installModal')">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Install Integration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<script>
const integrations = <?= json_encode($availableIntegrations) ?>;

function filterIntegrations(category) {
    const items = document.querySelectorAll('.integration-item');
    const tabs = document.querySelectorAll('.filter-tab');
    
    // Update active tab
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter items
    items.forEach(item => {
        if (category === 'all' || item.dataset.category === category) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function showInstallModal(integrationId) {
    const integration = integrations.find(i => i.id === integrationId);
    if (!integration) return;
    
    document.getElementById('modalIntegrationId').value = integrationId;
    
    let content = `
        <div class="text-center mb-4">
            <div class="integration-icon bg-light mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.5rem;">
                ${integration.icon}
            </div>
            <h5>${integration.name}</h5>
            <p class="text-muted">${integration.description}</p>
        </div>
        <hr>
        <h6 class="mb-3"><i class="fas fa-cog me-2"></i>Configuration</h6>
    `;
    
    integration.fields.forEach(field => {
        const label = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const type = field.includes('password') || field.includes('secret') || field.includes('key') ? 'password' : 'text';
        const icon = field.includes('key') ? 'fas fa-key' : field.includes('url') ? 'fas fa-link' : 'fas fa-edit';
        
        content += `
            <div class="mb-3">
                <label class="form-label"><i class="${icon} me-2 text-muted"></i>${label}</label>
                <input type="${type}" class="form-control" name="${field}" required 
                       placeholder="Enter your ${label.toLowerCase()}">
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

// Add smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.integration-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate__animated', 'animate__fadeInUp');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>