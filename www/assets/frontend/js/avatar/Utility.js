// Utility Functions
function debugLog(message, data = null) {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${message}`, data || '');

    if (debugMode) {
        const logEntry = `${timestamp}: ${message}${data ? ' - ' + JSON.stringify(data, null, 2) : ''}`;
        appendToLog('logContainer', logEntry);
    }
}

function appendToLog(containerId, message) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML += message + '\n';
        container.scrollTop = container.scrollHeight;
    }
}

function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.getElementById('notification');
    if (notification) {
        notification.textContent = message;
        notification.className = `notification ${type} show`;

        setTimeout(() => {
            notification.classList.remove('show');
        }, duration);
    }
}

function updateProgress(percent, text = '') {
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    if (progressFill) progressFill.style.width = percent + '%';
    if (progressText && text) progressText.textContent = text;
}