<?php 
$pageTitle = 'Tools Overview - ZeroAI';
$currentPage = 'tools';
include __DIR__ . '/includes/header.php';
?>

<h1>🔧 Tools Overview</h1>

<div class="card">
    <h3>Development & Monitoring Tools</h3>
    <p>Access various tools for monitoring, debugging, and managing your ZeroAI system.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        <a href="/admin/monitoring" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #28a745;">📊 System Monitoring</h4>
                <p style="margin: 0; color: #666;">Monitor system health, performance metrics, and resource usage</p>
            </div>
        </a>
        
        <a href="/admin/error_logs.php" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #007bff;">📋 Logs</h4>
                <p style="margin: 0; color: #666;">View and analyze system logs and error reports</p>
            </div>
        </a>
        
        <a href="/admin/performance" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #ffc107; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #f57c00;">⚡ Performance</h4>
                <p style="margin: 0; color: #666;">Analyze performance metrics and optimization opportunities</p>
            </div>
        </a>
        
        <a href="/admin/api_tester" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #6f42c1; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #6f42c1;">🧪 API Tester</h4>
                <p style="margin: 0; color: #666;">Test API endpoints and debug integration issues</p>
            </div>
        </a>
        
        <a href="/admin/database_viewer" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #dc3545; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #dc3545;">🗄️ Database Viewer</h4>
                <p style="margin: 0; color: #666;">Browse and manage database tables and records</p>
            </div>
        </a>
        
        <a href="/admin/file_manager" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #17a2b8; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #17a2b8;">📁 File Manager</h4>
                <p style="margin: 0; color: #666;">Browse and manage system files and configurations</p>
            </div>
        </a>
        
        <a href="/admin/error_logs" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #dc3545; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #dc3545;">🚨 Error Logs</h4>
                <p style="margin: 0; color: #666;">Real-time error monitoring and log analysis</p>
            </div>
        </a>
        
        <a href="/admin/backup" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #28a745;">💾 Backup</h4>
                <p style="margin: 0; color: #666;">Create and manage system backups</p>
            </div>
        </a>
        
        <a href="/admin/restore" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #ffc107; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #f57c00;">🔄 Restore</h4>
                <p style="margin: 0; color: #666;">Restore system from backups</p>
            </div>
        </a>
    </div>
</div>

<style>
a div:hover {
    background: #f8f9fa;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>