<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: /admin/login');
    exit;
}

$pageTitle = 'System Monitoring';
$currentPage = 'monitoring';
require_once 'includes/header.php';
?>

<div class="card">
    <h2>📊 System Monitoring</h2>
    <p>Real-time monitoring of ZeroAI system components</p>
</div>

<div class="card">
    <h3>🤖 AI Services Status</h3>
    <div id="ai-status">
        <p>Loading AI service status...</p>
    </div>
</div>

<div class="card">
    <h3>🔄 Active Crews</h3>
    <div id="active-crews">
        <p>Loading active crews...</p>
    </div>
</div>

<div class="card">
    <h3>💾 System Resources</h3>
    <div id="system-resources">
        <p>Loading system resources...</p>
    </div>
</div>

<script>
async function loadMonitoringData() {
    try {
        // Load AI service status
        const aiResponse = await fetch('/api/system_status.php?type=ai');
        const aiData = await aiResponse.json();
        document.getElementById('ai-status').innerHTML = formatAIStatus(aiData);

        // Load active crews
        const crewResponse = await fetch('/api/system_status.php?type=crews');
        const crewData = await crewResponse.json();
        document.getElementById('active-crews').innerHTML = formatActiveCrews(crewData);

        // Load system resources
        const resourceResponse = await fetch('/api/system_status.php?type=resources');
        const resourceData = await resourceResponse.json();
        document.getElementById('system-resources').innerHTML = formatSystemResources(resourceData);
    } catch (error) {
        console.error('Error loading monitoring data:', error);
    }
}

function formatAIStatus(data) {
    if (!data.success) return '<p class="error">Failed to load AI status</p>';
    
    let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
    
    if (data.ollama) {
        html += `<div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <strong>Ollama</strong><br>
            Status: <span style="color: ${data.ollama.status === 'running' ? 'green' : 'red'}">${data.ollama.status}</span><br>
            Models: ${data.ollama.models || 0}
        </div>`;
    }
    
    if (data.claude) {
        html += `<div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <strong>Claude</strong><br>
            Status: <span style="color: ${data.claude.status === 'available' ? 'green' : 'red'}">${data.claude.status}</span>
        </div>`;
    }
    
    html += '</div>';
    return html;
}

function formatActiveCrews(data) {
    if (!data.success || !data.crews || data.crews.length === 0) {
        return '<p>No active crews running</p>';
    }
    
    let html = '<table style="width: 100%; border-collapse: collapse;">';
    html += '<tr><th style="border: 1px solid #ddd; padding: 8px;">Crew ID</th><th style="border: 1px solid #ddd; padding: 8px;">Status</th><th style="border: 1px solid #ddd; padding: 8px;">Started</th></tr>';
    
    data.crews.forEach(crew => {
        html += `<tr>
            <td style="border: 1px solid #ddd; padding: 8px;">${crew.id}</td>
            <td style="border: 1px solid #ddd; padding: 8px;">${crew.status}</td>
            <td style="border: 1px solid #ddd; padding: 8px;">${crew.started}</td>
        </tr>`;
    });
    
    html += '</table>';
    return html;
}

function formatSystemResources(data) {
    if (!data.success) return '<p class="error">Failed to load system resources</p>';
    
    return `<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <strong>CPU Usage</strong><br>
            ${data.cpu || 'N/A'}%
        </div>
        <div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <strong>Memory Usage</strong><br>
            ${data.memory || 'N/A'}%
        </div>
        <div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <strong>Disk Usage</strong><br>
            ${data.disk || 'N/A'}%
        </div>
    </div>`;
}

// Load data on page load and refresh every 30 seconds
loadMonitoringData();
setInterval(loadMonitoringData, 30000);
</script>

<?php require_once 'includes/footer.php'; ?>