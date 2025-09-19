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
            
            <div class="subscription-plans">
                <?php foreach ($plans as $plan): ?>
                    <div class="plan-card <?= $plan['is_featured'] ? 'featured' : '' ?>">
                        <div class="plan-header">
                            <h3><?= htmlspecialchars($plan['name']) ?></h3>
                            <div class="plan-price">
                                $<?= number_format($plan['price'], 2) ?>
                                <small>/<?= htmlspecialchars($plan['billing_cycle']) ?></small>
                            </div>
                            <p><?= htmlspecialchars($plan['description']) ?></p>
                        </div>
                        <ul class="plan-features">
                            <li><strong>Users:</strong> Number of users allowed</li>
                            <li><strong>CRM Access:</strong> Customer relationship management</li>
                            <li><strong>AI Features:</strong> Artificial intelligence capabilities</li>
                            <li><strong>Support:</strong> Customer support level</li>
                            <li><strong>Reports:</strong> Advanced reporting and analytics</li>
                        </ul>
                        <button class="btn btn-<?= $plan['is_featured'] ? 'primary' : 'outline-primary' ?> w-100">
                            Choose Plan
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>