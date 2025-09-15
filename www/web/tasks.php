<?php
include __DIR__ . '/includes/header.php';


$pageTitle = 'Tasks - ZeroAI CRM';
$currentPage = 'tasks';


// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (description, agent_id, crew_id, status, created_at) VALUES (?, NULL, NULL, 'pending', datetime('now'))");
        $stmt->execute([$_POST['description']]);
        $success = "Task added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get tasks
try {
    $tasks = $pdo->query("SELECT * FROM tasks ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tasks = [];
    $error = "Database tables not found. Please visit <a href='/web/init.php'>init.php</a> first.";
}

?>

<div class="container-fluid mt-4">
    <div class="header">
        <div class="header-content">
            <div class="logo">🏢 ZeroAI CRM - Tasks</div>
            <nav class="nav">
                <a href="/web/index.php">Dashboard</a>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/contacts.php">Contacts</a>
                <a href="/web/projects.php">Projects</a>
                <a href="/web/tasks.php" class="active">Tasks</a>
            </nav>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser) ?>!</span>
                <?php if ($isAdmin): ?><a href="/admin/dashboard.php" class="header-btn btn-admin">⚙️ Admin</a><?php endif; ?>
                <a href="/web/logout.php" class="header-btn btn-logout">Logout</a>
            </div>
        </div>
    </div>
    <div class="main-content">
        <div class="container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Add New Task</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Task Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Tasks List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks)): ?>
                        <p>No tasks found. Add your first task above.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($task['description']) ?></td>
                                            <td><?= htmlspecialchars($task['status']) ?></td>
                                            <td><?= $task['created_at'] ? date('M j, Y', strtotime($task['created_at'])) : '' ?></td>
                                            <td><?= $task['completed_at'] ? date('M j, Y', strtotime($task['completed_at'])) : '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

