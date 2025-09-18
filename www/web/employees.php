<?php
$pageTitle = 'Employees - ZeroAI CRM';
$currentPage = 'employees';
include __DIR__ . '/includes/header.php';

$companyId = $_GET['company_id'] ?? null;

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO employees (company_id, first_name, last_name, email, phone, position, department, hire_date, salary, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
            $stmt->execute([
                $_POST['company_id'], $_POST['first_name'], $_POST['last_name'],
                $_POST['email'], $_POST['phone'], $_POST['position'], $_POST['department'],
                $_POST['hire_date'], $_POST['salary'], $_POST['notes'], $currentUser
            ]);
            header('Location: /web/employees.php?success=created' . ($companyId ? '&company_id=' . $companyId : ''));
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE employees SET company_id=?, first_name=?, last_name=?, email=?, phone=?, position=?, department=?, hire_date=?, salary=?, status=?, notes=? WHERE id=?");
                $stmt->execute([
                    $_POST['company_id'], $_POST['first_name'], $_POST['last_name'],
                    $_POST['email'], $_POST['phone'], $_POST['position'], $_POST['department'],
                    $_POST['hire_date'], $_POST['salary'], $_POST['status'], $_POST['notes'], $_POST['employee_id']
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE employees SET company_id=?, first_name=?, last_name=?, email=?, phone=?, position=?, department=?, hire_date=?, salary=?, status=?, notes=? WHERE id=? AND created_by=?");
                $stmt->execute([
                    $_POST['company_id'], $_POST['first_name'], $_POST['last_name'],
                    $_POST['email'], $_POST['phone'], $_POST['position'], $_POST['department'],
                    $_POST['hire_date'], $_POST['salary'], $_POST['status'], $_POST['notes'], $_POST['employee_id'], $currentUser
                ]);
            }
            header('Location: /web/employees.php?success=updated' . ($companyId ? '&company_id=' . $companyId : ''));
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                $stmt->execute([$_POST['employee_id']]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ? AND created_by = ?");
                $stmt->execute([$_POST['employee_id'], $currentUser]);
            }
            header('Location: /web/employees.php?success=deleted' . ($companyId ? '&company_id=' . $companyId : ''));
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get company info if specified
$company = null;
if ($companyId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Company not found";
    }
}

// Get employees
try {
    if ($isAdmin) {
        if ($companyId) {
            $stmt = $pdo->prepare("SELECT e.*, c.name as company_name FROM employees e LEFT JOIN companies c ON e.company_id = c.id WHERE e.company_id = ? ORDER BY e.last_name, e.first_name");
            $stmt->execute([$companyId]);
        } else {
            $stmt = $pdo->query("SELECT e.*, c.name as company_name FROM employees e LEFT JOIN companies c ON e.company_id = c.id ORDER BY e.last_name, e.first_name");
        }
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if ($companyId) {
            $stmt = $pdo->prepare("SELECT e.*, c.name as company_name FROM employees e LEFT JOIN companies c ON e.company_id = c.id WHERE e.company_id = ? AND (e.created_by = ? OR e.created_by IS NULL) ORDER BY e.last_name, e.first_name");
            $stmt->execute([$companyId, $currentUser]);
        } else {
            $stmt = $pdo->prepare("SELECT e.*, c.name as company_name FROM employees e LEFT JOIN companies c ON e.company_id = c.id WHERE (e.created_by = ? OR e.created_by IS NULL) ORDER BY e.last_name, e.first_name");
            $stmt->execute([$currentUser]);
        }
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE created_by = ? ORDER BY name");
        $stmt->execute([$currentUser]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $employees = [];
    $companies = [];
    $error = "Database error: " . $e->getMessage();
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created': $success = 'Employee created successfully!'; break;
        case 'updated': $success = 'Employee updated successfully!'; break;
        case 'deleted': $success = 'Employee deleted successfully!'; break;
    }
}


?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <img src="/assets/frontend/images/icons/users.svg" width="24" height="24" class="me-2"> 
                    Employees<?= $company ? ' - ' . htmlspecialchars($company['name']) : '' ?>
                </h1>
                <div class="d-flex gap-2">
                    <?php if ($companyId): ?>
                        <a href="/web/companies.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Companies
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-primary" onclick="toggleCollapse('addEmployeeForm')">
                        <i class="fas fa-plus"></i> Add Employee
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

    <!-- Add Employee Form -->
    <div class="card mb-4 collapse" id="addEmployeeForm" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Add New Employee</h5>
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
                        <label class="form-label">Company *</label>
                        <select class="form-select" name="company_id" required>
                            <?php if ($companyId): ?>
                                <option value="<?= htmlspecialchars($companyId) ?>" selected><?= htmlspecialchars($company['name']) ?></option>
                            <?php else: ?>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= htmlspecialchars($comp['id']) ?>"><?= htmlspecialchars($comp['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="department">
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
                        <label class="form-label">Hire Date</label>
                        <input type="date" class="form-control" name="hire_date">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Salary</label>
                        <input type="number" class="form-control" name="salary" step="0.01">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Employee</button>
                <button type="button" class="btn btn-secondary" onclick="toggleCollapse('addEmployeeForm')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Employees List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Employees List (<?= count($employees) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($employees)): ?>
                <div class="text-center py-4">
                    <img src="/assets/frontend/images/icons/users.svg" width="64" height="64" class="mb-3 opacity-50">
                    <p class="text-muted">No employees found. Add your first employee above.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                                    <td><?= htmlspecialchars($employee['company_name'] ?? 'No Company') ?></td>
                                    <td><?= htmlspecialchars($employee['position'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($employee['department'] ?? '') ?></td>
                                    <td>
                                        <?php if ($employee['email']): ?>
                                            <a href="mailto:<?= htmlspecialchars($employee['email']) ?>"><?= htmlspecialchars($employee['email']) ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($employee['phone'] ?? '') ?></td>
                                    <td>
                                        <span class="badge <?= $employee['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= htmlspecialchars(ucfirst($employee['status'] ?? 'active')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="editEmployee(<?= htmlspecialchars($employee['id']) ?>)" class="btn btn-sm btn-warning" data-employee='<?= json_encode($employee) ?>'>Edit</button>
                                        <button onclick="deleteEmployee(<?= htmlspecialchars($employee['id']) ?>)" class="btn btn-sm btn-danger">Delete</button>
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

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Employee</h5>
                <button type="button" class="btn-close" onclick="closeModal('editEmployeeModal')"></button>
            </div>
            <form method="POST" id="editEmployeeForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="employee_id" id="editEmployeeId">
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
                            <label class="form-label">Company *</label>
                            <select class="form-select" name="company_id" id="editCompanyId" required>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= htmlspecialchars($comp['id']) ?>"><?= htmlspecialchars($comp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position" id="editPosition">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" id="editDepartment">
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
                            <label class="form-label">Hire Date</label>
                            <input type="date" class="form-control" name="hire_date" id="editHireDate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Salary</label>
                            <input type="number" class="form-control" name="salary" id="editSalary" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="editNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editEmployeeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Employee</button>
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

function editEmployee(id) {
    const button = document.querySelector(`button[onclick="editEmployee(${id})"]`);
    const employeeData = JSON.parse(button.getAttribute('data-employee'));
    
    // Populate form fields
    document.getElementById('editEmployeeId').value = employeeData.id;
    document.getElementById('editFirstName').value = employeeData.first_name || '';
    document.getElementById('editLastName').value = employeeData.last_name || '';
    document.getElementById('editCompanyId').value = employeeData.company_id || '';
    document.getElementById('editPosition').value = employeeData.position || '';
    document.getElementById('editDepartment').value = employeeData.department || '';
    document.getElementById('editEmail').value = employeeData.email || '';
    document.getElementById('editPhone').value = employeeData.phone || '';
    document.getElementById('editHireDate').value = employeeData.hire_date || '';
    document.getElementById('editSalary').value = employeeData.salary || '';
    document.getElementById('editStatus').value = employeeData.status || 'active';
    document.getElementById('editNotes').value = employeeData.notes || '';
    
    // Show modal
    document.getElementById('editEmployeeModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function deleteEmployee(id) {
    if (confirm('Are you sure you want to delete this employee?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="employee_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editEmployeeModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

