<?php
$pageTitle = 'DB Tools - ZeroAI';
$currentPage = 'tools';
include __DIR__ . '/includes/header.php';

require_once '../src/Core/DatabaseManager.php';

// Handle AJAX requests first
if (isset($_GET['action'])) {
    $db = \ZeroAI\Core\DatabaseManager::getInstance();
    
    if ($_GET['action'] === 'get_tables' && isset($_GET['db'])) {
        try {
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            echo json_encode($tables);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get_table_info' && isset($_GET['table'])) {
        try {
            $tableName = $_GET['table'];
            $columns = $db->query("PRAGMA table_info($tableName)");
            $rowCount = $db->query("SELECT COUNT(*) as count FROM $tableName")[0]['count'] ?? 0;
            echo json_encode(['columns' => $columns, 'row_count' => $rowCount]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get_table_data' && isset($_GET['table'])) {
        try {
            $tableName = $_GET['table'];
            $data = $db->query("SELECT * FROM $tableName LIMIT 10");
            echo json_encode($data);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}

$db = \ZeroAI\Core\DatabaseManager::getInstance();
$databases = $db->getAvailableDatabases();
?>

<h1 class="mb-4">🛠️ DB Tools</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h6>📊 Databases</h6>
            </div>
            <div class="card-body p-2">
                <?php foreach ($databases as $path => $info): ?>
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2 text-start" onclick="selectDatabase('<?= $path ?>')">
                        <?= $info['name'] ?><br>
                        <small class="text-muted"><?= number_format($info['size']/1024, 1) ?>KB</small>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6>📋 Tables</h6>
            </div>
            <div class="card-body p-2" id="tables-list">
                <p class="text-muted">Select a database first</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div id="content-area">
            <div class="card">
                <div class="card-body text-center py-5">
                    <h5 class="text-muted">Select a database and table to view details</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentDb = null;
let currentTable = null;

function selectDatabase(dbPath) {
    currentDb = dbPath;
    document.getElementById('content-area').innerHTML = '<div class="card"><div class="card-body text-center py-5"><div class="spinner-border"></div><p>Loading tables...</p></div></div>';
    
    fetch(`?action=get_tables&db=${encodeURIComponent(dbPath)}`)
        .then(response => response.json())
        .then(tables => {
            if (tables.error) {
                document.getElementById('tables-list').innerHTML = `<div class="alert alert-danger">${tables.error}</div>`;
                return;
            }
            
            let html = '';
            tables.forEach(table => {
                html += `<button class="btn btn-outline-secondary btn-sm w-100 mb-1 text-start" onclick="selectTable('${table.name}')">${table.name}</button>`;
            });
            document.getElementById('tables-list').innerHTML = html;
            document.getElementById('content-area').innerHTML = '<div class="card"><div class="card-body text-center py-5"><h5 class="text-muted">Select a table to view details</h5></div></div>';
        })
        .catch(error => {
            document.getElementById('tables-list').innerHTML = `<div class="alert alert-danger">Error: ${error}</div>`;
        });
}

function selectTable(tableName) {
    currentTable = tableName;
    document.getElementById('content-area').innerHTML = '<div class="card"><div class="card-body text-center py-5"><div class="spinner-border"></div><p>Loading table info...</p></div></div>';
    
    fetch(`?action=get_table_info&table=${encodeURIComponent(tableName)}`)
        .then(response => response.json())
        .then(info => {
            if (info.error) {
                document.getElementById('content-area').innerHTML = `<div class="alert alert-danger">${info.error}</div>`;
                return;
            }
            
            let html = `
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5>📊 ${tableName} (${info.row_count} rows)</h5>
                        <button class="btn btn-sm btn-primary" onclick="loadTableData()">View Data</button>
                    </div>
                    <div class="card-body">
                        <h6>Columns:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Name</th><th>Type</th><th>Null</th><th>Default</th><th>PK</th></tr>
                                </thead>
                                <tbody>`;
            
            info.columns.forEach(col => {
                html += `<tr>
                    <td><strong>${col.name}</strong></td>
                    <td>${col.type}</td>
                    <td>${col.notnull ? 'No' : 'Yes'}</td>
                    <td>${col.dflt_value || 'NULL'}</td>
                    <td>${col.pk ? '✓' : ''}</td>
                </tr>`;
            });
            
            html += `</tbody></table></div></div></div>
                <div id="table-data" class="mt-3"></div>`;
            
            document.getElementById('content-area').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('content-area').innerHTML = `<div class="alert alert-danger">Error: ${error}</div>`;
        });
}

function loadTableData() {
    document.getElementById('table-data').innerHTML = '<div class="card"><div class="card-body text-center"><div class="spinner-border"></div><p>Loading data...</p></div></div>';
    
    fetch(`?action=get_table_data&table=${encodeURIComponent(currentTable)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('table-data').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            if (data.length === 0) {
                document.getElementById('table-data').innerHTML = '<div class="card"><div class="card-body text-center">No data found</div></div>';
                return;
            }
            
            let html = '<div class="card"><div class="card-header"><h6>Sample Data (First 10 rows)</h6></div><div class="card-body"><div class="table-responsive"><table class="table table-striped table-sm"><thead><tr>';
            
            Object.keys(data[0]).forEach(col => {
                html += `<th>${col}</th>`;
            });
            html += '</tr></thead><tbody>';
            
            data.forEach(row => {
                html += '<tr>';
                Object.values(row).forEach(value => {
                    html += `<td>${value || 'NULL'}</td>`;
                });
                html += '</tr>';
            });
            
            html += '</tbody></table></div></div></div>';
            document.getElementById('table-data').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('table-data').innerHTML = `<div class="alert alert-danger">Error: ${error}</div>`;
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>