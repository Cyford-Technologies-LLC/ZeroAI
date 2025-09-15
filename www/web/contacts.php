<?php
include __DIR__ . '/includes/header.php';

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO contacts (company_id, first_name, middle_name, last_name, email, phone, mobile, position, department, address, city, state, zip_code, country, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([
            $_POST['company_id'], $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'],
            $_POST['email'], $_POST['phone'], $_POST['mobile'], $_POST['position'], $_POST['department'],
            $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip_code'], $_POST['country'], $currentUser
        ]);
        $success = "Contact added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get contacts with company info
try {
    $sql = "SELECT c.*, comp.name as company_name FROM contacts c 
            LEFT JOIN companies comp ON c.company_id = comp.id 
            ORDER BY c.last_name, c.first_name";
    $contacts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for dropdown
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $contacts = [];
    $companies = [];
    $error = "Database error: " . $e->getMessage();
}


?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Add New Contact</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company</label>
                                <select class="form-select" name="company_id">
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position">
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
                                <label class="form-label">Mobile</label>
                                <input type="text" class="form-control" name="mobile">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" name="zip_code">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="USA">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Contact</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Contacts List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($contacts)): ?>
                        <p>No contacts found. Add your first contact above.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Company</th>
                                        <th>Position</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
                                            <td><?= htmlspecialchars($contact['company_name'] ?? 'No Company') ?></td>
                                            <td><?= htmlspecialchars($contact['position']) ?></td>
                                            <td><?= htmlspecialchars($contact['email']) ?></td>
                                            <td><?= htmlspecialchars($contact['phone']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

