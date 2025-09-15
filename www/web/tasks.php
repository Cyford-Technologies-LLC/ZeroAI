<?php
session_start();

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Get tasks with project and contact info
$sql = "SELECT t.*, p.name as project_name, c.first_name, c.last_name 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        LEFT JOIN contacts c ON t.contact_id = c.id 
        ORDER BY t.created_at DESC";
$tasks = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get projects and contacts for dropdowns
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$contacts = $pdo->query("SELECT id, first_name, last_name FROM contacts ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - ZeroAI CRM</title>
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
                    <a class="nav-link" href="/web/projects.php"><i class="bi bi-folder"></i> Projects</a>
                    <a class="nav-link active" href="/web/tasks.php"><i class="bi bi-check-square"></i> Tasks</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-check-square"></i> Tasks</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
                        <i class="bi bi-plus-square"></i> Add Task
                    </button>
                </div>
                
                <!-- Task Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <select class="form-control" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="priorityFilter">
                                    <option value="">All Priority</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tasks Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($task['title']) ?></strong>
                                                <?php if ($task['description']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($task['description'], 0, 50)) ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($task['project_name'] ?? 'No Project') ?></td>
                                            <td>
                                                <?php if ($task['first_name']): ?>
                                                    <?= htmlspecialchars($task['first_name'] . ' ' . $task['last_name']) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($task['assigned_to'] ?? 'Unassigned') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $task['status'] == 'completed' ? 'success' : ($task['status'] == 'in_progress' ? 'primary' : 'warning') ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $task['priority'] == 'high' ? 'danger' : ($task['priority'] == 'medium' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($task['priority']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($task['due_date']): ?>
                                                    <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                <button class="btn btn-sm btn-outline-success">Complete</button>
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
    
    <!-- Task Modal -->
    <div class="modal fade" id="taskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Task Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Project</label>
                            <select class="form-control" name="project_id">
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-control" name="contact_id">
                                <option value="">Select Contact</option>
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?= $contact['id'] ?>"><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>