<?php
$pageTitle = 'Companies - ZeroAI CRM';
$currentPage = 'companies';

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    // Start output buffering to prevent header issues
    ob_start();
    
    // Include header after form processing
    include __DIR__ . '/includes/header.php';
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
            ob_end_clean();
            header('Location: /web/companies.php?success=added');
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE companies SET name=?, ein=?, business_id=?, email=?, phone=?, street=?, street2=?, city=?, state=?, zip=?, country=?, website=?, linkedin=?, industry=?, about=? WHERE id=?");
                $stmt->execute([$_POST['name'], $_POST['ein'], $_POST['business_id'], $_POST['email'], $_POST['phone'], $_POST['street'], $_POST['street2'], $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'], $_POST['website'], $_POST['linkedin'], $_POST['industry'], $_POST['about'], $_POST['company_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE companies SET name=?, ein=?, business_id=?, email=?, phone=?, street=?, street2=?, city=?, state=?, zip=?, country=?, website=?, linkedin=?, industry=?, about=? WHERE id=? AND created_by=?");
                $stmt->execute([$_POST['name'], $_POST['ein'], $_POST['business_id'], $_POST['email'], $_POST['phone'], $_POST['street'], $_POST['street2'], $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'], $_POST['website'], $_POST['linkedin'], $_POST['industry'], $_POST['about'], $_POST['company_id'], $currentUser]);
            }
            ob_end_clean();
            header('Location: /web/companies.php?success=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
                $stmt->execute([$_POST['company_id']]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ? AND created_by = ?");
                $stmt->execute([$_POST['company_id'], $currentUser]);
            }
            ob_end_clean();
            header('Location: /web/companies.php?success=deleted');
            exit;
        }
    } catch (Exception $e) {
        ob_end_clean();
        $error = "Error: " . $e->getMessage();
    }
} else {
    // Include header for normal page load
    include __DIR__ . '/includes/header.php';
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
            <button class="btn btn-link p-0 text-decoration-none" type="button" onclick="toggleCollapse('addCompanyForm')" aria-expanded="false">
                <i class="fas fa-plus-circle"></i> Add New Company
            </button>
        </h5>
    </div>
    <div class="collapse" id="addCompanyForm" style="display: none;">
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
                            <th class="d-none d-md-table-cell">ID</th>
                            <th>Company</th>
                            <th class="d-none d-sm-table-cell">Organization ID</th>
                            <th class="d-none d-lg-table-cell">Phone</th>
                            <th class="d-none d-md-table-cell">Industry</th>
                            <?php if ($isAdmin): ?><th class="d-none d-xl-table-cell">Created By</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($company['id']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($company['name']) ?></div>
                                    <div class="d-sm-none small text-muted">Org: <?= htmlspecialchars($company['organization_id'] ?? '1') ?></div>
                                </td>
                                <td class="d-none d-sm-table-cell"><?= htmlspecialchars($company['organization_id'] ?? '1') ?></td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($company['phone']) ?></td>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($company['industry']) ?></td>
                                <?php if ($isAdmin): ?>
                                    <td class="d-none d-xl-table-cell"><?= htmlspecialchars($company['created_by'] ?? 'Unknown') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button onclick="editCompany(<?= $company['id'] ?>)" class="btn btn-warning btn-sm" data-company='<?= json_encode($company) ?>' title="Edit">✏️</button>
                                        <button onclick="deleteCompany(<?= $company['id'] ?>)" class="btn btn-danger btn-sm" title="Delete">🗑️</button>
                                    </div>
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
<div class="modal fade" id="editCompanyModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Company</h5>
                <button type="button" class="btn-close" onclick="closeModal('editCompanyModal')"></button>
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
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCompanyModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Company</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCollapse(id) {
    const element = document.getElementById(id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}

function editCompany(id) {
    // Find the button that was clicked to get company data
    const button = document.querySelector(`button[onclick="editCompany(${id})"]`);
    const companyData = JSON.parse(button.getAttribute('data-company'));
    
    // Populate form fields
    document.getElementById('editCompanyId').value = companyData.id;
    document.getElementById('editName').value = companyData.name || '';
    document.getElementById('editEin').value = companyData.ein || '';
    document.getElementById('editBusinessId').value = companyData.business_id || '';
    document.getElementById('editEmail').value = companyData.email || '';
    document.getElementById('editPhone').value = companyData.phone || '';
    document.getElementById('editWebsite').value = companyData.website || '';
    document.getElementById('editLinkedin').value = companyData.linkedin || '';
    document.getElementById('editIndustry').value = companyData.industry || '';
    document.getElementById('editAbout').value = companyData.about || '';
    document.getElementById('editStreet').value = companyData.street || '';
    document.getElementById('editStreet2').value = companyData.street2 || '';
    document.getElementById('editCity').value = companyData.city || '';
    document.getElementById('editState').value = companyData.state || '';
    document.getElementById('editZip').value = companyData.zip || '';
    document.getElementById('editCountry').value = companyData.country || '';
    
    // Show modal
    document.getElementById('editCompanyModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
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

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editCompanyModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>