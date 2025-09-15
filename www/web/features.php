<?php
$currentPage = 'features';
include __DIR__ . '/includes/header.php';

// Get project info
$project = null;
if (isset($projectId) && $projectId) {
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
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5>✨ Features<?= $project ? ' - ' . htmlspecialchars($project['name']) : '' ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($project): ?>
                        <p>Feature management functionality for <strong><?= htmlspecialchars($project['name']) ?></strong> coming soon...</p>
                    <?php else: ?>
                        <p>Please select a project from the <a href="/web/projects.php">Projects</a> page to view features.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

