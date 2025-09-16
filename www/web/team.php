<?php
$currentPage = 'team';
$pageTitle = 'Team - ZeroAI CRM';
$projectId = $_GET['project_id'] ?? null;
include __DIR__ . '/includes/header.php';

// Get project info if project_id is provided
$project = null;
if ($projectId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Project not found";
    }
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5>👥 Team<?= $project ? ' - ' . htmlspecialchars($project['name']) : '' ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($project): ?>
                        <p>Team management functionality for <strong><?= htmlspecialchars($project['name']) ?></strong> coming soon...</p>
                        <div class="alert alert-info">
                            <h6>Planned Features:</h6>
                            <ul class="mb-0">
                                <li>Team member assignment</li>
                                <li>Role management</li>
                                <li>Permission settings</li>
                                <li>Team collaboration tools</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p>Please select a project from the <a href="/web/projects.php">Projects</a> page to manage team members.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>