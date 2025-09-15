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
                $stmt = $pdo->prepare("INSERT INTO companies (name, email, phone, address, website, industry, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['name'], $_POST['email'], $_POST['phone'], 
                    $_POST['address'], $_POST['website'], $_POST['industry'], 'active'
                ]);
                $success = "Company added successfully!";
                break;
            case 'edit':
                $stmt = $pdo->prepare("UPDATE companies SET name=?, email=?, phone=?, address=?, website=?, industry=? WHERE id=?");
                $stmt->execute([
                    $_POST['name'], $_POST['email'], $_POST['phone'], 
                    $_POST['address'], $_POST['website'], $_POST['industry'], $_POST['id']
                ]);
                $success = "Company updated successfully!";
                break;
        }
    }
}

// Get companies
$companies = $pdo->query("SELECT * FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get single company for editing
$editCompany = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCompany = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies - ZeroAI CRM</title>
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
                    <a class="nav-link active" href="/web/companies.php"><i class="bi bi-building"></i> Companies</a>
                    <a class="nav-link" href="/web/contacts.php"><i class="bi bi-people"></i> Contacts</a>
                    <a class="nav-link" href="/web/projects.php"><i class="bi bi-folder"></i> Projects</a>
                    <a class="nav-link" href="/web/tasks.php"><i class="bi bi-check-square"></i> Tasks</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-building"></i> Companies</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#companyModal">
                        <i class="bi bi-plus"></i> Add Company
                    </button>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <!-- Companies Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Industry</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companies as $company): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($company['name']) ?></strong></td>
                                            <td><?= htmlspecialchars($company['email']) ?></td>
                                            <td><?= htmlspecialchars($company['phone']) ?></td>
                                            <td><?= htmlspecialchars($company['industry']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $company['status'] == 'active' ? 'success' : 'secondary' ?>">
                                                    <?= ucfirst($company['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?edit=<?= $company['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <a href="/web/contacts.php?company=<?= $company['id'] ?>" class="btn btn-sm btn-outline-info">Contacts</a>
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
    
    <!-- Company Modal -->
    <div class="modal fade" id="companyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= $editCompany ? 'Edit' : 'Add' ?> Company</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editCompany ? 'edit' : 'add' ?>">
                        <?php if ($editCompany): ?>
                            <input type="hidden" name="id" value="<?= $editCompany['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($editCompany['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($editCompany['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($editCompany['phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address"><?= htmlspecialchars($editCompany['address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" class="form-control" name="website" value="<?= htmlspecialchars($editCompany['website'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Industry</label>
                            <select class="form-control" name="industry">
                                <option value="">Select Industry</option>
                                <option value="Technology" <?= ($editCompany['industry'] ?? '') == 'Technology' ? 'selected' : '' ?>>Technology</option>
                                <option value="Healthcare" <?= ($editCompany['industry'] ?? '') == 'Healthcare' ? 'selected' : '' ?>>Healthcare</option>
                                <option value="Finance" <?= ($editCompany['industry'] ?? '') == 'Finance' ? 'selected' : '' ?>>Finance</option>
                                <option value="Manufacturing" <?= ($editCompany['industry'] ?? '') == 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                                <option value="Retail" <?= ($editCompany['industry'] ?? '') == 'Retail' ? 'selected' : '' ?>>Retail</option>
                                <option value="Other" <?= ($editCompany['industry'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><?= $editCompany ? 'Update' : 'Add' ?> Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($editCompany): ?>
        <script>
            new bootstrap.Modal(document.getElementById('companyModal')).show();
        </script>
    <?php endif; ?>
</body>
</html>