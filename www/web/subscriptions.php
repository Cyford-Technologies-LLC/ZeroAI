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
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <?php foreach ($plans as $plan): ?>
                                <th class="text-center <?= $plan['is_featured'] ? 'bg-primary text-white' : '' ?>">
                                    <div class="mb-2">
                                        <strong><?= htmlspecialchars($plan['name']) ?></strong>
                                        <?php if ($plan['is_featured']): ?>
                                            <br><small>Most Popular</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="h4 mb-2">
                                        $<?= number_format($plan['price'], 2) ?>
                                        <small class="text-muted">/<?= htmlspecialchars($plan['billing_cycle']) ?></small>
                                    </div>
                                    <p class="small mb-3"><?= htmlspecialchars($plan['description']) ?></p>
                                    <button class="btn btn-<?= $plan['is_featured'] ? 'light' : 'primary' ?> btn-sm w-100">
                                        Choose Plan
                                    </button>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Users</strong><br><small class="text-muted">Number of users allowed</small></td><?php foreach ($plans as $plan): ?><td class="text-center">✓ Included</td><?php endforeach; ?></tr>
                        <tr><td><strong>CRM Access</strong><br><small class="text-muted">Customer relationship management</small></td><?php foreach ($plans as $plan): ?><td class="text-center">✓ Included</td><?php endforeach; ?></tr>
                        <tr><td><strong>AI Features</strong><br><small class="text-muted">Artificial intelligence capabilities</small></td><?php foreach ($plans as $plan): ?><td class="text-center">✓ Included</td><?php endforeach; ?></tr>
                        <tr><td><strong>Support</strong><br><small class="text-muted">Customer support level</small></td><?php foreach ($plans as $plan): ?><td class="text-center">✓ Included</td><?php endforeach; ?></tr>
                        <tr><td><strong>Reports</strong><br><small class="text-muted">Advanced reporting and analytics</small></td><?php foreach ($plans as $plan): ?><td class="text-center">✓ Included</td><?php endforeach; ?></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>