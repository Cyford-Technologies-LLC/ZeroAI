<?php 
$pageTitle = 'Crew Chat - ZeroAI';
$currentPage = 'crew_chat';
include __DIR__ . '/includes/header.php';
?>
?>

<h1>Real-time Crew Chat</h1>

<div class="card">
    <h3>Chat with Your ZeroAI Crew</h3>
    <p>Talk directly to your DevOps crew agents. Watch them work in real-time!</p>
    
    <div id="chat-form">
        <label for="project">Project:</label>
        <select id="project">
            <option value="zeroai">ZeroAI</option>
            <option value="testcorp">TestCorp</option>
            <option value="custom">Custom</option>
        </select>
        
        <label for="message">Task/Question:</label>
        <textarea id="message" placeholder="Ask your crew to help with development tasks, code reviews, or project management..." rows="4"></textarea>
        
        <button id="send-btn" class="btn-success">Send to Crew</button>
        <button id="stop-btn" class="btn-danger" style="display: none;">Stop Task</button>
    </div>
    
    <div id="chat-output" style="background: #000; color: #0f0; padding: 15px; border-radius: 8px; margin-top: 15px; height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; display: none;">
        <div id="output-content"></div>
    </div>
    
    <div id="status" style="margin-top: 10px; font-weight: bold;"></div>
</div>

<script>
let eventSource = null;

document.getElementById('send-btn').addEventListener('click', function() {
    const message = document.getElementById('message').value.trim();
    const project = document.getElementById('project').value;
    
    if (!message) {
        alert('Please enter a message');
        return;
    }
    
    startStreaming(message, project);
});

document.getElementById('stop-btn').addEventListener('click', function() {
    stopStreaming();
    
    // Also send stop signal to backend
    fetch('/admin/crew_stop.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('status').innerHTML = '🛑 All processes stopped';
        } else {
            console.error('Stop failed:', data.error);
        }
    })
    .catch(error => {
        console.error('Stop request failed:', error);
    });
});

function startStreaming(message, project) {
    const outputDiv = document.getElementById('chat-output');
    const contentDiv = document.getElementById('output-content');
    const statusDiv = document.getElementById('status');
    const sendBtn = document.getElementById('send-btn');
    const stopBtn = document.getElementById('stop-btn');
    
    // Show output area and update UI
    outputDiv.style.display = 'block';
    contentDiv.innerHTML = '';
    statusDiv.innerHTML = '🚀 Starting crew...';
    sendBtn.style.display = 'none';
    stopBtn.style.display = 'inline-block';
    
    // Start EventSource for streaming
    const url = `/admin/crew_stream.php?message=${encodeURIComponent(message)}&project=${encodeURIComponent(project)}`;
    eventSource = new EventSource(url);
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        
        if (data.error) {
            contentDiv.innerHTML += `<div style="color: #f00;">❌ Error: ${data.error}</div>`;
            statusDiv.innerHTML = '❌ Error occurred';
            stopStreaming();
        } else if (data.status === 'started') {
            statusDiv.innerHTML = '⚡ Crew is working...';
        } else if (data.status === 'completed') {
            statusDiv.innerHTML = '✅ Task completed!';
            stopStreaming();
        } else if (data.type === 'output') {
            contentDiv.innerHTML += `<div>[${data.timestamp}] ${data.content}</div>`;
            outputDiv.scrollTop = outputDiv.scrollHeight;
        }
    };
    
    eventSource.onerror = function() {
        statusDiv.innerHTML = '❌ Connection error';
        stopStreaming();
    };
}

function stopStreaming() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    
    document.getElementById('send-btn').style.display = 'inline-block';
    document.getElementById('stop-btn').style.display = 'none';
    
    // Don't immediately change status - let the stop request handle it
    if (!document.getElementById('status').innerHTML.includes('stopped')) {
        document.getElementById('status').innerHTML = '⏹️ Stopping...';
    }
}
</script>

<div class="card">
    <h3>Quick Tasks</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Show me the current project status and what containers are running">
            <button type="submit" class="btn-primary" style="width: 100%;">Project Status</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Review the latest code changes and suggest improvements">
            <button type="submit" class="btn-primary" style="width: 100%;">Code Review</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Help me debug any issues in the current setup">
            <button type="submit" class="btn-primary" style="width: 100%;">Debug Help</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_crew">
            <input type="hidden" name="project" value="zeroai">
            <input type="hidden" name="message" value="Create a simple Python function to demonstrate the system">
            <button type="submit" class="btn-primary" style="width: 100%;">Demo Task</button>
        </form>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


