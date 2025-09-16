<?php
// Include header first to get database connection
include __DIR__ . '/includes/header.php';

// Handle form submission
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            // Check for duplicate name or email
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE name = ? OR email = ?");
            $checkStmt->execute([$_POST['name'], $_POST['email']]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Company name or email already exists');
            }
            
            $stmt = $pdo->prepare("INSERT INTO companies (name, ein, business_id, email, phone, street, street2, city, state, zip, country, website, linkedin, industry, about, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['ein'], $_POST['business_id'], $_POST['email'], $_POST['phone'], $_POST['street'], $_POST['street2'], $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'], $_POST['website'], $_POST['linkedin'], $_POST['industry'], $_POST['about'], $userOrgId, $currentUser]);
            header('Location: /web/companies.php?success=added');
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE companies SET name=?, ein=?, business_id=?, email=?, phone=?, street=?, street2=?, city=?, state=?, zip=?, country=?, website=?, linkedin=?, industry=?, about=? WHERE id=?");
            $stmt->execute([$_POST['name'], $_POST['ein'], $_POST['business_id'], $_POST['email'], $_POST['phone'], $_POST['street'], $_POST['street2'], $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'], $_POST['website'], $_POST['linkedin'], $_POST['industry'], $_POST['about'], $_POST['company_id']]);
            header('Location: /web/companies.php?success=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$_POST['company_id']]);
            header('Location: /web/companies.php?success=deleted');
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle success messages from redirects
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added': $success = 'Company added successfully!'; break;
        case 'updated': $success = 'Company updated successfully!'; break;
        case 'deleted': $success = 'Company deleted successfully!'; break;
    }
}
?>

<?php if ($success): ?>
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
                        <input type="hidden" name="action" value="create">
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
                                        <th>ID</th>
                                        <th>Company Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Industry</th>
                                        <?php if ($isAdmin): ?><th>Created By</th><th>Org ID</th><?php endif; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companies as $company): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($company['id']) ?></td>
                                            <td><?= htmlspecialchars($company['name']) ?></td>
                                            <td><?= htmlspecialchars($company['email']) ?></td>
                                            <td><?= htmlspecialchars($company['phone']) ?></td>
                                            <td><?= htmlspecialchars($company['industry']) ?></td>
                                            <?php if ($isAdmin): ?>
                                                <td><?= htmlspecialchars($company['created_by'] ?? 'Unknown') ?></td>
                                                <td><?= htmlspecialchars($company['organization_id'] ?? '1') ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <button onclick="editCompany(<?= $company['id'] ?>, '<?= htmlspecialchars($company['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($company['ein'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['business_id'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['phone'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['website'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['linkedin'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['industry'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['about'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['street'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['street2'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['city'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['state'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['zip'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($company['country'] ?? '', ENT_QUOTES) ?>')" class="btn btn-sm btn-warning">Edit</button>
                                                <button onclick="deleteCompany(<?= $company['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCompanyForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="company_id" id="editCompanyId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">EIN</label>
                            <input type="text" class="form-control" name="ein" id="editEin">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Business ID</label>
                            <input type="text" class="form-control" name="business_id" id="editBusinessId">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="editPhone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" class="form-control" name="website" id="editWebsite">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">LinkedIn</label>
                            <input type="url" class="form-control" name="linkedin" id="editLinkedin">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Industry</label>
                            <select class="form-select" name="industry" id="editIndustry">
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
                            <textarea class="form-control" name="about" id="editAbout" rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Street Address</label>
                            <input type="text" class="form-control" name="street" id="editStreet">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Street Address 2</label>
                            <input type="text" class="form-control" name="street2" id="editStreet2">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" id="editCity">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" id="editState">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ZIP Code</label>
                            <input type="text" class="form-control" name="zip" id="editZip">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" id="editCountry">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Company</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCompany(id, name, ein, businessId, email, phone, website, linkedin, industry, about, street, street2, city, state, zip, country) {
    document.getElementById('editCompanyId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEin').value = ein;
    document.getElementById('editBusinessId').value = businessId;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editWebsite').value = website;
    document.getElementById('editLinkedin').value = linkedin;
    document.getElementById('editIndustry').value = industry;
    document.getElementById('editAbout').value = about;
    document.getElementById('editStreet').value = street;
    document.getElementById('editStreet2').value = street2;
    document.getElementById('editCity').value = city;
    document.getElementById('editState').value = state;
    document.getElementById('editZip').value = zip;
    document.getElementById('editCountry').value = country;
    
    new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
}

function deleteCompany(id) {
    if (confirm('Are you sure you want to delete this company?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="company_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

