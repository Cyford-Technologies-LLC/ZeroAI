<?php
// Handle AJAX requests FIRST - before any output
if ((isset($_GET['action']) && $_GET['action']) || (isset($_POST['action']) && $_POST['action'])) {
    require_once '../src/Core/DatabaseManager.php';
    header('Content-Type: application/json');
    
    try {
        $db = \ZeroAI\Core\DatabaseManager::getInstance();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'get_tables' && isset($_GET['db'])) {
        try {
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            echo json_encode($tables);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'get_table_info' && isset($_GET['table'])) {
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
    
    if (isset($_GET['action']) && $_GET['action'] === 'get_table_data' && isset($_GET['table'])) {
        try {
            $tableName = $_GET['table'];
            $data = $db->query("SELECT * FROM $tableName LIMIT 10");
            echo json_encode($data);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'execute_sql' && isset($_POST['sql'])) {
        try {
            $sql = trim($_POST['sql']);
            if (empty($sql)) {
                echo json_encode(['error' => 'SQL query cannot be empty']);
                exit;
            }
            
            $result = $db->query($sql);
            echo json_encode(['success' => true, 'data' => $result, 'rows' => count($result)]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'backup_db' && isset($_GET['db'])) {
        try {
            $dbPath = $_GET['db'];
            $backupDir = '../data/backups/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $backupFile = $backupDir . basename($dbPath, '.db') . '_' . date('Y-m-d_H-i-s') . '.db';
            if (copy($dbPath, $backupFile)) {
                echo json_encode(['success' => true, 'backup' => basename($backupFile)]);
            } else {
                echo json_encode(['error' => 'Failed to create backup']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'upload_dump') {
        try {
            if (!isset($_FILES['dump_file']) || $_FILES['dump_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'No file uploaded or upload error']);
                exit;
            }
            
            $file = $_FILES['dump_file'];
            $allowedExts = ['sql', 'txt', 'dump'];
            
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExt, $allowedExts)) {
                echo json_encode(['error' => 'Invalid file type. Only .sql, .txt, .dump files allowed']);
                exit;
            }
            
            $sqlContent = file_get_contents($file['tmp_name']);
            if (empty($sqlContent)) {
                echo json_encode(['error' => 'File is empty']);
                exit;
            }
            
            // Remove SQL comments and split statements properly
            $lines = explode("\n", $sqlContent);
            $cleanedLines = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip empty lines and comment lines
                if (!empty($line) && !preg_match('/^\s*--/', $line)) {
                    $cleanedLines[] = $line;
                }
            }
            
            $cleanedSql = implode(" ", $cleanedLines);
            $statements = explode(';', $cleanedSql);
            $executed = 0;
            $errors = [];
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && strlen($statement) > 10) {
                    try {
                        $result = $db->query($statement);
                        $executed++;
                    } catch (Exception $e) {
                        $errors[] = 'SQL Error: ' . $e->getMessage();
                        if (count($errors) > 10) break; // Limit error reporting
                    }
                }
            }
            
            echo json_encode([
                'success' => true, 
                'executed' => $executed, 
                'errors' => $errors,
                'message' => $executed > 0 ? "Executed {$executed} SQL statements" : "No valid SQL statements found"
            ]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Upload failed: ' . $e->getMessage()]);
        }
        exit;
    }
    
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

$pageTitle = 'DB Tools - ZeroAI';
$currentPage = 'db_tools';
include __DIR__ . '/includes/header.php';

require_once '../src/Core/DatabaseManager.php';
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
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2 text-start db-btn" data-db="<?= htmlspecialchars($path) ?>" onclick="selectDatabase('<?= htmlspecialchars($path) ?>')">
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
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-success" onclick="showSqlQuery()">🔍 SQL Query</button>
                    <button class="btn btn-outline-info" onclick="showUploadDump()">📁 Upload Dump</button>
                    <button class="btn btn-outline-warning" onclick="backupDatabase()" id="backup-btn" disabled>💾 Backup DB</button>
                </div>
                <div id="current-selection" class="text-muted small">
                    <span id="selected-db">No database selected</span>
                    <span id="selected-table" style="display:none;"> > <strong id="table-name"></strong></span>
                </div>
            </div>
        </div>
        
        <div id="sql-query-area" class="card mb-3" style="display:none;">
            <div class="card-header">
                <h6>🔍 SQL Query</h6>
            </div>
            <div class="card-body">
                <textarea id="sql-input" class="form-control mb-2" rows="4" placeholder="Enter SQL query...">SELECT * FROM users LIMIT 5;</textarea>
                <button class="btn btn-primary" onclick="executeSql()">Execute</button>
                <button class="btn btn-secondary" onclick="hideSqlQuery()">Cancel</button>
            </div>
            <div id="sql-results"></div>
        </div>
        
        <div id="upload-dump-area" class="card mb-3" style="display:none;">
            <div class="card-header">
                <h6>📁 Upload & Execute SQL Dump</h6>
            </div>
            <div class="card-body">
                <form id="dump-upload-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="dump-file" class="form-label">Select SQL Dump File (.sql, .txt, .dump)</label>
                        <input type="file" class="form-control" id="dump-file" name="dump_file" accept=".sql,.txt,.dump" required>
                    </div>
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will execute all SQL statements in the file. Make sure to backup your database first!
                    </div>
                    <button type="button" class="btn btn-primary" onclick="uploadDump()">Upload & Execute</button>
                    <button type="button" class="btn btn-secondary" onclick="hideUploadDump()">Cancel</button>
                </form>
            </div>
            <div id="upload-results"></div>
        </div>
        
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

function showSqlQuery() {
    document.getElementById('sql-query-area').style.display = 'block';
    document.getElementById('sql-input').focus();
}

function hideSqlQuery() {
    document.getElementById('sql-query-area').style.display = 'none';
    document.getElementById('sql-results').innerHTML = '';
}

function showUploadDump() {
    document.getElementById('upload-dump-area').style.display = 'block';
    document.getElementById('dump-file').focus();
}

function hideUploadDump() {
    document.getElementById('upload-dump-area').style.display = 'none';
    document.getElementById('upload-results').innerHTML = '';
    document.getElementById('dump-upload-form').reset();
}

function uploadDump() {
    const fileInput = document.getElementById('dump-file');
    if (!fileInput.files[0]) {
        alert('Please select a file first');
        return;
    }
    
    if (!confirm('This will execute all SQL statements in the file. Continue?')) {
        return;
    }
    
    document.getElementById('upload-results').innerHTML = '<div class="card-body text-center"><div class="spinner-border"></div><p>Uploading and executing dump...</p></div>';
    
    const formData = new FormData();
    formData.append('action', 'upload_dump');
    formData.append('dump_file', fileInput.files[0]);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.error) {
            document.getElementById('upload-results').innerHTML = `<div class="card-body"><div class="alert alert-danger">${result.error}</div></div>`;
            return;
        }
        
        let html = `<div class="card-body"><div class="alert alert-success">${result.message}</div>`;
        
        if (result.errors && result.errors.length > 0) {
            html += '<div class="alert alert-warning"><strong>Errors encountered:</strong><ul>';
            result.errors.forEach(error => {
                html += `<li>${error}</li>`;
            });
            html += '</ul></div>';
        }
        
        html += '</div>';
        document.getElementById('upload-results').innerHTML = html;
        
        // Refresh the current view if a table is selected
        if (currentTable) {
            setTimeout(() => selectTable(currentTable), 1000);
        }
    })
    .catch(error => {
        document.getElementById('upload-results').innerHTML = `<div class="card-body"><div class="alert alert-danger">Upload failed: ${error}</div></div>`;
    });
}

function executeSql() {
    const sql = document.getElementById('sql-input').value.trim();
    if (!sql) {
        alert('Please enter a SQL query');
        return;
    }
    
    document.getElementById('sql-results').innerHTML = '<div class="card-body text-center"><div class="spinner-border"></div><p>Executing query...</p></div>';
    
    const formData = new FormData();
    formData.append('action', 'execute_sql');
    formData.append('sql', sql);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.error) {
            document.getElementById('sql-results').innerHTML = `<div class="card-body"><div class="alert alert-danger">${result.error}</div></div>`;
            return;
        }
        
        if (!result.data || result.data.length === 0) {
            document.getElementById('sql-results').innerHTML = '<div class="card-body"><div class="alert alert-info">Query executed successfully. No data returned.</div></div>';
            return;
        }
        
        let html = `<div class="card-body"><div class="alert alert-success">Query executed successfully. ${result.rows} rows returned.</div><div class="table-responsive"><table class="table table-striped table-sm"><thead><tr>`;
        
        Object.keys(result.data[0]).forEach(col => {
            html += `<th>${col}</th>`;
        });
        html += '</tr></thead><tbody>';
        
        result.data.forEach(row => {
            html += '<tr>';
            Object.values(row).forEach(value => {
                html += `<td>${value || 'NULL'}</td>`;
            });
            html += '</tr>';
        });
        
        html += '</tbody></table></div></div>';
        document.getElementById('sql-results').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('sql-results').innerHTML = `<div class="card-body"><div class="alert alert-danger">Error: ${error}</div></div>`;
    });
}

function backupDatabase() {
    if (!currentDb) {
        alert('Please select a database first');
        return;
    }
    
    if (!confirm('Create backup of current database?')) return;
    
    fetch(`?action=backup_db&db=${encodeURIComponent(currentDb)}`)
        .then(response => response.json())
        .then(result => {
            if (result.error) {
                alert('Backup failed: ' + result.error);
                return;
            }
            alert('Backup created: ' + result.backup);
        })
        .catch(error => {
            alert('Backup failed: ' + error);
        });
}

function selectDatabase(dbPath) {
    currentDb = dbPath;
    currentTable = null;
    
    // Update visual indicators
    document.querySelectorAll('.db-btn').forEach(btn => btn.classList.remove('btn-primary'));
    document.querySelectorAll('.db-btn').forEach(btn => btn.classList.add('btn-outline-primary'));
    document.querySelector(`[data-db="${dbPath}"]`).classList.remove('btn-outline-primary');
    document.querySelector(`[data-db="${dbPath}"]`).classList.add('btn-primary');
    
    document.getElementById('backup-btn').disabled = false;
    document.getElementById('selected-db').textContent = dbPath.split('/').pop();
    document.getElementById('selected-table').style.display = 'none';
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
                html += `<button class="btn btn-outline-secondary btn-sm w-100 mb-1 text-start table-btn" data-table="${table.name}" onclick="selectTable('${table.name}')">${table.name}</button>`;
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
    
    // Update visual indicators
    document.querySelectorAll('.table-btn').forEach(btn => btn.classList.remove('btn-secondary'));
    document.querySelectorAll('.table-btn').forEach(btn => btn.classList.add('btn-outline-secondary'));
    document.querySelector(`[data-table="${tableName}"]`).classList.remove('btn-outline-secondary');
    document.querySelector(`[data-table="${tableName}"]`).classList.add('btn-secondary');
    
    document.getElementById('selected-table').style.display = 'inline';
    document.getElementById('table-name').textContent = tableName;
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