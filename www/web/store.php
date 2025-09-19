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

<h1 class="mb-4">🛒 ZeroAI Store</h1>
<p class="text-muted mb-4">Choose from our marketplace of plans, tokens, and services</p>

<div class="row">
    <!-- Subscription Plans -->
    <div class="col-md-6 col-lg-4 mb-4">
        <a href="/web/subscriptions.php" class="store-card card text-decoration-none">
            <div class="card-body text-center d-flex flex-column justify-content-center subscription-card">
                <div class="store-icon">💎</div>
                <h4>Subscription Plans</h4>
                <p class="mb-0">Monthly & yearly plans with AI tokens included</p>
                <small class="mt-2">Starting at $29/month</small>
            </div>
        </a>
    </div>
    
    <!-- AI Tokens -->
    <div class="col-md-6 col-lg-4 mb-4">
        <a href="/web/tokens.php" class="store-card card text-decoration-none">
            <div class="card-body text-center d-flex flex-column justify-content-center token-card">
                <div class="store-icon">🪙</div>
                <h4>AI Token Packages</h4>
                <p class="mb-0">Prepaid tokens with bonus offers</p>
                <small class="mt-2">Starting at $9.99</small>
            </div>
        </a>
    </div>
    
    <!-- Billing & Usage -->
    <div class="col-md-6 col-lg-4 mb-4">
        <a href="/web/billing.php" class="store-card card text-decoration-none">
            <div class="card-body text-center d-flex flex-column justify-content-center billing-card">
                <div class="store-icon">📊</div>
                <h4>Billing & Usage</h4>
                <p class="mb-0">Manage subscriptions and view usage</p>
                <small class="mt-2">Current plan & analytics</small>
            </div>
        </a>
    </div>
    
    <!-- Enterprise Solutions -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="store-card card">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white;">
                <div class="store-icon">🏢</div>
                <h4>Enterprise</h4>
                <p class="mb-0">Custom solutions for large teams</p>
                <small class="mt-2">Contact sales</small>
            </div>
        </div>
    </div>
    
    <!-- API Access -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="store-card card">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%); color: white;">
                <div class="store-icon">🔌</div>
                <h4>API Access</h4>
                <p class="mb-0">Integrate ZeroAI with your apps</p>
                <small class="mt-2">Developer plans</small>
            </div>
        </div>
    </div>
    
    <!-- White Label -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="store-card card">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white;">
                <div class="store-icon">🏷️</div>
                <h4>White Label</h4>
                <p class="mb-0">Brand ZeroAI as your own</p>
                <small class="mt-2">Custom branding</small>
            </div>
        </div>
    </div>
    
    <!-- Training & Support -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="store-card card">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white;">
                <div class="store-icon">🎓</div>
                <h4>Training & Support</h4>
                <p class="mb-0">Get expert help and training</p>
                <small class="mt-2">Premium support</small>
            </div>
        </div>
    </div>
    
    <!-- Add-ons -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="store-card card">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #495057 0%, #6c757d 100%); color: white;">
                <div class="store-icon">🧩</div>
                <h4>Add-ons</h4>
                <p class="mb-0">Extra features and integrations</p>
                <small class="mt-2">Extend functionality</small>
            </div>
        </div>
    </div>
    
    <!-- Gift Cards -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="store-card card">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%); color: white;">
                <div class="store-icon">🎁</div>
                <h4>Gift Cards</h4>
                <p class="mb-0">Give the gift of AI productivity</p>
                <small class="mt-2">Perfect for teams</small>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>