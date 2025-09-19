<?php
$pageTitle = 'Checkout - ZeroAI Store';
$currentPage = 'checkout';

// Get product details from URL
$type = $_GET['type'] ?? '';
$productId = $_GET['product'] ?? '';
$price = floatval($_GET['price'] ?? 0);

if (empty($type) || empty($productId) || $price <= 0) {
    header('Location: /web/store.php');
    exit;
}

// Product definitions
$products = [
    'subscription' => [
        'starter' => ['name' => 'Starter Plan', 'price' => 29, 'billing' => 'monthly'],
        'professional' => ['name' => 'Professional Plan', 'price' => 79, 'billing' => 'monthly'],
        'enterprise' => ['name' => 'Enterprise Plan', 'price' => 199, 'billing' => 'monthly']
    ],
    'tokens' => [
        'tokens_1k' => ['name' => '1,000 AI Tokens', 'price' => 9.99],
        'tokens_5k' => ['name' => '5,000 AI Tokens + 500 Bonus', 'price' => 39.99],
        'tokens_10k' => ['name' => '10,000 AI Tokens + 1,500 Bonus', 'price' => 69.99],
        'tokens_25k' => ['name' => '25,000 AI Tokens + 5,000 Bonus', 'price' => 149.99]
    ]
];

$product = $products[$type][$productId] ?? null;
if (!$product) {
    header('Location: /web/store.php');
    exit;
}

include __DIR__ . '/includes/header.php';

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    try {
        require_once __DIR__ . '/../src/Services/StripeIntegration.php';
        $stripe = new \ZeroAI\Services\StripeIntegration($userOrgId);
        
        if (!$stripe->isConfigured()) {
            throw new Exception('Payment processing not configured. Please contact support.');
        }
        
        // Create payment intent
        $paymentIntent = $stripe->createPaymentIntent(
            $price,
            'usd',
            [
                'type' => $type,
                'product_id' => $productId,
                'organization_id' => $userOrgId,
                'customer_email' => $_POST['email']
            ]
        );
        
        $success = "Payment initiated successfully! Payment Intent ID: " . $paymentIntent['id'];
        
    } catch (Exception $e) {
        $error = "Payment failed: " . $e->getMessage();
    }
}
?>

<style>
.checkout-container {
    max-width: 1000px;
    margin: 0 auto;
}

.checkout-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -20px 2rem -20px;
    text-align: center;
}

.order-summary {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.payment-form {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e9ecef;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #495057;
}

.price-breakdown {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    border: 2px solid #e9ecef;
}

.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.price-row.total {
    border-top: 2px solid #e9ecef;
    margin-top: 1rem;
    padding-top: 1rem;
    font-weight: bold;
    font-size: 1.25rem;
}

.security-badges {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}

.security-badge {
    background: #e8f5e8;
    color: #28a745;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.btn-pay {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    border-radius: 25px;
    padding: 1rem 2rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: white;
    width: 100%;
    transition: all 0.3s ease;
}

.btn-pay:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    color: white;
}

.product-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}
</style>

<div class="checkout-header">
    <h1>🛒 Secure Checkout</h1>
    <p>Complete your purchase securely</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?= $success ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
    </div>
<?php endif; ?>

<div class="checkout-container">
    <div class="row">
        <!-- Order Summary -->
        <div class="col-lg-5 mb-4">
            <div class="order-summary">
                <h4 class="mb-4">📋 Order Summary</h4>
                
                <div class="text-center mb-4">
                    <div class="product-icon">
                        <?= $type === 'subscription' ? '💎' : '🪙' ?>
                    </div>
                    <h5><?= htmlspecialchars($product['name']) ?></h5>
                    <?php if ($type === 'subscription'): ?>
                        <p class="text-muted">Billed <?= $product['billing'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="price-breakdown">
                    <div class="price-row">
                        <span><?= htmlspecialchars($product['name']) ?></span>
                        <span>$<?= number_format($price, 2) ?></span>
                    </div>
                    
                    <?php if ($type === 'subscription'): ?>
                        <div class="price-row">
                            <span>14-day free trial</span>
                            <span class="text-success">-$<?= number_format($price, 2) ?></span>
                        </div>
                        <div class="price-row total">
                            <span>Due Today</span>
                            <span>$0.00</span>
                        </div>
                        <div class="price-row">
                            <small class="text-muted">Next billing: <?= date('M j, Y', strtotime('+14 days')) ?></small>
                        </div>
                    <?php else: ?>
                        <div class="price-row total">
                            <span>Total</span>
                            <span>$<?= number_format($price, 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="security-badges">
                    <div class="security-badge">🔒 SSL Secured</div>
                    <div class="security-badge">💳 Stripe Protected</div>
                </div>
            </div>
        </div>
        
        <!-- Payment Form -->
        <div class="col-lg-7">
            <div class="payment-form">
                <form method="POST">
                    <input type="hidden" name="action" value="process_payment">
                    
                    <!-- Customer Information -->
                    <div class="form-section">
                        <div class="section-title">👤 Customer Information</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Billing Address -->
                    <div class="form-section">
                        <div class="section-title">📍 Billing Address</div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address Line 2 (Optional)</label>
                                <input type="text" class="form-control" name="address_line2">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" name="zip" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="form-section">
                        <div class="section-title">💳 Payment Method</div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Demo Mode:</strong> This is a demonstration. In production, this would integrate with Stripe Elements for secure card processing.
                        </div>
                        
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Card Number</label>
                                <input type="text" class="form-control" placeholder="4242 4242 4242 4242" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="text" class="form-control" placeholder="MM/YY" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CVC</label>
                                <input type="text" class="form-control" placeholder="123" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-pay">
                        <i class="fas fa-lock me-2"></i>
                        <?php if ($type === 'subscription'): ?>
                            Start Free Trial
                        <?php else: ?>
                            Complete Purchase - $<?= number_format($price, 2) ?>
                        <?php endif; ?>
                    </button>
                    
                    <p class="text-center text-muted mt-3 small">
                        By completing this purchase, you agree to our Terms of Service and Privacy Policy.
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// In production, this would initialize Stripe Elements
console.log('Checkout page loaded for:', {
    type: '<?= $type ?>',
    product: '<?= $productId ?>',
    price: <?= $price ?>
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>