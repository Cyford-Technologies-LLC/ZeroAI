<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'Active Sessions - ZeroAI';
$currentPage = 'sessions';
include __DIR__ . '/includes/header.php';
?>

<h1>👥 Active Sessions</h1>

<div class="card">
    <h3>Current Session</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding: 10px; border: 1px solid #ddd;">Session ID</th>
                <th style="padding: 10px; border: 1px solid #ddd;">User</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Role</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Login Time</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><?= session_id() ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?= $_SESSION['user_name'] ?? $_SESSION['admin_user'] ?? 'Unknown' ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #007bff; color: white;">
                        <?= ucfirst($_SESSION['user_role'] ?? 'admin') ?>
                    </span>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?= date('M j, Y g:i A') ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <span style="color: #28a745;">🟢 Active</span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Session Information</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Session Timeout:</strong> <?= ini_get('session.gc_maxlifetime') ?> seconds
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Cookie Lifetime:</strong> <?= ini_get('session.cookie_lifetime') ?> seconds
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Session Name:</strong> <?= session_name() ?>
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Session Status:</strong> <?= session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive' ?>
        </div>
    </div>
</div>

<div class="card">
    <h3>Session Actions</h3>
    <p>Manage your current session:</p>
    <a href="/admin/logout.php" class="btn-danger">🚪 Logout</a>
    <button onclick="regenerateSession()" class="btn-warning">🔄 Regenerate Session ID</button>
</div>

<script>
function regenerateSession() {
    if (confirm('Regenerate session ID? This will create a new session ID but keep you logged in.')) {
        fetch('/admin/regenerate_session.php', {method: 'POST'})
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to regenerate session');
                }
            });
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
