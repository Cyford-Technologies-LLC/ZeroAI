<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-robot"></i> Avatar Test</h4>
                </div>
                <div class="card-body">
                    <form id="avatarForm">
                        <div class="mb-3">
                            <label for="prompt" class="form-label">Text Prompt</label>
                            <textarea class="form-control" id="prompt" rows="3" placeholder="Enter text for avatar to speak...">Hello! Welcome to ZeroAI. I'm your AI assistant ready to help you with any task.</textarea>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Source Image (optional)</label>
                            <input type="text" class="form-control" id="image" placeholder="examples/source_image/art_0.png" value="examples/source_image/art_0.png">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Generate Avatar
                        </button>
                    </form>
                    
                    <div id="loading" class="text-center mt-3" style="display: none;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Generating avatar video... This may take a few minutes.</p>
                    </div>
                    
                    <div id="result" class="mt-4" style="display: none;">
                        <h5>Generated Avatar Video:</h5>
                        <video id="avatarVideo" controls width="100%" style="max-width: 600px;">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                    
                    <div id="error" class="alert alert-danger mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const prompt = document.getElementById('prompt').value;
    const image = document.getElementById('image').value;
    
    document.getElementById('loading').style.display = 'block';
    document.getElementById('result').style.display = 'none';
    document.getElementById('error').style.display = 'none';
    
    try {
        // Try test port first, fallback to production port
        let avatarUrl = 'http://localhost:7861/generate';
        try {
            await fetch('http://localhost:7861/health');
        } catch {
            avatarUrl = 'http://localhost:7860/generate';
        }
        
        const response = await fetch(avatarUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                prompt: prompt,
                image: image
            })
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const videoUrl = URL.createObjectURL(blob);
            
            document.getElementById('avatarVideo').src = videoUrl;
            document.getElementById('result').style.display = 'block';
        } else {
            throw new Error('Failed to generate avatar');
        }
    } catch (error) {
        document.getElementById('error').textContent = 'Error: ' + error.message;
        document.getElementById('error').style.display = 'block';
    } finally {
        document.getElementById('loading').style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>