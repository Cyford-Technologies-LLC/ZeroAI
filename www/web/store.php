<?php
$pageTitle = 'ZeroAI Store - Subscriptions & Tokens';
$currentPage = 'store';

include __DIR__ . '/includes/header.php';

// Store products
$subscriptions = [
    [
        'id' => 'starter',
        'name' => 'Starter Plan',
        'price' => 29,
        'billing' => 'monthly',
        'features' => ['5,000 AI Tokens/month', '10 Companies', '50 Contacts', 'Basic Integrations', 'Email Support'],
        'popular' => false
    ],
    [
        'id' => 'professional',
        'name' => 'Professional Plan',
        'price' => 79,
        'billing' => 'monthly',
        'features' => ['25,000 AI Tokens/month', '100 Companies', '500 Contacts', 'All Integrations', 'Priority Support', 'Advanced Analytics'],
        'popular' => true
    ],
    [
        'id' => 'enterprise',
        'name' => 'Enterprise Plan',
        'price' => 199,
        'billing' => 'monthly',
        'features' => ['Unlimited AI Tokens', 'Unlimited Companies', 'Unlimited Contacts', 'Custom Integrations', '24/7 Support', 'White-label Options'],
        'popular' => false
    ]
];

$tokenPackages = [
    ['id' => 'tokens_1k', 'name' => '1,000 Tokens', 'tokens' => 1000, 'price' => 9.99, 'bonus' => 0],
    ['id' => 'tokens_5k', 'name' => '5,000 Tokens', 'tokens' => 5000, 'price' => 39.99, 'bonus' => 500],
    ['id' => 'tokens_10k', 'name' => '10,000 Tokens', 'tokens' => 10000, 'price' => 69.99, 'bonus' => 1500],
    ['id' => 'tokens_25k', 'name' => '25,000 Tokens', 'tokens' => 25000, 'price' => 149.99, 'bonus' => 5000],
];
?>

<style>
.store-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin-bottom: 2rem;
    border-radius: 12px;
}

.pricing-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.pricing-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.pricing-card.popular {
    border: 3px solid #667eea;
    transform: scale(1.05);
}

.pricing-card.popular::before {
    content: "Most Popular";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: #667eea;
    color: white;
    text-align: center;
    padding: 0.5rem;
    font-weight: bold;
    font-size: 0.875rem;
}

.pricing-card.popular .card-body {
    padding-top: 3rem;
}

.price-display {
    font-size: 3rem;
    font-weight: bold;
    color: #667eea;
}

.token-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.token-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.bonus-badge {
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: bold;
}

.feature-list {
    list-style: none;
    padding: 0;
}

.feature-list li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.feature-list li:last-child {
    border-bottom: none;
}

.feature-list li::before {
    content: "✓";
    color: #28a745;
    font-weight: bold;
    margin-right: 0.5rem;
}

.btn-purchase {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.btn-purchase:hover {
    transform: translateY(-2px);
}

.section-title {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title h2 {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.token-amount {
    font-size: 2rem;
    font-weight: bold;
    color: #667eea;
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
                        <small class="text-muted" style="font-size: 1rem;">/{$plan['billing']}</small>
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