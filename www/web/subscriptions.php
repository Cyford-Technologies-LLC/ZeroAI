<?php
$pageTitle = 'Subscriptions - ZeroAI CRM';
$currentPage = 'subscriptions';
include __DIR__ . '/includes/header.php';

// Get subscription plans and services
try {
    $plans = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order, price")->fetchAll(PDO::FETCH_ASSOC);
    $services = $pdo->query("SELECT * FROM subscription_services WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [];
    $services = [];
}
?>

<style>
.subscription-plans {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.plan-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.plan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
}

.plan-card.featured {
    border: 3px solid #007bff;
    transform: scale(1.05);
}

.plan-card.featured::before {
    content: 'Most Popular';
    position: absolute;
    top: 0;
    right: 0;
    background: #007bff;
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: bold;
    border-bottom-left-radius: 8px;
}

.plan-header {
    padding: 2rem;
    text-align: center;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.plan-card.featured .plan-header {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
}

.plan-header h3 {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.plan-price {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 1rem 0;
}

.plan-price small {
    font-size: 1rem;
    opacity: 0.7;
}

.plan-features {
    padding: 1.5rem;
}

.feature-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #eee;
}

.feature-row:last-child {
    border-bottom: none;
}

.feature-name {
    font-weight: 500;
    color: #495057;
}

.feature-value {
    font-weight: bold;
    color: #007bff;
}

.plan-actions {
    padding: 1.5rem;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .subscription-plans {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .plan-card.featured {
        transform: none;
    }
}
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="text-center mb-5">
                <h1 class="h2 mb-3">Choose Your ZeroAI Plan</h1>
                <p class="lead text-muted">Zero Cost. Zero Cloud. Zero Limits. Build your AI workforce.</p>
            </div>
            
            <?php if (empty($plans)): ?>
                <div class="text-center py-5">
                    <h3>No subscription plans available</h3>
                    <p class="text-muted">Please contact your administrator to set up subscription plans.</p>
                </div>
            <?php else: ?>
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
                            <div class="plan-features">
                                <?php foreach ($services as $service): ?>
                                    <?php 
                                    $valueStmt = $pdo->prepare("SELECT value FROM plan_services WHERE plan_id = ? AND service_id = ?");
                                    $valueStmt->execute([$plan['id'], $service['id']]);
                                    $planService = $valueStmt->fetch(PDO::FETCH_ASSOC);
                                    $value = $planService ? $planService['value'] : 'Not included';
                                    ?>
                                    <div class="feature-row">
                                        <span class="feature-name"><?= htmlspecialchars($service['name']) ?></span>
                                        <span class="feature-value"><?= htmlspecialchars($value) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="plan-actions">
                                <button class="btn btn-<?= $plan['is_featured'] ? 'primary' : 'outline-primary' ?> w-100 btn-lg">
                                    <?= $plan['price'] == 0 ? 'Get Started Free' : 'Choose Plan' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>