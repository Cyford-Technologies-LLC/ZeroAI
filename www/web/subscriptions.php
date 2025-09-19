<?php
$pageTitle = 'Subscriptions - ZeroAI CRM';
$currentPage = 'subscriptions';
include __DIR__ . '/includes/header.php';

// Get subscription plans
try {
    $stmt = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order, price");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [];
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h2 mb-4">Choose Your Plan</h1>
            
            <div class="row">
                <?php foreach ($plans as $plan): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 <?= $plan['is_featured'] ? 'border-primary' : '' ?>">
                            <?php if ($plan['is_featured']): ?>
                                <div class="card-header bg-primary text-white text-center">
                                    <strong>Most Popular</strong>
                                </div>
                            <?php endif; ?>
                            <div class="card-body text-center">
                                <h3 class="card-title"><?= htmlspecialchars($plan['name']) ?></h3>
                                <div class="mb-3">
                                    <span class="h2">$<?= number_format($plan['price'], 2) ?></span>
                                    <span class="text-muted">/<?= htmlspecialchars($plan['billing_cycle']) ?></span>
                                </div>
                                <p class="card-text"><?= htmlspecialchars($plan['description']) ?></p>
                                
                                <?php if ($plan['features']): ?>
                                    <ul class="list-unstyled">
                                        <?php foreach (json_decode($plan['features'], true) as $feature): ?>
                                            <li class="mb-2">✓ <?= htmlspecialchars($feature) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-<?= $plan['is_featured'] ? 'primary' : 'outline-primary' ?> w-100">
                                    Choose Plan
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>