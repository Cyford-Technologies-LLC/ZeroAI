<?php
session_start();

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Get projects with company info
$sql = "SELECT p.*, c.name as company_name FROM projects p 
        LEFT JOIN companies c ON p.company_id = c.id 
        ORDER BY p.created_at DESC";
$projects = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get companies for dropdown
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - ZeroAI CRM</title>
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
                    <a class="nav-link" href="/web/contacts.php"><i class="bi bi-people"></i> Contacts</a>
                    <a class="nav-link active" href="/web/projects.php"><i class="bi bi-folder"></i> Projects</a>
                    <a class="nav-link" href="/web/tasks.php"><i class="bi bi-check-square"></i> Tasks</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-folder"></i> Projects</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal">
                        <i class="bi bi-folder-plus"></i> New Project
                    </button>
                </div>
                
                <!-- Projects Grid -->
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?= htmlspecialchars($project['name']) ?></h6>
                                    <span class="badge bg-<?= $project['status'] == 'active' ? 'success' : ($project['status'] == 'completed' ? 'primary' : 'warning') ?>">
                                        <?= ucfirst($project['status']) ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-building"></i> <?= htmlspecialchars($project['company_name'] ?? 'No Company') ?>
                                    </p>
                                    <p class="card-text"><?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Priority: <span class="badge bg-<?= $project['priority'] == 'high' ? 'danger' : ($project['priority'] == 'medium' ? 'warning' : 'secondary') ?>">
                                                <?= ucfirst($project['priority']) ?>
                                            </span>
                                        </small>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary">Edit</button>
                                            <a href="/web/tasks.php?project=<?= $project['id'] ?>" class="btn btn-sm btn-outline-info">Tasks</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-muted">
                                    <small>Created: <?= date('M j, Y', strtotime($project['created_at'])) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Project Modal -->
    <div class="modal fade" id="projectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Project Name</label>
                            <input type="text" class="form-control" name="name" required>
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
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget</label>
                                <input type="number" class="form-control" name="budget" step="0.01">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>