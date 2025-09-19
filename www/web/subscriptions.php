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

<div class="table-responsive mb-5">
    <table class="table table-bordered align-middle">
        <thead>
            <tr class="text-center">
                <th class="bg-light" style="width: 25%;">Features</th>
                <th class="bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 25%;">
                    <div class="py-3">
                        <h4 class="mb-1">Starter</h4>
                        <div class="h2 mb-1">$29<small class="fs-6">/month</small></div>
                        <small class="opacity-90">Perfect for small teams</small>
                    </div>
                </th>
                <th class="bg-gradient text-white position-relative" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); width: 25%;">
                    <span class="position-absolute top-0 start-50 translate-middle badge bg-warning text-dark px-2 py-1 rounded-pill" style="font-size: 0.7rem;">Most Popular</span>
                    <div class="py-3">
                        <h4 class="mb-1">Professional</h4>
                        <div class="h2 mb-1">$79<small class="fs-6">/month</small></div>
                        <small class="opacity-90">Best for growing businesses</small>
                    </div>
                </th>
                <th class="bg-gradient text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); width: 25%;">
                    <div class="py-3">
                        <h4 class="mb-1">Enterprise</h4>
                        <div class="h2 mb-1">$199<small class="fs-6">/month</small></div>
                        <small class="opacity-90">For large organizations</small>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="fw-bold bg-light">AI Tokens/Month</td>
                <td class="text-center">5,000</td>
                <td class="text-center">25,000</td>
                <td class="text-center">Unlimited</td>
            </tr>
            <tr>
                <td class="fw-bold bg-light">Companies</td>
                <td class="text-center">10</td>
                <td class="text-center">100</td>
                <td class="text-center">Unlimited</td>
            </tr>
            <tr>
                <td class="fw-bold bg-light">Contacts</td>
                <td class="text-center">50</td>
                <td class="text-center">500</td>
                <td class="text-center">Unlimited</td>
            </tr>
            <tr>
                <td class="fw-bold bg-light">Integrations</td>
                <td class="text-center">Basic</td>
                <td class="text-center">All</td>
                <td class="text-center">Custom</td>
            </tr>
            <tr>
                <td class="fw-bold bg-light">Support</td>
                <td class="text-center">Email</td>
                <td class="text-center">Priority</td>
                <td class="text-center">24/7</td>
            </tr>
            <tr>
                <td class="fw-bold bg-light">Analytics</td>
                <td class="text-center"><i class="fas fa-times text-muted"></i></td>
                <td class="text-center"><i class="fas fa-check text-success"></i> Advanced</td>
                <td class="text-center"><i class="fas fa-check text-success"></i> Advanced</td>
            </tr>
            <tr>
                <td class="fw-bold bg-light">White-label</td>
                <td class="text-center"><i class="fas fa-times text-muted"></i></td>
                <td class="text-center"><i class="fas fa-times text-muted"></i></td>
                <td class="text-center"><i class="fas fa-check text-success"></i></td>
            </tr>
            <tr>
                <td class="bg-light"></td>
                <td class="text-center py-3">
                    <button class="btn btn-primary btn-lg" onclick="checkout('subscription', 'starter', 29)">Choose Starter</button>
                </td>
                <td class="text-center py-3">
                    <button class="btn btn-primary btn-lg" onclick="checkout('subscription', 'professional', 79)">Choose Professional</button>
                </td>
                <td class="text-center py-3">
                    <button class="btn btn-primary btn-lg" onclick="checkout('subscription', 'enterprise', 199)">Choose Enterprise</button>
                </td>
            </tr>
        </tbody>
    </table>
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