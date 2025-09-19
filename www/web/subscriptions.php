<?php
$pageTitle = 'Subscription Plans - ZeroAI';
$currentPage = 'subscriptions';

include __DIR__ . '/includes/header.php';

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
?>

<style>
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
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>💎 Subscription Plans</h1>
        <p class="text-muted">Choose the perfect plan for your business needs</p>
    </div>
    <a href="/web/store.php" class="btn btn-outline-secondary">← Back to Store</a>
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

<div class="alert alert-info">
    <h5><i class="fas fa-gift me-2"></i>14-Day Free Trial</h5>
    <p class="mb-0">All plans include a 14-day free trial. No credit card required to start.</p>
</div>

<script>
function checkout(type, productId, price) {
    const params = new URLSearchParams({
        type: type,
        product: productId,
        price: price
    });
    
    window.location.href = '/web/checkout.php?' + params.toString();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>