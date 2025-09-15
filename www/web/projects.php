<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

if (!isset($_SESSION['web_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: /web/login.php');
    exit;
}

$currentUser = $_SESSION['web_user'] ?? $_SESSION['admin_user'] ?? 'User';
$isAdmin = isset($_SESSION['admin_logged_in']);
$pageTitle = 'Projects - ZeroAI CRM';
$currentPage = 'projects';

require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

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

include __DIR__ . '/includes/header.php';
?>

<!-- TOP MENU -->
<nav style="background: linear-gradient(135deg, #2563eb, #1d4ed8); padding: 1rem 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
        <div style="color: white; font-size: 1.5rem; font-weight: bold;">🏢 ZeroAI CRM</div>
        <div style="display: flex; gap: 20px;">
            <a href="/web/index.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'crm_dashboard' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📊 Dashboard</a>
            <a href="/web/companies.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'companies' ? 'background: rgba(255,255,255,0.2);' : '' ?>">🏢 Companies</a>
            <a href="/web/contacts.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'contacts' ? 'background: rgba(255,255,255,0.2);' : '' ?>">👥 Contacts</a>
            <a href="/web/sales.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'sales' ? 'background: rgba(255,255,255,0.2);' : '' ?>">💰 Sales</a>
            <a href="/web/projects.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'projects' ? 'background: rgba(255,255,255,0.2);' : '' ?>">📋 Projects</a>
            <a href="/web/tasks.php" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; <?= ($currentPage ?? '') === 'tasks' ? 'background: rgba(255,255,255,0.2);' : '' ?>">✅ Tasks</a>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: rgba(255,255,255,0.9);">👤 <?= htmlspecialchars($currentUser) ?></span>
            <?php if ($isAdmin): ?><a href="/admin/dashboard.php" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">⚙️ Admin</a><?php endif; ?>
            <a href="/web/ai_workshop.php" style="background: #0dcaf0; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🤖 AI</a>
            <a href="/web/logout.php" style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem;">🚪 Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="sidebar-group">
                <h3>CRM</h3>
                <a href="/web/companies.php">Companies</a>
                <a href="/web/contacts.php">Contacts</a>
                <a href="/web/projects.php" class="active">Projects</a>
                <?php if (isset($_GET['project_id'])): ?>
                    <a href="/web/tasks.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">📋 Tasks</a>
                    <a href="/web/features.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">✨ Features</a>
                    <a href="/web/bugs.php?project_id=<?= $_GET['project_id'] ?>" style="padding-left: 40px;">🐛 Bugs</a>
                <?php else: ?>
                    <a href="/web/tasks.php">Tasks</a>
                <?php endif; ?>
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

            <div class="card">
                <h3>Add New Project</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Company</label>
                        <select name="company_id">
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget</label>
                        <input type="number" name="budget" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date">
                    </div>
                    <button type="submit" class="btn">Add Project</button>
                </form>
            </div>

            <div class="card">
                <h3>Projects List</h3>
                <?php if (empty($projects)): ?>
                    <p>No projects found. Add your first project above.</p>
                <?php else: ?>
                    <table>
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
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>