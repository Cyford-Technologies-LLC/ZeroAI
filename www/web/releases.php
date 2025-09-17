<?php
$pageTitle = 'Releases - ZeroAI CRM';
$currentPage = 'releases';
include __DIR__ . '/includes/header.php';

$projectId = $_GET['project_id'] ?? null;
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <i class="fas fa-rocket"></i> Release Management
                </h1>
                <?php if ($projectId): ?>
                    <div>
                        <a href="/web/projects.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Projects
                        </a>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Release
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Project Releases</h5>
        </div>
        <div class="card-body">
            <?php if ($projectId): ?>
                <div class="alert alert-info">
                    <strong>Project Releases:</strong> Managing releases for Project ID: <?= htmlspecialchars($projectId) ?>
                </div>
            <?php endif; ?>
            
            <p class="text-muted">Release management functionality coming soon...</p>
            
            <div class="row">
                <div class="col-md-6">
                    <h6>Planned Features:</h6>
                    <ul>
                        <li>Version tracking</li>
                        <li>Release notes</li>
                        <li>Deployment status</li>
                        <li>Rollback capabilities</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Release Pipeline:</h6>
                    <ul>
                        <li>Development → Staging → Production</li>
                        <li>Automated testing integration</li>
                        <li>Approval workflows</li>
                        <li>Release scheduling</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>