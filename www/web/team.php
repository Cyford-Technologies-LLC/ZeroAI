<?php
$pageTitle = 'Team - ZeroAI CRM';
$currentPage = 'team';
include __DIR__ . '/includes/header.php';

$projectId = $_GET['project_id'] ?? null;
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">
                    <img src="/assets/frontend/images/icons/users.svg" width="24" height="24" class="me-2"> Team Management
                </h1>
                <?php if ($projectId): ?>
                    <a href="/web/projects.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Team Members</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Team management functionality coming soon...</p>
            
            <?php if ($projectId): ?>
                <div class="alert alert-info">
                    <strong>Project Team:</strong> Managing team for Project ID: <?= htmlspecialchars($projectId) ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <h6>Current Features:</h6>
                    <ul>
                        <li>View team members</li>
                        <li>Assign roles and permissions</li>
                        <li>Track team performance</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Coming Soon:</h6>
                    <ul>
                        <li>Add/remove team members</li>
                        <li>Team collaboration tools</li>
                        <li>Performance analytics</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>