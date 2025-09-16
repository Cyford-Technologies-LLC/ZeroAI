<?php
$currentPage = 'projects';
$pageTitle = 'Projects - ZeroAI CRM';
include __DIR__ . '/includes/header.php';

// Handle form submissions
if ($_POST) {
    try {
        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO projects (name, company_id, description, priority, budget, start_date, end_date, project_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $_POST['name'], 
                $_POST['company_id'], 
                $_POST['description'], 
                $_POST['priority'], 
                $_POST['budget'], 
                $_POST['start_date'], 
                $_POST['end_date'],
                $_POST['project_type']
            ]);
            header('Location: /web/projects.php?success=created');
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE projects SET name=?, company_id=?, description=?, priority=?, budget=?, start_date=?, end_date=?, project_type=? WHERE id=?");
            $stmt->execute([
                $_POST['name'], 
                $_POST['company_id'], 
                $_POST['description'], 
                $_POST['priority'], 
                $_POST['budget'], 
                $_POST['start_date'], 
                $_POST['end_date'],
                $_POST['project_type'],
                $_POST['project_id']
            ]);
            header('Location: /web/projects.php?success=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$_POST['project_id']]);
            header('Location: /web/projects.php?success=deleted');
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get projects and companies
try {
    $projects = $pdo->query("SELECT p.*, c.name as company_name FROM projects p LEFT JOIN companies c ON p.company_id = c.id ORDER BY p.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
    $companies = [];
    $error = "Database error. Please check your database setup.";
}

// Handle success messages
$success = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created': $success = 'Project created successfully!'; break;
        case 'updated': $success = 'Project updated successfully!'; break;
        case 'deleted': $success = 'Project deleted successfully!'; break;
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
            <button class="btn btn-link p-0 text-decoration-none" type="button" onclick="toggleCollapse('addProjectForm')" aria-expanded="false">
                <i class="fas fa-plus-circle"></i> Add New Project
            </button>
        </h5>
    </div>
    <div class="collapse" id="addProjectForm" style="display: none;">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
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
                                <option value="<?= htmlspecialchars($company['id']) ?>"><?= htmlspecialchars($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Project Type</label>
                        <select class="form-select" name="project_type" required>
                            <option value="">Select Type</option>
                            <option value="web_based">Web Based</option>
                            <option value="application">Application</option>
                            <option value="marketing">Marketing</option>
                            <option value="other">Other</option>
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
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Project</button>
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
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Budget</th>
                            <th>Start Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?= htmlspecialchars($project['name']) ?></td>
                                <td><?= htmlspecialchars($project['company_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $project['project_type'] ?? 'N/A'))) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $priorityClass = match($project['priority']) {
                                        'high' => 'bg-danger',
                                        'medium' => 'bg-warning',
                                        'low' => 'bg-success',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $priorityClass ?>"><?= htmlspecialchars(ucfirst($project['priority'] ?? 'N/A')) ?></span>
                                </td>
                                <td><?= $project['budget'] ? '$' . number_format($project['budget'], 2) : 'N/A' ?></td>
                                <td><?= $project['start_date'] ? date('M j, Y', strtotime($project['start_date'])) : 'N/A' ?></td>
                                <td>
                                    <button onclick="editProject(<?= htmlspecialchars($project['id']) ?>)" class="btn btn-sm btn-warning" data-project='<?= json_encode($project) ?>'>Edit</button>
                                    <a href="/web/project_view.php?id=<?= htmlspecialchars($project['id']) ?>" class="btn btn-sm btn-info">View</a>
                                    <button onclick="deleteProject(<?= htmlspecialchars($project['id']) ?>)" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Project</h5>
                <button type="button" class="btn-close" onclick="closeModal('editProjectModal')"></button>
            </div>
            <form method="POST" id="editProjectForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="project_id" id="editProjectId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
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
                            <label class="form-label">Project Type</label>
                            <select class="form-select" name="project_type" id="editProjectType" required>
                                <option value="">Select Type</option>
                                <option value="web_based">Web Based</option>
                                <option value="application">Application</option>
                                <option value="marketing">Marketing</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority" id="editPriority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Budget</label>
                            <input type="number" class="form-control" name="budget" id="editBudget" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="editStartDate">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="editEndDate">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Project</button>
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

function editProject(id) {
    const button = document.querySelector(`button[onclick="editProject(${id})"]`);
    const projectData = JSON.parse(button.getAttribute('data-project'));
    
    // Populate form fields
    document.getElementById('editProjectId').value = projectData.id;
    document.getElementById('editName').value = projectData.name || '';
    document.getElementById('editCompanyId').value = projectData.company_id || '';
    document.getElementById('editProjectType').value = projectData.project_type || '';
    document.getElementById('editPriority').value = projectData.priority || '';
    document.getElementById('editBudget').value = projectData.budget || '';
    document.getElementById('editStartDate').value = projectData.start_date || '';
    document.getElementById('editEndDate').value = projectData.end_date || '';
    document.getElementById('editDescription').value = projectData.description || '';
    
    // Show modal
    document.getElementById('editProjectModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function deleteProject(id) {
    if (confirm('Are you sure you want to delete this project?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="project_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editProjectModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

