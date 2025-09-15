<?php
session_start();
require_once __DIR__ . '/../admin/includes/autoload.php';

use ZeroAI\Core\{Project, DatabaseManager};

$projectId = $_GET['id'] ?? 0;
$project = new Project();
$db = DatabaseManager::getInstance();

$projectData = $project->findById($projectId);
if (!$projectData) {
    header('Location: dashboard.php');
    exit;
}

$stats = $project->getStats($projectId);
$tasks = $db->query("SELECT * FROM tasks WHERE project_id = ? ORDER BY created_at DESC LIMIT 10", [$projectId]);
$bugs = $db->query("SELECT * FROM bugs WHERE project_id = ? ORDER BY created_at DESC LIMIT 5", [$projectId]);
$milestones = $db->query("SELECT * FROM milestones WHERE project_id = ? ORDER BY due_date ASC", [$projectId]);

$pageTitle = 'Project: ' . $projectData['name'];
include __DIR__ . '/../admin/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>📋 <?= htmlspecialchars($projectData['name']) ?></h1>
    <div>
        <button onclick="aiOptimize()" class="btn btn-primary">🤖 AI Optimize</button>
        <a href="project_edit.php?id=<?= $projectId ?>" class="btn btn-secondary">Edit</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $projectData['progress'] ?>%</div>
        <div class="stat-label">Progress</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($tasks) ?></div>
        <div class="stat-label">Tasks</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['open_bugs'] ?></div>
        <div class="stat-label">Open Bugs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($milestones) ?></div>
        <div class="stat-label">Milestones</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <div class="card">
            <h3>📝 Description</h3>
            <div style="margin: 10px 0;">
                <strong>Original:</strong>
                <p><?= nl2br(htmlspecialchars($projectData['description'])) ?></p>
            </div>
            <?php if ($projectData['ai_description']): ?>
                <div style="margin: 10px 0; padding: 10px; background: #f0f8ff; border-left: 4px solid #007cba;">
                    <strong>🤖 AI Optimized:</strong>
                    <p><?= nl2br(htmlspecialchars($projectData['ai_description'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3>📋 Recent Tasks</h3>
                <a href="task_create.php?project=<?= $projectId ?>" class="btn btn-sm">Add Task</a>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Task</th>
                        <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Status</th>
                        <th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Priority</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <a href="task_view.php?id=<?= $task['id'] ?>"><?= htmlspecialchars($task['name']) ?></a>
                            </td>
                            <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                                <span class="status-<?= $task['status'] ?>"><?= ucfirst($task['status']) ?></span>
                            </td>
                            <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                                <span class="priority-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="card">
            <h3>🎯 Milestones</h3>
            <?php foreach ($milestones as $milestone): ?>
                <div style="padding: 10px; margin: 5px 0; border-left: 4px solid #28a745; background: #f8f9fa;">
                    <strong><?= htmlspecialchars($milestone['name']) ?></strong>
                    <div style="font-size: 12px; color: #666;">
                        Due: <?= $milestone['due_date'] ?> | <?= $milestone['progress'] ?>%
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3>🐛 Recent Bugs</h3>
            <?php foreach ($bugs as $bug): ?>
                <div style="padding: 8px; margin: 5px 0; border-left: 4px solid #dc3545; background: #fff5f5;">
                    <a href="bug_view.php?id=<?= $bug['id'] ?>"><?= htmlspecialchars($bug['title']) ?></a>
                    <div style="font-size: 12px; color: #666;">
                        <?= ucfirst($bug['severity']) ?> | <?= ucfirst($bug['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3>🔑 Project Secret</h3>
            <div style="font-family: monospace; background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">
                <?= htmlspecialchars($projectData['secret_key']) ?>
            </div>
            <button onclick="copySecret()" style="margin-top: 10px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Copy Secret
            </button>
        </div>
    </div>
</div>

<script>
function aiOptimize() {
    fetch(`/web/api/projects/<?= $projectId ?>/ai-optimize`, {method: 'POST'})
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('AI optimization failed');
            }
        });
}

function copySecret() {
    navigator.clipboard.writeText('<?= $projectData['secret_key'] ?>');
    alert('Secret copied to clipboard');
}
</script>

<style>
.status-todo { background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; }
.status-in_progress { background: #007bff; color: #fff; padding: 2px 6px; border-radius: 3px; }
.status-done { background: #28a745; color: #fff; padding: 2px 6px; border-radius: 3px; }
.priority-high { background: #dc3545; color: #fff; padding: 2px 6px; border-radius: 3px; }
.priority-medium { background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; }
.priority-low { background: #6c757d; color: #fff; padding: 2px 6px; border-radius: 3px; }
</style>

<?php include __DIR__ . '/../admin/includes/footer.php'; ?>