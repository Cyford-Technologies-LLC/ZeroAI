
async function getLogs() {
    debugLog('Getting avatar service logs');

    try {
        const response = await fetch('/web/api/avatar_dual.php?action=logs');
        const result = await response.json();

        debugLog('Logs received', { logCount: result.data?.logs?.length || 0 });

        const container = document.getElementById('logContainer');
        if (container) {
            if (result.data && result.data.logs) {
                const logText = Array.isArray(result.data.logs) ? result.data.logs.join('\n') : result.data.logs;
                container.innerHTML = logText;
                container.setAttribute('data-all-logs', logText);
            } else {
                container.innerHTML = 'No logs available';
            }
        }

        showNotification('Logs retrieved successfully', 'success');

    } catch (error) {
        debugLog('Log retrieval error', { error: error.message });
        showNotification('Failed to retrieve logs', 'error');
    }
}

// Log Management
function refreshStatus() {
    getStatus();
}

function refreshLogs() {
    getLogs();
}

function clearLogs() {
    const container = document.getElementById('logContainer');
    if (container) {
        container.innerHTML = 'Logs cleared';
    }
    debugLog('Debug logs cleared');
    showNotification('Logs cleared', 'info');
}

function clearDebugLogs() {
    const container = document.getElementById('logContainer');
    if (container) {
        container.innerHTML = 'Debug logs cleared';
    }
    console.clear();
    debugLog('Debug console cleared');
    showNotification('Debug logs cleared', 'info');
}

function downloadLogs() {
    const container = document.getElementById('logContainer');
    if (container) {
        const logs = container.textContent;
        const blob = new Blob([logs], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `avatar_debug_logs_${new Date().toISOString()}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        debugLog('Debug logs downloaded');
        showNotification('Logs downloaded', 'success');
    }
}

function copyLogsToClipboard() {
    const container = document.getElementById('logContainer');
    if (container && navigator.clipboard) {
        const logs = container.textContent;
        navigator.clipboard.writeText(logs).then(() => {
            debugLog('Debug logs copied to clipboard');
            showNotification('Logs copied to clipboard', 'success');
        }).catch(err => {
            debugLog('Failed to copy logs to clipboard', { error: err.message });
            showNotification('Failed to copy logs', 'error');
        });
    } else {
        showNotification('Clipboard not available', 'error');
    }
}

function filterLogs() {
    const searchTerm = prompt('Enter search term for logs:');
    if (searchTerm) {
        searchLogs(searchTerm);
    }
}

function filterLogsByLevel() {
    const levelSelect = document.getElementById('logLevel');
    const level = levelSelect ? levelSelect.value : 'all';
    const container = document.getElementById('logContainer');

    if (!container) return;

    const allLogs = container.getAttribute('data-all-logs') || container.textContent;

    if (level === 'all') {
        container.innerHTML = allLogs;
    } else {
        const filteredLogs = allLogs.split('\n').filter(line =>
            line.toLowerCase().includes(level.toLowerCase())
        ).join('\n');
        container.innerHTML = filteredLogs || 'No logs found for this level';
    }
}

function searchLogs(searchTerm = null) {
    const searchInput = document.getElementById('logSearch');
    const term = searchTerm || (searchInput ? searchInput.value : '');
    const container = document.getElementById('logContainer');

    if (!container) return;

    const allLogs = container.getAttribute('data-all-logs') || container.textContent;

    if (!term) {
        container.innerHTML = allLogs;
        return;
    }

    const filteredLogs = allLogs.split('\n').filter(line =>
        line.toLowerCase().includes(term.toLowerCase())
    ).join('\n');

    container.innerHTML = filteredLogs || 'No logs found for search term';
}
