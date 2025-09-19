<?php
$pageTitle = 'Stripe Integration - ZeroAI CRM';
$currentPage = 'stripe_integration';

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    ob_start();
    include __DIR__ . '/includes/header.php';
    
    try {
        require_once __DIR__ . '/../src/Services/IntegrationManager.php';
        $integrationManager = new \ZeroAI\Services\IntegrationManager($userOrgId);
        
        if ($_POST['action'] === 'configure') {
            $config = [
                'publishable_key' => $_POST['publishable_key'],
                'secret_key' => $_POST['secret_key'],
                'webhook_secret' => $_POST['webhook_secret'] ?? ''
            ];
            
            // Check if Stripe integration exists
            $integrations = $integrationManager->getIntegrations();
            $stripeIntegration = array_filter($integrations, fn($i) => $i['type'] === 'stripe');
            
            if (!empty($stripeIntegration)) {
                // Update existing
                $integration = reset($stripeIntegration);
                $integrationManager->updateIntegration($integration['id'], [
                    'config' => $config,
                    'status' => 'active'
                ]);
            } else {
                // Create new
                $integrationManager->addIntegration('Stripe', 'stripe', $config);
            }
            
            ob_end_clean();
            header('Location: /web/stripe_integration.php?success=configured');
            exit;
        }
        
        if ($_POST['action'] === 'test') {
            require_once __DIR__ . '/../src/Services/StripeIntegration.php';
            $stripe = new \ZeroAI\Services\StripeIntegration($userOrgId);
            
            // Test by creating a test customer
            $result = $stripe->createCustomer('test@example.com', 'Test Customer');
            
            ob_end_clean();
            header('Location: /web/stripe_integration.php?success=test&customer_id=' . $result['id']);
            exit;
        }
    } catch (Exception $e) {
        ob_end_clean();
        $error = "Error: " . $e->getMessage();
    }
} else {
    include __DIR__ . '/includes/header.php';
}

// Get current Stripe configuration
try {
    require_once __DIR__ . '/../src/Services/IntegrationManager.php';
    $integrationManager = new \ZeroAI\Services\IntegrationManager($userOrgId);
    $integrations = $integrationManager->getIntegrations();
    $stripeIntegration = array_filter($integrations, fn($i) => $i['type'] === 'stripe');
    $stripeConfig = !empty($stripeIntegration) ? reset($stripeIntegration)['config'] : null;
} catch (Exception $e) {
    $stripeConfig = null;
    $error = "Error loading configuration: " . $e->getMessage();
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'configured': 
            $success = 'Stripe integration configured successfully!'; 
            break;
        case 'test': 
            $customerId = $_GET['customer_id'] ?? '';
            $success = "Stripe connection test successful! Created test customer: {$customerId}"; 
            break;
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>💳 Stripe Payment Integration</h5>
            </div>
            <div class="card-body">
                <p>Connect your Stripe account to process payments and manage subscriptions directly from ZeroAI CRM.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="configure">
                    
                    <div class="mb-3">
                        <label class="form-label">Publishable Key</label>
                        <input type="text" class="form-control" name="publishable_key" 
                               value="<?= htmlspecialchars($stripeConfig['publishable_key'] ?? '') ?>" 
                               placeholder="pk_test_..." required>
                        <small class="form-text text-muted">Your Stripe publishable key (starts with pk_)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Secret Key</label>
                        <input type="password" class="form-control" name="secret_key" 
                               value="<?= htmlspecialchars($stripeConfig['secret_key'] ?? '') ?>" 
                               placeholder="sk_test_..." required>
                        <small class="form-text text-muted">Your Stripe secret key (starts with sk_)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Webhook Secret (Optional)</label>
                        <input type="password" class="form-control" name="webhook_secret" 
                               value="<?= htmlspecialchars($stripeConfig['webhook_secret'] ?? '') ?>" 
                               placeholder="whsec_...">
                        <small class="form-text text-muted">Webhook endpoint secret for secure event handling</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $stripeConfig ? 'Update Configuration' : 'Configure Stripe' ?>
                        </button>
                        
                        <?php if ($stripeConfig): ?>
                            <button type="submit" name="action" value="test" class="btn btn-outline-success">
                                Test Connection
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6>Setup Instructions</h6>
            </div>
            <div class="card-body">
                <ol class="small">
                    <li>Log in to your <a href="https://dashboard.stripe.com" target="_blank">Stripe Dashboard</a></li>
                    <li>Go to Developers → API keys</li>
                    <li>Copy your Publishable key and Secret key</li>
                    <li>For webhooks, create an endpoint pointing to:<br>
                        <code><?= $_SERVER['HTTP_HOST'] ?>/web/stripe_webhook.php</code>
                    </li>
                    <li>Copy the webhook signing secret</li>
                </ol>
                
                <hr>
                
                <h6>Features</h6>
                <ul class="small">
                    <li>Process one-time payments</li>
                    <li>Create and manage customers</li>
                    <li>Handle subscription billing</li>
                    <li>Receive real-time webhooks</li>
                    <li>View payment analytics</li>
                </ul>
            </div>
        </div>
        
        <?php if ($stripeConfig): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h6>Integration Status</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-2">✓</span>
                    <span>Stripe Configured</span>
                </div>
                <small class="text-muted">
                    Key: <?= substr($stripeConfig['publishable_key'], 0, 12) ?>...
                </small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>