<?php
$pageTitle = 'Claude Memory Debug - ZeroAI';
$currentPage = 'debug';
include __DIR__ . '/includes/header.php';
?>

<h1>🔍 Claude Memory Debug</h1>

<div class="card">
    <h3>Debug Information</h3>
    
    <div style="margin-bottom: 20px;">
        <button onclick="clearDebugLogs()" class="btn-danger">Clear Debug Logs</button>
        <button onclick="testConversation()" class="btn-success">Test Conversation</button>
        <button onclick="location.reload()" class="btn-primary">Refresh</button>
    </div>
    
    <div class="log-section">
        <h4>Recent Debug Logs</h4>
        <div class="log-content" style="background: #000; color: #0f0; font-family: monospace; padding: 15px; border-radius: 3px; max-height: 400px; overflow-y: auto; white-space: pre-wrap;">
<?php
$errorLog = '/var/log/nginx/error.log';
if (file_exists($errorLog)) {
    $lines = file($errorLog, FILE_IGNORE_NEW_LINES);
    $debugLines = array_filter($lines, function($line) {
        return strpos($line, 'Claude History Debug') !== false || 
               strpos($line, 'Recent History Count') !== false ||
               strpos($line, 'Processed message') !== false ||
               strpos($line, 'Final messages to Claude') !== false;
    });
    $debugLines = array_reverse(array_slice($debugLines, -20));
    
    foreach ($debugLines as $line) {
        echo htmlspecialchars($line) . "\n";
    }
} else {
    echo "Error log not found";
}
?>
        </div>
    </div>
    
    <div class="log-section">
        <h4>Browser localStorage Check</h4>
        <div id="localStorage-info" style="background: #f8f9fa; padding: 15px; border-radius: 3px; font-family: monospace;"></div>
    </div>
    
    <div class="log-section">
        <h4>Test Conversation History</h4>
        <textarea id="test-message" placeholder="Type a test message..." rows="3" style="width: 100%; margin-bottom: 10px;"></textarea>
        <button onclick="sendTestMessage()" class="btn-primary">Send Test Message</button>
        <div id="test-result" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 3px; font-family: monospace; white-space: pre-wrap;"></div>
    </div>
</div>

<script>
// Check localStorage
function checkLocalStorage() {
    const history = localStorage.getItem('claude_chat_history');
    const info = document.getElementById('localStorage-info');
    
    if (history) {
        const parsed = JSON.parse(history);
        info.innerHTML = `
History entries: ${parsed.length}
Last entry: ${parsed.length > 0 ? parsed[parsed.length - 1].sender + ': ' + parsed[parsed.length - 1].message.substring(0, 50) + '...' : 'None'}
Storage size: ${history.length} characters
        `;
    } else {
        info.innerHTML = 'No conversation history found in localStorage';
    }
}

function clearDebugLogs() {
    if (confirm('Clear debug logs?')) {
        fetch('/admin/clear_debug_logs.php', {method: 'POST'})
            .then(() => location.reload());
    }
}

function testConversation() {
    const history = JSON.parse(localStorage.getItem('claude_chat_history') || '[]');
    console.log('Current history:', history);
    alert(`History has ${history.length} entries. Check console for details.`);
}

async function sendTestMessage() {
    const message = document.getElementById('test-message').value;
    if (!message) return;
    
    const history = JSON.parse(localStorage.getItem('claude_chat_history') || '[]');
    
    try {
        const response = await fetch('/admin/chat_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                message: message,
                model: 'claude-3-5-sonnet-20241022',
                autonomous: false,
                history: history
            })
        });
        
        const result = await response.json();
        document.getElementById('test-result').textContent = JSON.stringify(result, null, 2);
        
        // Add to history
        history.push({sender: 'You', message: message, type: 'user', timestamp: new Date()});
        if (result.success) {
            history.push({sender: 'Claude', message: result.response, type: 'claude', timestamp: new Date()});
        }
        localStorage.setItem('claude_chat_history', JSON.stringify(history));
        
        checkLocalStorage();
    } catch (error) {
        document.getElementById('test-result').textContent = 'Error: ' + error.message;
    }
}

// Check on load
checkLocalStorage();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>