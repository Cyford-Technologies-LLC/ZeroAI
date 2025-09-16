<?php
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../src/Core/FormBuilder.php';

$pageTitle = 'Projects - ZeroAI CRM';
$currentPage = 'projects';

$formBuilder = new \ZeroAI\Core\FormBuilder();

// Define project fields
$projectFields = [
    'name' => ['label' => 'Project Name', 'type' => 'text', 'required' => true],
    'company_id' => ['label' => 'Company', 'type' => 'select', 'options' => []],
    'description' => ['label' => 'Description', 'type' => 'textarea'],
    'priority' => ['label' => 'Priority', 'type' => 'select', 'options' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']],
    'budget' => ['label' => 'Budget', 'type' => 'number'],
    'start_date' => ['label' => 'Start Date', 'type' => 'date'],
    'end_date' => ['label' => 'End Date', 'type' => 'date']
];

// Get companies for dropdown
try {
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as $company) {
        $projectFields['company_id']['options'][$company['id']] = $company['name'];
    }
} catch (Exception $e) {
    $companies = [];
}

// Handle form submissions
$result = $formBuilder->handleRequest('projects', $projectFields);
if ($result) {
    $success = $result['success'] ?? null;
    $error = $result['error'] ?? null;
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

?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5>
            <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#addProjectForm" aria-expanded="false">
                <i class="fas fa-plus-circle"></i> Add New Project
            </button>
        </h5>
    </div>
    <div class="collapse" id="addProjectForm">
        <div class="card-body">
        <?= $formBuilder->renderForm('projects', $projectFields) ?>
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
                <?= $formBuilder->renderTable('projects', $projects, [
                    'name' => 'Name',
                    'company_id' => 'Company',
                    'status' => 'Status',
                    'priority' => 'Priority',
                    'budget' => 'Budget',
                    'start_date' => 'Start Date'
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $formBuilder->renderScript() ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

