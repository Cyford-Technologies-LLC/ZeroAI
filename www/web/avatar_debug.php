<!DOCTYPE html>
<html>
<head>
    <title>Avatar Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; }
        .error { background: #ffebee; color: #c62828; }
        .success { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <h1>Avatar Debug Page</h1>
    
    <div class="debug">
        <h3>1. Avatar Icon Test</h3>
        <img src="../assets/frontend/images/icons/avatar.svg" alt="Avatar" style="width: 100px; height: 100px; border: 1px solid #ccc;">
        <p>Icon path: assets/frontend/images/icons/avatar.svg</p>
    </div>
    
    <div class="debug">
        <h3>2. Service Connection Test</h3>
        <button onclick="testService()">Test Avatar Service</button>
        <div id="serviceResult"></div>
    </div>
    
    <div class="debug">
        <h3>3. Generate Test</h3>
        <button onclick="generateTest()">Generate Avatar</button>
        <div id="generateResult"></div>
    </div>

    <script>
    async function testService() {
        const result = document.getElementById('serviceResult');
        result.innerHTML = 'Testing...';
        
        try {
            const response = await fetch('api/avatar_test.php');
            const data = await response.json();
            result.innerHTML = `<div class="${data.status === 'success' ? 'success' : 'error'}">${JSON.stringify(data, null, 2)}</div>`;
        } catch (error) {
            result.innerHTML = `<div class="error">Error: ${error.message}</div>`;
        }
    }
    
    async function generateTest() {
        const result = document.getElementById('generateResult');
        result.innerHTML = 'Generating...';
        
        try {
            const response = await fetch('api/avatar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt: 'Hello test', image: 'test.png' })
            });
            
            if (response.ok) {
                result.innerHTML = '<div class="success">Generation successful!</div>';
            } else {
                const text = await response.text();
                result.innerHTML = `<div class="error">Generation failed: ${text}</div>`;
            }
        } catch (error) {
            result.innerHTML = `<div class="error">Error: ${error.message}</div>`;
        }
    }
    </script>
</body>
</html>