<?php
session_start();

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO contacts (company_id, first_name, last_name, email, phone, position, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['company_id'], $_POST['first_name'], $_POST['last_name'],
                    $_POST['email'], $_POST['phone'], $_POST['position'], $_POST['department']
                ]);
                $success = "Contact added successfully!";
                break;
        }
    }
}

// Get contacts with company info
$sql = "SELECT c.*, comp.name as company_name FROM contacts c 
        LEFT JOIN companies comp ON c.company_id = comp.id 
        ORDER BY c.last_name, c.first_name";
$contacts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get companies for dropdown
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - ZeroAI CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-light p-3" style="min-height: 100vh;">
                <h5 class="text-primary mb-4"><i class="bi bi-robot"></i> ZeroAI CRM</h5>
                <nav class="nav flex-column">
                    <a class="nav-link" href="/web/"><i class="bi bi-house"></i> Dashboard</a>
                    <a class="nav-link" href="/web/companies.php"><i class="bi bi-building"></i> Companies</a>
                    <a class="nav-link active" href="/web/contacts.php"><i class="bi bi-people"></i> Contacts</a>
                    <a class="nav-link" href="/web/projects.php"><i class="bi bi-folder"></i> Projects</a>
                    <a class="nav-link" href="/web/tasks.php"><i class="bi bi-check-square"></i> Tasks</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-people"></i> Contacts</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="bi bi-person-plus"></i> Add Contact
                    </button>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <!-- Contacts Table -->
                <div class="card">
                    <div class="card-body">
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
                                            <td>
                                                <strong><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($contact['company_name'] ?? 'No Company') ?></td>
                                            <td><?= htmlspecialchars($contact['position']) ?></td>
                                            <td>
                                                <?php if ($contact['email']): ?>
                                                    <a href="mailto:<?= htmlspecialchars($contact['email']) ?>">
                                                        <?= htmlspecialchars($contact['email']) ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($contact['phone']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                <button class="btn btn-sm btn-outline-info">Tasks</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contact Modal -->
    <div class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Contact</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
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
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company</label>
                            <select class="form-control" name="company_id">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>