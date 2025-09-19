<?php
$pageTitle = 'Billing & Usage - ZeroAI CRM';
$currentPage = 'billing';

include __DIR__ . '/includes/header.php';

try {
    require_once __DIR__ . '/../src/Services/SubscriptionManager.php';
    $subscriptionManager = new \ZeroAI\Services\SubscriptionManager($userOrgId);
    
    $subscription = $subscriptionManager->getCurrentSubscription();
    $tokenBalance = $subscriptionManager->getTokenBalance();
    $usageStats = $subscriptionManager->getUsageStats(30);
} catch (Exception $e) {
    $error = "Error loading billing information: " . $e->getMessage();
}
?>

<style>
.billing-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin-bottom: 2rem;
    border-radius: 12px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.progress-ring {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
}

.progress-ring circle {
    fill: transparent;
    stroke-width: 8;
    stroke-linecap: round;
}

.progress-ring .background {
    stroke: #e9ecef;
}

.progress-ring .progress {
    stroke: #667eea;
    stroke-dasharray: 283;
    stroke-dashoffset: 283;
    transition: stroke-dashoffset 0.5s ease;
}

.usage-chart {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.feature-usage {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.feature-usage:last-child {
    border-bottom: none;
}

.usage-bar {
    width: 100px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 4px;
    transition: width 0.5s ease;
}

.plan-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border: 2px solid #e9ecef;
}

.plan-card.current {
    border-color: #667eea;
    background: linear-gradient(135deg, #f8f9ff 0%, #e8eaff 100%);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-active { background: #d4edda; color: #155724; }
.status-trialing { background: #d1ecf1; color: #0c5460; }
.status-canceled { background: #f8d7da; color: #721c24; }
</style>

<div class="billing-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-3">💳 Billing & Usage</h1>
            <p class="mb-0">Manage your subscription and monitor token usage</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="/web/store.php" class="btn btn-light">
                <i class="fas fa-shopping-cart me-2"></i>Upgrade Plan
            </a>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="stat-card">
            <div class="stat-icon">🪙</div>
            <div class="stat-value text-primary"><?= number_format($tokenBalance['tokens_available'] ?? 0) ?></div>
            <div class="text-muted">Available Tokens</div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value text-success"><?= number_format($tokenBalance['tokens_used'] ?? 0) ?></div>
            <div class="text-muted">Tokens Used</div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value text-info"><?= number_format($tokenBalance['tokens_purchased'] ?? 0) ?></div>
            <div class="text-muted">Tokens Purchased</div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="stat-card">
            <?php 
            $totalTokens = ($tokenBalance['tokens_available'] ?? 0) + ($tokenBalance['tokens_used'] ?? 0);
            $usagePercent = $totalTokens > 0 ? (($tokenBalance['tokens_used'] ?? 0) / $totalTokens) * 100 : 0;
            ?>
            <svg class="progress-ring">
                <circle class="background" cx="60" cy="60" r="45"></circle>
                <circle class="progress" cx="60" cy="60" r="45" 
                        style="stroke-dashoffset: <?= 283 - (283 * $usagePercent / 100) ?>"></circle>
            </svg>
            <div class="stat-value text-warning"><?= number_format($usagePercent, 1) ?>%</div>
            <div class="text-muted">Usage Rate</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Current Plan -->
    <div class="col-lg-6 mb-4">
        <div class="plan-card <?= $subscription ? 'current' : '' ?>">
            <h5 class="mb-3">📋 Current Plan</h5>
            
            <?php if ($subscription): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><?= htmlspecialchars($subscription['plan_name']) ?></h4>
                    <span class="status-badge status-<?= $subscription['status'] ?>">
                        <?= ucfirst($subscription['status']) ?>
                    </span>
                </div>
                
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="h4 text-primary">$<?= number_format($subscription['price'], 2) ?></div>
                        <small class="text-muted">per <?= $subscription['billing_cycle'] ?></small>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-success"><?= date('M j, Y', strtotime($subscription['current_period_end'])) ?></div>
                        <small class="text-muted">next billing</small>
                    </div>
                </div>
                
                <?php if ($subscription['status'] === 'trialing'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-gift me-2"></i>
                        <strong>Free Trial:</strong> Ends on <?= date('M j, Y', strtotime($subscription['trial_end'])) ?>
                    </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary">Manage Subscription</button>
                    <button class="btn btn-outline-danger">Cancel Subscription</button>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <div class="h1 text-muted">📦</div>
                    <h5>No Active Subscription</h5>
                    <p class="text-muted">Choose a plan to get started</p>
                    <a href="/web/store.php" class="btn btn-primary">View Plans</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Usage by Feature -->
    <div class="col-lg-6 mb-4">
        <div class="usage-chart">
            <h5 class="mb-4">📈 Usage by Feature (Last 30 Days)</h5>
            
            <?php if (!empty($usageStats['by_feature'])): ?>
                <?php foreach ($usageStats['by_feature'] as $feature): ?>
                    <div class="feature-usage">
                        <div>
                            <strong><?= htmlspecialchars($feature['feature']) ?></strong>
                            <br>
                            <small class="text-muted"><?= $feature['usage_count'] ?> uses</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold"><?= number_format($feature['total_tokens']) ?></div>
                            <div class="usage-bar">
                                <?php 
                                $maxTokens = $usageStats['by_feature'][0]['total_tokens'] ?? 1;
                                $percentage = ($feature['total_tokens'] / $maxTokens) * 100;
                                ?>
                                <div class="usage-fill" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <div class="h1 text-muted">📊</div>
                    <p class="text-muted">No usage data available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">⚡ Quick Actions</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="/web/store.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-shopping-cart me-2"></i>Buy More Tokens
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-outline-success w-100">
                            <i class="fas fa-download me-2"></i>Download Invoice
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-outline-info w-100">
                            <i class="fas fa-chart-line me-2"></i>Usage Report
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-outline-warning w-100">
                            <i class="fas fa-cog me-2"></i>Billing Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>