<?php
$pageTitle = 'DB Tools - ZeroAI';
$currentPage = 'tools';
include __DIR__ . '/includes/header.php';

require_once '../src/Core/DatabaseManager.php';

// Available databases
$databases = [
    'main' => 'Main Database',
    'crm' => 'CRM Database',
    'logs' => 'Logs Database'
];

$selectedDb = $_GET['db'] ?? 'main';
$db = \ZeroAI\Core\DatabaseManager::getInstance();
$tables = [];
$tableData = [];

try {
    // Get all tables from selected database
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    
    // Get structure for each table
    foreach ($tables as $table) {
        $tableName = $table['name'];
        $columns = $db->query("PRAGMA table_info($tableName)");
        $rowCount = $db->query("SELECT COUNT(*) as count FROM $tableName")[0]['count'] ?? 0;
        
        $tableData[$tableName] = [
            'columns' => $columns,
            'row_count' => $rowCount
        ];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<h1 class="mb-4">🛠️ DB Tools</h1>

<div class="card mb-4">
    <div class="card-header">
        <h5>📊 Database Selection</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <select name="db" class="form-select" style="width: auto;">
                <?php foreach ($databases as $key => $name): ?>
                    <option value="<?= $key ?>" <?= $selectedDb === $key ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Switch Database</button>
        </form>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>📋 Tables (<?= count($tables) ?>)</h5>
            </div>
            <div class="card-body">
                <?php foreach ($tables as $table): ?>
                    <div class="mb-2">
                        <a href="#table-<?= $table['name'] ?>" class="btn btn-outline-primary btn-sm w-100 text-start">
                            📊 <?= $table['name'] ?> 
                            <span class="badge bg-secondary float-end"><?= $tableData[$table['name']]['row_count'] ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php foreach ($tables as $table): ?>
            <div class="card mb-4" id="table-<?= $table['name'] ?>">
                <div class="card-header">
                    <h5>📊 <?= $table['name'] ?> (<?= $tableData[$table['name']]['row_count'] ?> rows)</h5>
                </div>
                <div class="card-body">
                    <h6>Columns:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Default</th>
                                    <th>PK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData[$table['name']]['columns'] as $col): ?>
                                <tr>
                                    <td><strong><?= $col['name'] ?></strong></td>
                                    <td><?= $col['type'] ?></td>
                                    <td><?= $col['notnull'] ? 'No' : 'Yes' ?></td>
                                    <td><?= $col['dflt_value'] ?? 'NULL' ?></td>
                                    <td><?= $col['pk'] ? '✓' : '' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($tableData[$table['name']]['row_count'] > 0): ?>
                        <button class="btn btn-sm btn-info" onclick="loadTableData('<?= $table['name'] ?>')">View Data</button>
                        <div id="data-<?= $table['name'] ?>" class="mt-3" style="display:none;"></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function loadTableData(tableName) {
    const container = document.getElementById('data-' + tableName);
    if (container.style.display === 'none') {
        fetch('/admin/database.php?action=get_table_data&table=' + tableName)
            .then(response => response.text())
            .then(data => {
                container.innerHTML = data;
                container.style.display = 'block';
            })
            .catch(error => {
                container.innerHTML = '<div class="alert alert-danger">Error loading data: ' + error + '</div>';
                container.style.display = 'block';
            });
    } else {
        container.style.display = 'none';
    }
}
</script>

<?php
// Handle AJAX request for table data
if (isset($_GET['action']) && $_GET['action'] === 'get_table_data' && isset($_GET['table'])) {
    $tableName = $_GET['table'];
    try {
        $data = $db->query("SELECT * FROM $tableName LIMIT 10");
        if ($data) {
            echo '<div class="table-responsive"><table class="table table-striped table-sm"><thead><tr>';
            foreach (array_keys($data[0]) as $col) {
                echo '<th>' . htmlspecialchars($col) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                foreach ($row as $value) {
                    echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    exit;
}
?>

<?php include __DIR__ . '/includes/footer.php'; ?>