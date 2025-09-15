<?php
include __DIR__ . '/includes/header.php';

// Handle form submission
if ($_POST && isset($_POST['name'])) {
    try {
        // Combine address fields
        $address = trim(($_POST['street'] ?? '') . ' ' . ($_POST['street2'] ?? '') . ', ' . ($_POST['city'] ?? '') . ', ' . ($_POST['state'] ?? '') . ' ' . ($_POST['zip'] ?? ''));
        $address = trim($address, ', ');
        
        $stmt = $pdo->prepare("INSERT INTO companies (name, ein, business_id, email, phone, address, industry, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['ein'], $_POST['business_id'], $_POST['email'], $_POST['phone'], $address, $_POST['industry'], $userOrgId, $currentUser]);
        $success = "Company added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}



?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>
                        <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#addCompanyForm" aria-expanded="false">
                            <i class="fas fa-plus-circle"></i> Add New Company
                        </button>
                    </h5>
                </div>
                <div class="collapse" id="addCompanyForm">
                    <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">EIN</label>
                                <input type="text" class="form-control" name="ein" placeholder="XX-XXXXXXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Business ID</label>
                                <input type="text" class="form-control" name="business_id">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="website" placeholder="https://">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">LinkedIn</label>
                                <input type="url" class="form-control" name="linkedin" placeholder="https://linkedin.com/company/">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Industry</label>
                                <select class="form-select" name="industry">
                                    <option value="">Select Industry</option>
                                    <option value="Technology">Technology</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Manufacturing">Manufacturing</option>
                                    <option value="Retail">Retail</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">About the Company</label>
                                <textarea class="form-control" name="about" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Street Address</label>
                                <input type="text" class="form-control" name="street">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Street Address 2</label>
                                <input type="text" class="form-control" name="street2">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" name="zip">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="USA">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Company</button>
                    </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Companies List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($companies)): ?>
                        <p>No companies found. Add your first company above.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Industry</th>
                                        <?php if ($isAdmin): ?><th>Created By</th><th>Org ID</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companies as $company): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($company['name']) ?></td>
                                            <td><?= htmlspecialchars($company['email']) ?></td>
                                            <td><?= htmlspecialchars($company['phone']) ?></td>
                                            <td><?= htmlspecialchars($company['industry']) ?></td>
                                            <?php if ($isAdmin): ?>
                                                <td><?= htmlspecialchars($company['created_by'] ?? 'Unknown') ?></td>
                                                <td><?= htmlspecialchars($company['organization_id'] ?? '1') ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

<?php include __DIR__ . '/includes/footer.php'; ?>

