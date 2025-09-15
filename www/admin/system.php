<?php 
$pageTitle = 'System Overview - ZeroAI';
$currentPage = 'system';
include __DIR__ . '/includes/header.php';
?>

<h1>🖥️ System Overview</h1>

<div class="card">
    <h3>System Resources</h3>
    <p>Monitor and manage your ZeroAI system resources across local and distributed infrastructure.</p>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        <a href="/admin/localhost" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #007bff;">🖥️ Local Host</h4>
                <p style="margin: 0; color: #666;">View current server resources, Ollama status, and available models</p>
            </div>
        </a>
        
        <a href="/admin/peers" style="text-decoration: none; color: inherit;">
            <div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; text-align: center; transition: background 0.3s;">
                <h4 style="margin: 0 0 10px 0; color: #28a745;">🌐 Peers</h4>
                <p style="margin: 0; color: #666;">Monitor distributed peer resources and network status</p>
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


