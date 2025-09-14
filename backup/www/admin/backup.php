<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: /admin/login');
    exit;
}

$pageTitle = 'System Backup';
$currentPage = 'backup';
require_once 'includes/header.php';

$message = '';
if ($_POST['action'] ?? '' === 'create_backup') {
    $backup_name = $_POST['backup_name'] ?? 'backup_' . date('Y-m-d_H-i-s');
    $include_database = isset($_POST['include_database']);
    $include_config = isset($_POST['include_config']);
    $include_knowledge = isset($_POST['include_knowledge']);
    
    // Create backup via API
    $backup_data = [
        'name' => $backup_name,
        'include_database' => $include_database,
        'include_config' => $include_config,
        'include_knowledge' => $include_knowledge
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/backup.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($backup_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    if ($result['success'] ?? false) {
        $message = '<div class="message">Backup created successfully: ' . $result['backup_file'] . '</div>';
    } else {
        $message = '<div class="message error">Backup failed: ' . ($result['error'] ?? 'Unknown error') . '</div>';
    }
}
?>

<div class="card">
    <h2>💾 System Backup</h2>
    <p>Create and manage system backups</p>
    <?= $message ?>
</div>

<div class="card">
    <h3>Create New Backup</h3>
    <form method="POST">
        <input type="hidden" name="action" value="create_backup">
        
        <label>Backup Name:</label>
        <input type="text" name="backup_name" placeholder="backup_<?= date('Y-m-d_H-i-s') ?>" value="">
        
        <div style="margin: 15px 0;">
            <label><input type="checkbox" name="include_database" checked> Include Database</label><br>
            <label><input type="checkbox" name="include_config" checked> Include Configuration Files</label><br>
            <label><input type="checkbox" name="include_knowledge" checked> Include Knowledge Base</label>
        </div>
        
        <button type="submit" class="btn-success">Create Backup</button>
    </form>
</div>

<div class="card">
    <h3>Available Backups</h3>
    <div id="backup-list">
        <p>Loading backup list...</p>
    </div>
</div>

<script>
async function loadBackupList() {
    try {
        const response = await fetch('/api/backup.php?action=list');
        const data = await response.json();
        
        if (data.success && data.backups) {
            let html = '<table style="width: 100%; border-collapse: collapse;">';
            html += '<tr><th style="border: 1px solid #ddd; padding: 8px;">Name</th><th style="border: 1px solid #ddd; padding: 8px;">Date</th><th style="border: 1px solid #ddd; padding: 8px;">Size</th><th style="border: 1px solid #ddd; padding: 8px;">Actions</th></tr>';
            
            data.backups.forEach(backup => {
                html += `<tr>
                    <td style="border: 1px solid #ddd; padding: 8px;">${backup.name}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${backup.date}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${backup.size}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        <button onclick="downloadBackup('${backup.name}')" class="btn-success">Download</button>
                        <button onclick="deleteBackup('${backup.name}')" class="btn-danger">Delete</button>
                    </td>
                </tr>`;
            });
            
            html += '</table>';
            document.getElementById('backup-list').innerHTML = html;
        } else {
            document.getElementById('backup-list').innerHTML = '<p>No backups found</p>';
        }
    } catch (error) {
        document.getElementById('backup-list').innerHTML = '<p class="error">Error loading backup list</p>';
    }
}

function downloadBackup(name) {
    window.location.href = `/api/backup.php?action=download&name=${encodeURIComponent(name)}`;
}

async function deleteBackup(name) {
    if (!confirm(`Are you sure you want to delete backup: ${name}?`)) return;
    
    try {
        const response = await fetch('/api/backup.php', {
            method: 'DELETE',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name: name})
        });
        
        const result = await response.json();
        if (result.success) {
            loadBackupList();
        } else {
            alert('Failed to delete backup: ' + result.error);
        }
    } catch (error) {
        alert('Error deleting backup');
    }
}

loadBackupList();
</script>

<?php require_once 'includes/footer.php'; ?>