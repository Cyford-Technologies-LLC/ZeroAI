<?php
include __DIR__ . '/includes/header.php';


$pageTitle = 'Projects - ZeroAI CRM';
$currentPage = 'projects';

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO projects (company_id, name, description, status, priority, start_date, end_date, budget, organization_id, created_by) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, 1, ?)");
        $stmt->execute([
            $_POST['company_id'], $_POST['name'], $_POST['description'],
            $_POST['priority'], $_POST['start_date'], $_POST['end_date'], $_POST['budget'], $currentUser
        ]);
        $success = "Project added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get projects with company info
try {
    $projects = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
    $companies = [];
    $error = "Database tables not found. Please visit <a href='/web/init.php'>init.php</a> first.";
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
            <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#addProjectForm" aria-expanded="false">
                <i class="fas fa-plus-circle"></i> Add New Project
            </button>
        </h5>
    </div>
    <div class="collapse" id="addProjectForm">
        <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Project Name</label>
                    <input type="text" class="form-control" name="name" required>
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
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Budget</label>
                    <input type="number" class="form-control" name="budget" step="0.01">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Project</button>
        </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Projects List</h5>
    </div>
    <div class="card-body">
        <?php if (empty($projects)): ?>
            <p>No projects found. Add your first project above.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Budget</th>
                            <th>Start Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?= htmlspecialchars($project['name']) ?></td>
                                <td><?= htmlspecialchars($project['company_id'] ?? 'No Company') ?></td>
                                <td><?= htmlspecialchars($project['status'] ?? 'active') ?></td>
                                <td><?= htmlspecialchars($project['priority'] ?? 'medium') ?></td>
                                <td><?= isset($project['budget']) && $project['budget'] ? '$' . number_format($project['budget'], 2) : '' ?></td>
                                <td><?= isset($project['start_date']) && $project['start_date'] ? date('M j, Y', strtotime($project['start_date'])) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>