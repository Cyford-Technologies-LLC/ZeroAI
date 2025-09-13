<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: /admin/login');
    exit;
}

$pageTitle = 'System Restore';
$currentPage = 'restore';
require_once 'includes/header.php';

$message = '';
if ($_POST['action'] ?? '' === 'restore_backup') {
    $backup_name = $_POST['backup_name'] ?? '';
    $restore_database = isset($_POST['restore_database']);
    $restore_config = isset($_POST['restore_config']);
    $restore_knowledge = isset($_POST['restore_knowledge']);
    
    if ($backup_name) {
        $restore_data = [
            'backup_name' => $backup_name,
            'restore_database' => $restore_database,
            'restore_config' => $restore_config,
            'restore_knowledge' => $restore_knowledge
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/restore.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($restore_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        
        if ($result['success'] ?? false) {
            $message = '<div class="message">System restored successfully from: ' . $backup_name . '</div>';
        } else {
            $message = '<div class="message error">Restore failed: ' . ($result['error'] ?? 'Unknown error') . '</div>';
        }
    } else {
        $message = '<div class="message error">Please select a backup to restore</div>';
    }
}
?>

<div class="card">
    <h2>🔄 System Restore</h2>
    <p>Restore system from backup</p>
    <?= $message ?>
</div>

<div class="card" style="background: #fff3cd; border: 1px solid #ffeaa7;">
    <h3>⚠️ Important Warning</h3>
    <p><strong>Restoring from backup will overwrite current data!</strong></p>
    <ul>
        <li>Database restore will replace all current database data</li>
        <li>Configuration restore will overwrite current settings</li>
        <li>Knowledge base restore will replace current knowledge files</li>
        <li>Make sure to create a backup of current state before restoring</li>
    </ul>
</div>

<div class="card">
    <h3>Restore from Backup</h3>
    <form method="POST" onsubmit="return confirmRestore()">
        <input type="hidden" name="action" value="restore_backup">
        
        <label>Select Backup:</label>
        <select name="backup_name" required>
            <option value="">-- Select Backup --</option>
            <option value="loading">Loading backups...</option>
        </select>
        
        <div style="margin: 15px 0;">
            <label><input type="checkbox" name="restore_database" checked> Restore Database</label><br>
            <label><input type="checkbox" name="restore_config" checked> Restore Configuration Files</label><br>
            <label><input type="checkbox" name="restore_knowledge" checked> Restore Knowledge Base</label>
        </div>
        
        <button type="submit" class="btn-warning">Restore System</button>
    </form>
</div>

<div class="card">
    <h3>Upload Backup File</h3>
    <form id="upload-form" enctype="multipart/form-data">
        <label>Select Backup File (.zip):</label>
        <input type="file" name="backup_file" accept=".zip" required>
        <button type="submit" class="btn-success">Upload Backup</button>
    </form>
    <div id="upload-status"></div>
</div>

<script>
async function loadBackupOptions() {
    try {
        const response = await fetch('/api/backup.php?action=list');
        const data = await response.json();
        
        const select = document.querySelector('select[name="backup_name"]');
        select.innerHTML = '<option value="">-- Select Backup --</option>';
        
        if (data.success && data.backups) {
            data.backups.forEach(backup => {
                const option = document.createElement('option');
                option.value = backup.name;
                option.textContent = `${backup.name} (${backup.date} - ${backup.size})`;
                select.appendChild(option);
            });
        } else {
            select.innerHTML = '<option value="">No backups available</option>';
        }
    } catch (error) {
        const select = document.querySelector('select[name="backup_name"]');
        select.innerHTML = '<option value="">Error loading backups</option>';
    }
}

function confirmRestore() {
    const backupName = document.querySelector('select[name="backup_name"]').value;
    if (!backupName) {
        alert('Please select a backup to restore');
        return false;
    }
    
    return confirm(`Are you sure you want to restore from backup: ${backupName}?\n\nThis will overwrite current data and cannot be undone!`);
}

document.getElementById('upload-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const statusDiv = document.getElementById('upload-status');
    
    statusDiv.innerHTML = '<p>Uploading backup file...</p>';
    
    try {
        const response = await fetch('/api/backup.php?action=upload', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            statusDiv.innerHTML = '<div class="message">Backup uploaded successfully</div>';
            loadBackupOptions(); // Refresh backup list
            this.reset();
        } else {
            statusDiv.innerHTML = '<div class="message error">Upload failed: ' + result.error + '</div>';
        }
    } catch (error) {
        statusDiv.innerHTML = '<div class="message error">Upload error: ' + error.message + '</div>';
    }
});

loadBackupOptions();
</script>

<?php require_once 'includes/footer.php'; ?>