<?php
$pageTitle = 'Subscription Plans Management';
require_once __DIR__ . '/../../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

include __DIR__ . '/../includes/header.php';

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO subscription_plans (name, description, price, billing_cycle, features, is_featured, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'], $_POST['description'], $_POST['price'], $_POST['billing_cycle'],
                json_encode(array_filter(explode("\n", $_POST['features']))),
                isset($_POST['is_featured']) ? 1 : 0,
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['sort_order']
            ]);
            $success = 'Plan created successfully!';
        }
        
        if ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE subscription_plans SET name=?, description=?, price=?, billing_cycle=?, features=?, is_featured=?, is_active=?, sort_order=? WHERE id=?");
            $stmt->execute([
                $_POST['name'], $_POST['description'], $_POST['price'], $_POST['billing_cycle'],
                json_encode(array_filter(explode("\n", $_POST['features']))),
                isset($_POST['is_featured']) ? 1 : 0,
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['sort_order'], $_POST['plan_id']
            ]);
            $success = 'Plan updated successfully!';
        }
        
        if ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?");
            $stmt->execute([$_POST['plan_id']]);
            $success = 'Plan deleted successfully!';
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Create table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscription_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            billing_cycle TEXT DEFAULT 'month',
            features TEXT,
            is_featured BOOLEAN DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS subscription_services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS plan_services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plan_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            value TEXT,
            FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
            FOREIGN KEY (service_id) REFERENCES subscription_services(id)
        )
    ");
    
    // Insert default plans if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("
            INSERT INTO subscription_plans (name, description, price, billing_cycle, features, is_featured, is_active, sort_order) VALUES 
            ('Free', 'Perfect for getting started', 0.00, 'month', '[\"1 User\", \"Basic CRM\", \"Email Support\"]', 0, 1, 1),
            ('Pro', 'Best for growing businesses', 29.99, 'month', '[\"5 Users\", \"Advanced CRM\", \"AI Features\", \"Priority Support\", \"Custom Reports\"]', 1, 1, 2),
            ('Enterprise', 'For large organizations', 99.99, 'month', '[\"Unlimited Users\", \"Full CRM Suite\", \"Advanced AI\", \"24/7 Support\", \"Custom Integration\", \"White Label\"]', 0, 1, 3)
        ");
        
        // Insert default services
        $pdo->exec("
            INSERT INTO subscription_services (name, description, sort_order) VALUES 
            ('Users', 'Number of users allowed', 1),
            ('CRM Access', 'Customer relationship management features', 2),
            ('AI Features', 'Artificial intelligence capabilities', 3),
            ('Support Level', 'Customer support tier', 4),
            ('Custom Reports', 'Advanced reporting and analytics', 5)
        ");
        
        $pdo->exec("
            INSERT INTO plan_services (plan_id, service_id, value) VALUES 
            (1, 1, '1 User'), (1, 2, 'Basic'), (1, 3, 'Limited'), (1, 4, 'Email'), (1, 5, 'No'),
            (2, 1, '5 Users'), (2, 2, 'Full'), (2, 3, 'Advanced'), (2, 4, 'Priority'), (2, 5, 'Yes'),
            (3, 1, 'Unlimited'), (3, 2, 'Enterprise'), (3, 3, 'Full AI Suite'), (3, 4, '24/7'), (3, 5, 'Custom')
        ");
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get all plans
try {
    $plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [];
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">Subscription Plans Management</h1>
                <button class="btn btn-primary" onclick="toggleCollapse('addPlanForm')">
                    <i class="fas fa-plus"></i> Add Plan
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Add Plan Form -->
    <div class="card mb-4 collapse" id="addPlanForm" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Add New Plan</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Plan Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Price *</label>
                        <input type="number" step="0.01" class="form-control" name="price" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Billing Cycle</label>
                        <select class="form-select" name="billing_cycle">
                            <option value="month">Monthly</option>
                            <option value="year">Yearly</option>
                            <option value="week">Weekly</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Features (one per line)</label>
                        <textarea class="form-control" name="features" rows="3" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" value="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured">
                            <label class="form-check-label" for="is_featured">Featured Plan</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Plan</button>
                <button type="button" class="btn btn-secondary" onclick="toggleCollapse('addPlanForm')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Plans List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Subscription Plans (<?= count($plans) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($plans)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No plans found. Add your first plan above.</p>
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
                            <ul class="plan-features">
                                <?php 
                                // Get services for this plan
                                $serviceStmt = $pdo->query("SELECT * FROM subscription_services WHERE is_active = 1 ORDER BY sort_order");
                                $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($services as $service):
                                    // Get plan service value
                                    $valueStmt = $pdo->prepare("SELECT value FROM plan_services WHERE plan_id = ? AND service_id = ?");
                                    $valueStmt->execute([$plan['id'], $service['id']]);
                                    $planService = $valueStmt->fetch(PDO::FETCH_ASSOC);
                                    $value = $planService ? $planService['value'] : '✓';
                                ?>
                                    <li><strong><?= htmlspecialchars($service['name']) ?>:</strong> <?= htmlspecialchars($value) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div style="margin-top: 20px;">
                                <button onclick="editPlan(<?= $plan['id'] ?>)" class="btn btn-warning" data-plan='<?= json_encode($plan) ?>'>Edit</button>
                                <button onclick="deletePlan(<?= $plan['id'] ?>)" class="btn btn-danger">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Plan</h5>
                <button type="button" class="btn-close" onclick="closeModal('editPlanModal')"></button>
            </div>
            <form method="POST" id="editPlanForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="plan_id" id="editPlanId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plan Name *</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Price *</label>
                            <input type="number" step="0.01" class="form-control" name="price" id="editPrice" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Billing Cycle</label>
                            <select class="form-select" name="billing_cycle" id="editBillingCycle">
                                <option value="month">Monthly</option>
                                <option value="year">Yearly</option>
                                <option value="week">Weekly</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Features (one per line)</label>
                            <textarea class="form-control" name="features" id="editFeatures" rows="3"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="editSortOrder">
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="editIsFeatured">
                                <label class="form-check-label" for="editIsFeatured">Featured Plan</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                                <label class="form-check-label" for="editIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editPlanModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editPlan(id) {
    console.log('Edit plan clicked:', id);
    const button = document.querySelector(`button[onclick="editPlan(${id})"]`);
    const planData = JSON.parse(button.getAttribute('data-plan'));
    
    document.getElementById('editPlanId').value = planData.id;
    document.getElementById('editName').value = planData.name || '';
    document.getElementById('editPrice').value = planData.price || '';
    document.getElementById('editBillingCycle').value = planData.billing_cycle || 'month';
    document.getElementById('editDescription').value = planData.description || '';
    document.getElementById('editSortOrder').value = planData.sort_order || 0;
    document.getElementById('editIsFeatured').checked = planData.is_featured == 1;
    document.getElementById('editIsActive').checked = planData.is_active == 1;
    
    // Handle features
    const features = JSON.parse(planData.features || '[]');
    document.getElementById('editFeatures').value = features.join('\n');
    
    const modal = document.getElementById('editPlanModal');
    console.log('Modal element:', modal);
    modal.style.display = 'block';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
}

function deletePlan(id) {
    if (confirm('Are you sure you want to delete this plan?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="plan_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}


</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>