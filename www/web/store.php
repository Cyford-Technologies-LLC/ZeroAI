<?php
$pageTitle = 'ZeroAI Store';
$currentPage = 'store';

include __DIR__ . '/includes/header.php';
?>

<style>
.store-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 300px;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.store-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    text-decoration: none;
    color: inherit;
}

.store-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.subscription-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.token-card {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.billing-card {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: white;
}
</style>

<div class="store-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-3">🚀 ZeroAI Store</h1>
            <p class="mb-0">Choose your plan and power up your CRM with AI tokens</p>
        </div>
        <div class="col-md-4 text-center">
            <div class="bg-white text-dark p-3 rounded-3">
                <h5 class="text-primary mb-0">Start Free</h5>
                <small class="mb-0">14-day trial included</small>
            </div>
        </div>
    </div>
</div>

<!-- Subscription Plans -->
<div class="section-title">
    <h2>💎 Subscription Plans</h2>
    <p class="text-muted">Choose the perfect plan for your business needs</p>
</div>

<div class="row mb-5">
    <?php foreach ($subscriptions as $plan): ?>
        <div class="col-lg-4 mb-4">
            <div class="card pricing-card h-100 <?= $plan['popular'] ? 'popular' : '' ?>">
                <div class="card-body text-center p-4">
                    <h3 class="card-title"><?= $plan['name'] ?></h3>
                    <div class="price-display">
                        $<?= $plan['price'] ?>
                        <small class="text-muted" style="font-size: 1rem;">/<?= $plan['billing'] ?></small>
                    </div>
                    
                    <ul class="feature-list mt-4 mb-4">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li><?= $feature ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <button class="btn btn-primary btn-purchase w-100" 
                            onclick="checkout('subscription', '<?= $plan['id'] ?>', <?= $plan['price'] ?>)">
                        Choose Plan
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Token Packages -->
<div class="section-title">
    <h2>🪙 Prepaid AI Tokens</h2>
    <p class="text-muted">Buy tokens in advance and save money</p>
</div>

<div class="row">
    <?php foreach ($tokenPackages as $package): ?>
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card token-card h-100">
                <div class="card-body text-center p-4">
                    <div class="token-amount"><?= number_format($package['tokens']) ?></div>
                    <p class="text-muted mb-2">AI Tokens</p>
                    
                    <?php if ($package['bonus'] > 0): ?>
                        <div class="bonus-badge mb-3">
                            +<?= number_format($package['bonus']) ?> Bonus!
                        </div>
                    <?php endif; ?>
                    
                    <div class="h4 text-success mb-3">$<?= $package['price'] ?></div>
                    
                    <p class="small text-muted mb-3">
                        $<?= number_format($package['price'] / ($package['tokens'] + $package['bonus']), 4) ?> per token
                    </p>
                    
                    <button class="btn btn-outline-primary btn-purchase w-100" 
                            onclick="checkout('tokens', '<?= $package['id'] ?>', <?= $package['price'] ?>)">
                        Buy Tokens
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Features Section -->
<div class="row mt-5 pt-5 border-top">
    <div class="col-md-4 text-center mb-4">
        <div class="h1 text-primary">🔒</div>
        <h5>Secure Payments</h5>
        <p class="text-muted">All payments processed securely through Stripe</p>
    </div>
    <div class="col-md-4 text-center mb-4">
        <div class="h1 text-primary">⚡</div>
        <h5>Instant Activation</h5>
        <p class="text-muted">Your plan activates immediately after payment</p>
    </div>
    <div class="col-md-4 text-center mb-4">
        <div class="h1 text-primary">🔄</div>
        <h5>Cancel Anytime</h5>
        <p class="text-muted">No long-term contracts, cancel whenever you want</p>
    </div>
</div>

<script>
function checkout(type, productId, price) {
    // Redirect to checkout page with product details
    const params = new URLSearchParams({
        type: type,
        product: productId,
        price: price
    });
    
    window.location.href = '/web/checkout.php?' + params.toString();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>