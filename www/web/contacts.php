<?php
$pageTitle = 'Contacts - ZeroAI CRM';
$currentPage = 'contacts';
include __DIR__ . '/includes/header.php';

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO contacts (company_id, first_name, last_name, email, phone, mobile, position, department, address, city, state, zip_code, country, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['company_id'] ?: null, $_POST['first_name'], $_POST['last_name'],
                $_POST['email'], $_POST['phone'], $_POST['mobile'], $_POST['position'], $_POST['department'],
                $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip_code'], $_POST['country'], $_POST['notes'], $currentUser
            ]);
            header('Location: /web/contacts.php?success=created');
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE contacts SET company_id=?, first_name=?, last_name=?, email=?, phone=?, mobile=?, position=?, department=?, address=?, city=?, state=?, zip_code=?, country=?, notes=? WHERE id=?");
                $stmt->execute([
                    $_POST['company_id'] ?: null, $_POST['first_name'], $_POST['last_name'],
                    $_POST['email'], $_POST['phone'], $_POST['mobile'], $_POST['position'], $_POST['department'],
                    $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip_code'], $_POST['country'], $_POST['notes'], $_POST['contact_id']
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE contacts SET company_id=?, first_name=?, last_name=?, email=?, phone=?, mobile=?, position=?, department=?, address=?, city=?, state=?, zip_code=?, country=?, notes=? WHERE id=? AND created_by=?");
                $stmt->execute([
                    $_POST['company_id'] ?: null, $_POST['first_name'], $_POST['last_name'],
                    $_POST['email'], $_POST['phone'], $_POST['mobile'], $_POST['position'], $_POST['department'],
                    $_POST['address'], $_POST['city'], $_POST['state'], $_POST['zip_code'], $_POST['country'], $_POST['notes'], $_POST['contact_id'], $currentUser
                ]);
            }
            header('Location: /web/contacts.php?success=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
                $stmt->execute([$_POST['contact_id']]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND created_by = ?");
                $stmt->execute([$_POST['contact_id'], $currentUser]);
            }
            header('Location: /web/contacts.php?success=deleted');
            exit;
        }
        
        if ($_POST['action'] === 'import_companies') {
            $companies_data = json_decode($_POST['companies_json'], true);
            $imported = 0;
            foreach ($companies_data as $company) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO companies (name, email, phone, address, city, state, zip_code, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $company['name'], $company['email'] ?? '', $company['phone'] ?? '', 
                    $company['address'] ?? '', $company['city'] ?? '', $company['state'] ?? '', 
                    $company['zip_code'] ?? '', $company['country'] ?? 'USA'
                ]);
                $imported++;
            }
            header("Location: /web/contacts.php?success=imported&count=$imported");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get contacts with company info
try {
    if ($isAdmin) {
        $sql = "SELECT c.*, comp.name as company_name FROM contacts c 
                LEFT JOIN companies comp ON c.company_id = comp.id 
                ORDER BY c.last_name, c.first_name";
        $contacts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT c.*, comp.name as company_name FROM contacts c 
                LEFT JOIN companies comp ON c.company_id = comp.id 
                WHERE c.created_by = ? ORDER BY c.last_name, c.first_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUser]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE created_by = ? ORDER BY name");
        $stmt->execute([$currentUser]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $contacts = [];
    $companies = [];
    $error = "Database error: " . $e->getMessage();
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created': $success = 'Contact created successfully!'; break;
        case 'updated': $success = 'Contact updated successfully!'; break;
        case 'deleted': $success = 'Contact deleted successfully!'; break;
        case 'imported': $success = 'Companies imported successfully! Count: ' . ($_GET['count'] ?? 0); break;
    }
}


?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <img src="/assets/frontend/images/icons/users.svg" width="24" height="24" class="me-2"> Contacts
                </h1>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" onclick="toggleCollapse('importForm')">
                        <i class="fas fa-upload"></i> Import Companies
                    </button>
                    <button class="btn btn-primary" onclick="toggleCollapse('addContactForm')">
                        <i class="fas fa-plus"></i> Add Contact
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Import Companies Form -->
    <div class="card mb-4 collapse" id="importForm" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Import Companies</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="import_companies">
                <div class="mb-3">
                    <label class="form-label">Companies JSON Data</label>
                    <textarea class="form-control" name="companies_json" rows="10" placeholder='[{"name":"Company Name","email":"contact@company.com","phone":"123-456-7890","address":"123 Main St","city":"City","state":"State","zip_code":"12345","country":"USA"}]' required></textarea>
                    <div class="form-text">Paste JSON array of companies to import. Each company should have: name (required), email, phone, address, city, state, zip_code, country</div>
                </div>
                <button type="submit" class="btn btn-success">Import Companies</button>
                <button type="button" class="btn btn-secondary" onclick="toggleCollapse('importForm')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Contact Form -->
    <div class="card mb-4 collapse" id="addContactForm" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Add New Contact</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company_id">
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= htmlspecialchars($company['id']) ?>"><?= htmlspecialchars($company['name']) ?></option>
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
                        <input type="text" class="form-control" name="zip_code">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Country</label>
                        <input type="text" class="form-control" name="country" value="USA">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Contact</button>
                <button type="button" class="btn btn-secondary" onclick="toggleCollapse('addContactForm')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Contacts List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Contacts List (<?= count($contacts) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($contacts)): ?>
                <div class="text-center py-4">
                    <img src="/assets/frontend/images/icons/users.svg" width="64" height="64" class="mb-3 opacity-50">
                    <p class="text-muted">No contacts found. Add your first contact or import companies above.</p>
                </div>
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
                                    <td><?= htmlspecialchars($contact['company_name'] ?? 'No Company') ?></td>
                                    <td><?= htmlspecialchars($contact['position'] ?? '') ?></td>
                                    <td>
                                        <?php if ($contact['email']): ?>
                                            <a href="mailto:<?= htmlspecialchars($contact['email']) ?>"><?= htmlspecialchars($contact['email']) ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($contact['phone'] ?? '') ?></td>
                                    <td>
                                        <button onclick="editContact(<?= htmlspecialchars($contact['id']) ?>)" class="btn btn-sm btn-warning" data-contact='<?= json_encode($contact) ?>'>Edit</button>
                                        <button onclick="deleteContact(<?= htmlspecialchars($contact['id']) ?>)" class="btn btn-sm btn-danger">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Contact</h5>
                <button type="button" class="btn-close" onclick="closeModal('editContactModal')"></button>
            </div>
            <form method="POST" id="editContactForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="contact_id" id="editContactId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="editLastName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company</label>
                            <select class="form-select" name="company_id" id="editCompanyId">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= htmlspecialchars($company['id']) ?>"><?= htmlspecialchars($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position" id="editPosition">
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
                            <label class="form-label">Mobile</label>
                            <input type="text" class="form-control" name="mobile" id="editMobile">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" id="editDepartment">
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
                            <input type="text" class="form-control" name="zip_code" id="editZipCode">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" id="editCountry">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="editAddress" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="editNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editContactModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Contact</button>
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

function editContact(id) {
    const button = document.querySelector(`button[onclick="editContact(${id})"]`);
    const contactData = JSON.parse(button.getAttribute('data-contact'));
    
    // Populate form fields
    document.getElementById('editContactId').value = contactData.id;
    document.getElementById('editFirstName').value = contactData.first_name || '';
    document.getElementById('editLastName').value = contactData.last_name || '';
    document.getElementById('editCompanyId').value = contactData.company_id || '';
    document.getElementById('editPosition').value = contactData.position || '';
    document.getElementById('editEmail').value = contactData.email || '';
    document.getElementById('editPhone').value = contactData.phone || '';
    document.getElementById('editMobile').value = contactData.mobile || '';
    document.getElementById('editDepartment').value = contactData.department || '';
    document.getElementById('editCity').value = contactData.city || '';
    document.getElementById('editState').value = contactData.state || '';
    document.getElementById('editZipCode').value = contactData.zip_code || '';
    document.getElementById('editCountry').value = contactData.country || '';
    document.getElementById('editAddress').value = contactData.address || '';
    document.getElementById('editNotes').value = contactData.notes || '';
    
    // Show modal
    document.getElementById('editContactModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function deleteContact(id) {
    if (confirm('Are you sure you want to delete this contact?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="contact_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editContactModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

