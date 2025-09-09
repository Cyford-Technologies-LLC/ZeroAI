<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: /admin/login');
    exit;
}

$pageTitle = 'Error Logs - Real-time';
$currentPage = 'error_logs';
require_once 'includes/header.php';
?>

<div class="card">
    <h2>🚨 Real-time Error Logs</h2>
    <p>Live monitoring of ZeroAI system errors and warnings</p>
    
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <button onclick="toggleAutoRefresh()" id="auto-refresh-btn" class="btn-success">▶️ Start Auto-refresh</button>
        <button onclick="clearLogs()" class="btn-warning">🗑️ Clear Display</button>
        <button onclick="downloadLogs()" class="btn-primary">💾 Download Logs</button>
        <select id="log-level" onchange="filterLogs()" style="padding: 8px;">
            <option value="all">All Levels</option>
            <option value="error">Errors Only</option>
            <option value="warning">Warnings Only</option>
            <option value="info">Info Only</option>
        </select>
    </div>
    
    <div id="log-status" style="padding: 10px; background: #f8f9fa; border-radius: 4px; margin-bottom: 15px;">
        <span id="status-text">Ready to monitor logs</span> | 
        <span id="log-count">0 entries</span> | 
        <span id="last-update">Never updated</span>
    </div>
</div>

<div class="card">
    <div id="log-container" style="height: 600px; overflow-y: auto; background: #1a1a1a; color: #00ff00; font-family: 'Courier New', monospace; padding: 15px; border-radius: 8px; font-size: 12px; line-height: 1.4;">
        <div id="log-content">
            <div style="color: #888;">Waiting for log data...</div>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval = null;
let isAutoRefreshing = false;
let logEntries = [];
let currentFilter = 'all';

async function fetchLogs() {
    try {
        const response = await fetch('/api/logs.php?type=error&format=json');
        const data = await response.json();
        
        if (data.success) {
            logEntries = data.logs;
            displayLogs();
            updateStatus(data.logs.length);
        } else {
            addLogEntry('SYSTEM', 'ERROR', 'Failed to fetch logs: ' + data.error);
        }
    } catch (error) {
        addLogEntry('SYSTEM', 'ERROR', 'Connection error: ' + error.message);
    }
}

function displayLogs() {
    const logContent = document.getElementById('log-content');
    const filteredLogs = filterLogsByLevel(logEntries);
    
    if (filteredLogs.length === 0) {
        logContent.innerHTML = '<div style="color: #888;">No logs match current filter</div>';
        return;
    }
    
    let html = '';
    filteredLogs.forEach(log => {
        const color = getLogColor(log.level);
        const timestamp = new Date(log.timestamp).toLocaleString();
        
        html += `<div style="margin-bottom: 8px; border-left: 3px solid ${color}; padding-left: 10px;">
            <span style="color: #666;">[${timestamp}]</span> 
            <span style="color: ${color}; font-weight: bold;">${log.level.toUpperCase()}</span> 
            <span style="color: #ccc;">${log.source}:</span> 
            <span style="color: #fff;">${escapeHtml(log.message)}</span>
        </div>`;
    });
    
    logContent.innerHTML = html;
    
    // Auto-scroll to bottom
    const container = document.getElementById('log-container');
    container.scrollTop = container.scrollHeight;
}

function filterLogsByLevel(logs) {
    if (currentFilter === 'all') return logs;
    return logs.filter(log => log.level.toLowerCase() === currentFilter);
}

function getLogColor(level) {
    switch (level.toLowerCase()) {
        case 'error': return '#ff4444';
        case 'warning': return '#ffaa00';
        case 'info': return '#44aaff';
        case 'debug': return '#888888';
        default: return '#00ff00';
    }
}

function addLogEntry(source, level, message) {
    const entry = {
        timestamp: new Date().toISOString(),
        source: source,
        level: level,
        message: message
    };
    
    logEntries.unshift(entry);
    if (logEntries.length > 1000) {
        logEntries = logEntries.slice(0, 1000); // Keep only last 1000 entries
    }
    
    displayLogs();
    updateStatus(logEntries.length);
}

function toggleAutoRefresh() {
    const btn = document.getElementById('auto-refresh-btn');
    
    if (isAutoRefreshing) {
        clearInterval(autoRefreshInterval);
        btn.textContent = '▶️ Start Auto-refresh';
        btn.className = 'btn-success';
        isAutoRefreshing = false;
        updateStatusText('Auto-refresh stopped');
    } else {
        autoRefreshInterval = setInterval(fetchLogs, 2000); // Refresh every 2 seconds
        btn.textContent = '⏸️ Stop Auto-refresh';
        btn.className = 'btn-danger';
        isAutoRefreshing = true;
        updateStatusText('Auto-refreshing every 2 seconds');
        fetchLogs(); // Immediate fetch
    }
}

function clearLogs() {
    logEntries = [];
    document.getElementById('log-content').innerHTML = '<div style="color: #888;">Logs cleared</div>';
    updateStatus(0);
}

function downloadLogs() {
    const logText = logEntries.map(log => 
        `[${log.timestamp}] ${log.level.toUpperCase()} ${log.source}: ${log.message}`
    ).join('\n');
    
    const blob = new Blob([logText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `zeroai_error_logs_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function filterLogs() {
    currentFilter = document.getElementById('log-level').value;
    displayLogs();
}

function updateStatus(count) {
    document.getElementById('log-count').textContent = `${count} entries`;
    document.getElementById('last-update').textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
}

function updateStatusText(text) {
    document.getElementById('status-text').textContent = text;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initial load
fetchLogs();
</script>

<?php require_once 'includes/footer.php'; ?>