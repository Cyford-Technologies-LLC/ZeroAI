<?php
$pageTitle = 'AI Token Packages - ZeroAI';
$currentPage = 'tokens';

include __DIR__ . '/includes/header.php';

$tokenPackages = [
    ['id' => 'tokens_1k', 'name' => '1,000 Tokens', 'tokens' => 1000, 'price' => 9.99, 'bonus' => 0],
    ['id' => 'tokens_5k', 'name' => '5,000 Tokens', 'tokens' => 5000, 'price' => 39.99, 'bonus' => 500],
    ['id' => 'tokens_10k', 'name' => '10,000 Tokens', 'tokens' => 10000, 'price' => 69.99, 'bonus' => 1500],
    ['id' => 'tokens_25k', 'name' => '25,000 Tokens', 'tokens' => 25000, 'price' => 149.99, 'bonus' => 5000],
    ['id' => 'tokens_50k', 'name' => '50,000 Tokens', 'tokens' => 50000, 'price' => 249.99, 'bonus' => 15000],
    ['id' => 'tokens_100k', 'name' => '100,000 Tokens', 'tokens' => 100000, 'price' => 399.99, 'bonus' => 35000],
];
?>

<style>
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

.token-amount {
    font-size: 2rem;
    font-weight: bold;
    color: #667eea;
}

.btn-purchase {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-purchase:hover {
    transform: translateY(-2px);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🪙 AI Token Packages</h1>
        <p class="text-muted">Buy tokens in advance and save money with bonus offers</p>
    </div>
    <a href="/web/store.php" class="btn btn-outline-secondary">← Back to Store</a>
</div>

<div class="row">
    <?php foreach ($tokenPackages as $package): ?>
        <div class="col-md-6 col-lg-4 mb-4">
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

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <div class="h2 text-primary">⚡</div>
                <h5>Instant Delivery</h5>
                <p class="text-muted mb-0">Tokens added to your account immediately</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <div class="h2 text-primary">💰</div>
                <h5>Volume Discounts</h5>
                <p class="text-muted mb-0">Save more with larger token packages</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <div class="h2 text-primary">🔄</div>
                <h5>Never Expire</h5>
                <p class="text-muted mb-0">Your tokens never expire, use them anytime</p>
            </div>
        </div>
    </div>
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