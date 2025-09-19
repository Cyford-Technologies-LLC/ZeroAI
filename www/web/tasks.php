<?php
$pageTitle = 'Tasks - ZeroAI CRM';
$currentPage = 'tasks';
include __DIR__ . '/includes/header.php';

// Create tasks table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        description TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        priority VARCHAR(10) DEFAULT 'medium',
        assigned_to VARCHAR(100),
        due_date DATE,
        organization_id VARCHAR(10),
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME
    )");
    
    // Add organization_id column if not exists
    try {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN organization_id VARCHAR(10)");
    } catch (Exception $e) {}
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (description, status, priority, assigned_to, due_date, organization_id, created_by, created_at) VALUES (?, 'pending', ?, ?, ?, ?, ?, datetime('now'))");
        $stmt->execute([
            $_POST['description'],
            $_POST['priority'] ?? 'medium',
            $_POST['assigned_to'] ?? null,
            $_POST['due_date'] ?? null,
            $userOrgId,
            $currentUser
        ]);
        $success = "Task added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    try {
        $completed_at = $_POST['status'] === 'completed' ? "datetime('now')" : 'NULL';
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = {$completed_at} WHERE id = ? AND organization_id = ?");
        $stmt->execute([$_POST['status'], $_POST['task_id'], $userOrgId]);
        $success = "Task status updated!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get tasks with multi-tenant filtering
try {
    if ($isAdmin) {
        $tasks = $pdo->query("SELECT * FROM tasks ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE organization_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userOrgId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $tasks = [];
    $error = "Database error: " . $e->getMessage();
}

?>


        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Tasks Management</h2>
            <button class="btn btn-primary" onclick="toggleCollapse('addTaskForm')">
                <i class="fas fa-plus"></i> Add Task
            </button>
        </div>

        <!-- Add Task Form -->
        <div class="card mb-4 collapse" id="addTaskForm" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">Add New Task</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Task Description *</label>
                            <textarea class="form-control" name="description" rows="3" required placeholder="Describe the task..."></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Assigned To</label>
                            <input type="text" class="form-control" name="assigned_to" placeholder="Team member name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleCollapse('addTaskForm')">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tasks List (<?= count($tasks) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No tasks found. Add your first task above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Priority</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($task['description']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $task['priority'] === 'urgent' ? 'danger' : ($task['priority'] === 'high' ? 'warning' : ($task['priority'] === 'medium' ? 'info' : 'secondary')) ?>">
                                                <?= htmlspecialchars(ucfirst($task['priority'] ?? 'medium')) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($task['assigned_to'] ?? 'Unassigned') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $task['status'] === 'completed' ? 'success' : ($task['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $task['status']))) ?>
                                            </span>
                                        </td>
                                        <td><?= $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : '' ?></td>
                                        <td><?= $task['created_at'] ? date('M j, Y', strtotime($task['created_at'])) : '' ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                                                    <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

