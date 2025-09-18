<?php
$pageTitle = 'Locations - ZeroAI CRM';
$currentPage = 'locations';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    ob_start();
    include __DIR__ . '/includes/header.php';
    
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO locations (company_id, name, type, address, city, state, zip, country, phone, email, notes, organization_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['company_id'], $_POST['name'], $_POST['type'], $_POST['address'],
                $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'],
                $_POST['phone'], $_POST['email'], $_POST['notes'], $userOrgId, $currentUser
            ]);
            ob_end_clean();
            header('Location: /web/locations.php?success=created');
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE locations SET company_id=?, name=?, type=?, address=?, city=?, state=?, zip=?, country=?, phone=?, email=?, notes=? WHERE id=?");
                $stmt->execute([
                    $_POST['company_id'], $_POST['name'], $_POST['type'], $_POST['address'],
                    $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'],
                    $_POST['phone'], $_POST['email'], $_POST['notes'], $_POST['location_id']
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE locations SET company_id=?, name=?, type=?, address=?, city=?, state=?, zip=?, country=?, phone=?, email=?, notes=? WHERE id=? AND created_by=?");
                $stmt->execute([
                    $_POST['company_id'], $_POST['name'], $_POST['type'], $_POST['address'],
                    $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country'],
                    $_POST['phone'], $_POST['email'], $_POST['notes'], $_POST['location_id'], $currentUser
                ]);
            }
            ob_end_clean();
            header('Location: /web/locations.php?success=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
                $stmt->execute([$_POST['location_id']]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ? AND created_by = ?");
                $stmt->execute([$_POST['location_id'], $currentUser]);
            }
            ob_end_clean();
            header('Location: /web/locations.php?success=deleted');
            exit;
        }
    } catch (Exception $e) {
        ob_end_clean();
        $error = "Error: " . $e->getMessage();
    }
} else {
    include __DIR__ . '/includes/header.php';
}

// Create locations table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER,
        name TEXT NOT NULL,
        type TEXT DEFAULT 'office',
        address TEXT,
        city TEXT,
        state TEXT,
        zip TEXT,
        country TEXT DEFAULT 'USA',
        phone TEXT,
        email TEXT,
        notes TEXT,
        organization_id VARCHAR(10),
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table exists or creation failed
}

// Get locations with company info
try {
    if ($isAdmin) {
        $sql = "SELECT l.*, c.name as company_name FROM locations l 
                LEFT JOIN companies c ON l.company_id = c.id 
                ORDER BY l.name";
        $locations = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $availableCompanies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT l.*, c.name as company_name FROM locations l 
                LEFT JOIN companies c ON l.company_id = c.id 
                WHERE l.organization_id = ? ORDER BY l.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userOrgId]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name");
        $stmt->execute([$userOrgId]);
        $availableCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $locations = [];
    $availableCompanies = [];
    $error = "Database error: " . $e->getMessage();
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created': $success = 'Location created successfully!'; break;
        case 'updated': $success = 'Location updated successfully!'; break;
        case 'deleted': $success = 'Location deleted successfully!'; break;
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5>
            <button class="btn btn-link p-0 text-decoration-none" type="button" onclick="toggleCollapse('addLocationForm')" aria-expanded="false">
                <i class="fas fa-plus-circle"></i> Add New Location
            </button>
        </h5>
    </div>
    <div class="collapse" id="addLocationForm" style="display: none;">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Location Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company *</label>
                        <select class="form-select" name="company_id" required>
                            <option value="">Select Company</option>
                            <?php foreach ($availableCompanies as $company): ?>
                                <option value="<?= htmlspecialchars($company['id']) ?>"><?= htmlspecialchars($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Location Type</label>
                        <select class="form-select" name="type">
                            <option value="office">Office</option>
                            <option value="warehouse">Warehouse</option>
                            <option value="retail">Retail Store</option>
                            <option value="factory">Factory</option>
                            <option value="branch">Branch Office</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address">
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
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Location</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Locations List (<?= count($locations) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($locations)): ?>
            <div class="text-center py-4">
                <i class="fas fa-map-marker-alt fa-3x mb-3 text-muted"></i>
                <p class="text-muted">No locations found. Add your first location above.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Address</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><?= htmlspecialchars($location['name']) ?></td>
                                <td><?= htmlspecialchars($location['company_name'] ?? 'No Company') ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars(ucfirst($location['type'] ?? 'office')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($location['address']): ?>
                                        <?= htmlspecialchars($location['address']) ?>
                                        <?php if ($location['city']): ?>, <?= htmlspecialchars($location['city']) ?><?php endif; ?>
                                        <?php if ($location['state']): ?>, <?= htmlspecialchars($location['state']) ?><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($location['phone'] ?? '') ?></td>
                                <td>
                                    <button onclick="editLocation(<?= htmlspecialchars($location['id']) ?>)" class="btn btn-sm btn-warning" data-location='<?= json_encode($location) ?>'>Edit</button>
                                    <button onclick="deleteLocation(<?= htmlspecialchars($location['id']) ?>)" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Location Modal -->
<div class="modal fade" id="editLocationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Location</h5>
                <button type="button" class="btn-close" onclick="closeModal('editLocationModal')"></button>
            </div>
            <form method="POST" id="editLocationForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="location_id" id="editLocationId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location Name *</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company *</label>
                            <select class="form-select" name="company_id" id="editCompanyId" required>
                                <option value="">Select Company</option>
                                <?php foreach ($availableCompanies as $company): ?>
                                    <option value="<?= htmlspecialchars($company['id']) ?>"><?= htmlspecialchars($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location Type</label>
                            <select class="form-select" name="type" id="editType">
                                <option value="office">Office</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="retail">Retail Store</option>
                                <option value="factory">Factory</option>
                                <option value="branch">Branch Office</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="editPhone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" id="editAddress">
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
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="editNotes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editLocationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Location</button>
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

function editLocation(id) {
    const button = document.querySelector(`button[onclick="editLocation(${id})"]`);
    const locationData = JSON.parse(button.getAttribute('data-location'));
    
    // Populate form fields
    document.getElementById('editLocationId').value = locationData.id;
    document.getElementById('editName').value = locationData.name || '';
    document.getElementById('editCompanyId').value = locationData.company_id || '';
    document.getElementById('editType').value = locationData.type || 'office';
    document.getElementById('editPhone').value = locationData.phone || '';
    document.getElementById('editEmail').value = locationData.email || '';
    document.getElementById('editAddress').value = locationData.address || '';
    document.getElementById('editCity').value = locationData.city || '';
    document.getElementById('editState').value = locationData.state || '';
    document.getElementById('editZip').value = locationData.zip || '';
    document.getElementById('editCountry').value = locationData.country || '';
    document.getElementById('editNotes').value = locationData.notes || '';
    
    // Show modal
    document.getElementById('editLocationModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function deleteLocation(id) {
    if (confirm('Are you sure you want to delete this location?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="location_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editLocationModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>