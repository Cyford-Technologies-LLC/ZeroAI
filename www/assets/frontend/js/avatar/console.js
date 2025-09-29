
function displayStatus(status) {
    const grid = document.getElementById('statusGrid');
    if (!grid) return;

    if (!status) {
        grid.innerHTML = '<div class="status-item status-error">No status data available</div>';
        return;
    }

    const items = [
        { label: 'Timestamp', value: status.timestamp || 'Unknown', status: 'ok' },
        { label: 'Device', value: status.device || 'Unknown', status: 'ok' },
        { label: 'TTS Ready', value: status.tts_ready ? 'Yes' : 'No', status: status.tts_ready ? 'ok' : 'error' },
        { label: 'SadTalker Installed', value: status.sadtalker_installed ? 'Yes' : 'No', status: status.sadtalker_installed ? 'ok' : 'warning' },
        { label: 'Streaming Available', value: status.streaming_available ? 'Yes' : 'No', status: status.streaming_available ? 'ok' : 'warning' },
        { label: 'Default Codec', value: status.default_codec || 'Unknown', status: 'ok' },
        { label: 'GPU Available', value: status.gpu_available ? 'Yes' : 'No', status: status.gpu_available ? 'ok' : 'warning' },
        { label: 'Memory Usage', value: status.memory_usage || 'Unknown', status: 'ok' }
    ];

    grid.innerHTML = items.map(item => `
                <div class="status-item status-${item.status}">
                    <strong>${item.label}:</strong><br>
                    ${item.value}
                </div>
            `).join('');
}

// Drag and Drop for Image Upload
function setupDragAndDrop() {
    const uploadZone = document.querySelector('.image-upload-zone');

    if (uploadZone) {
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('imageFile');
                if (fileInput) {
                    fileInput.files = files;
                    handleImageUpload({ target: fileInput });
                }
            }
        });
    }
}

// Initialize
function initializeDebugConsole() {
    debugLog('Complete Avatar Debug Console initializing');

    // Set default TTS engine
    updateTTSOptions();

    // Initialize range displays
    updateRangeDisplay('speed', document.getElementById('ttsSpeed')?.value || '160');
    updateRangeDisplay('pitch', document.getElementById('ttsPitch')?.value || '0');
    updateRangeDisplay('confidence', document.getElementById('faceConfidence')?.value || '0.5');
    updateRangeDisplay('expression', document.getElementById('expressionScale')?.value || '1.0');

    // Setup drag and drop
    setupDragAndDrop();

    // Load initial data
    getServerInfo();
    getStatus();
    loadAvailablePeers();

    debugLog('Initialization complete - ALL options loaded');
    showNotification('Avatar Debug Console initialized', 'success');
}


function downloadVideo() {
    const video = document.getElementById('avatarVideo');
    if (video && video.src) {
        const a = document.createElement('a');
        a.href = video.src;
        a.download = `avatar_${new Date().getTime()}.mp4`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showNotification('Video download started', 'success');
    } else {
        showNotification('No video available to download', 'error');
    }
}

async function getStatus() {
    debugLog('Getting system status');

    try {
        const response = await fetch('/web/api/avatar_dual.php?action=status');
        const result = await response.json();

        debugLog('System status received', result);
        displayStatus(result.data || result);

    } catch (error) {
        debugLog('Status retrieval error', { error: error.message });
        showNotification('Failed to get system status', 'error');
    }
}

async function getDetailedStatus() {
    debugLog('Getting detailed system status');
    showNotification('Getting detailed status...', 'info');

    try {
        await getStatus();
        // Additional detailed status calls could be made here
        showNotification('Detailed status retrieved', 'success');
    } catch (error) {
        showNotification('Failed to get detailed status', 'error');
    }
}

async function exportStatus() {
    debugLog('Exporting system status');

    try {
        const response = await fetch('/web/api/avatar_dual.php?action=status');
        const result = await response.json();

        const statusData = JSON.stringify(result, null, 2);
        const blob = new Blob([statusData], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `avatar_status_${new Date().toISOString()}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showNotification('Status exported successfully', 'success');
    } catch (error) {
        showNotification('Failed to export status', 'error');
    }
}



function shareVideo() {
    const video = document.getElementById('avatarVideo');
    if (video && video.src) {
        if (navigator.share) {
            navigator.share({
                title: 'Generated Avatar Video',
                text: 'Check out this AI-generated avatar!',
                url: video.src
            }).then(() => {
                showNotification('Video shared successfully', 'success');
            }).catch(() => {
                showNotification('Sharing failed', 'error');
            });
        } else {
            // Fallback: copy URL to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(video.src).then(() => {
                    showNotification('Video URL copied to clipboard', 'success');
                }).catch(() => {
                    showNotification('Could not copy URL', 'error');
                });
            } else {
                showNotification('Sharing not supported', 'error');
            }
        }
    } else {
        showNotification('No video available to share', 'error');
    }
}

// API Functions
async function getEngines() {
    debugLog('Getting TTS engines and voices');

    try {
        const response = await fetch('/web/api/avatar_dual.php?action=engines');
        const result = await response.json();

        if (result.engines) {
            debugLog('TTS engines received', result.engines);
            displayEngineInfo(result.engines);
        } else {
            debugLog('No engine data received');
            showNotification('No engine data received', 'warning');
        }

    } catch (error) {
        debugLog('Engine retrieval error', { error: error.message });
        showNotification('Failed to get TTS engines: ' + error.message, 'error');
    }
}

function displayEngineInfo(engines) {
    let info = "Available TTS Engines:\n\n";
    Object.entries(engines).forEach(([key, engine]) => {
        info += `${engine.name}:\n`;
        info += `  Voices: ${engine.voices ? engine.voices.length : 'Unknown'}\n`;
        info += `  Speed Range: ${engine.speed_range ? engine.speed_range[0] + '-' + engine.speed_range[1] : 'Unknown'}\n`;
        info += `  Pitch Range: ${engine.pitch_range ? engine.pitch_range[0] + '-' + engine.pitch_range[1] : 'Unknown'}\n\n`;
    });

    alert(info);
}

async function testMP4() {
    debugLog('Testing MP4 pipeline');

    const video = document.getElementById('avatarVideo');
    const result = document.getElementById('result');
    const error = document.getElementById('error');

    try {
        const response = await fetch('/test-mp4');

        if (response.ok) {
            const blob = await response.blob();
            const videoUrl = URL.createObjectURL(blob);

            if (video) video.src = videoUrl;
            if (result) result.style.display = 'block';
            if (error) error.style.display = 'none';

            debugLog('Test MP4 loaded successfully', {
                size: blob.size,
                type: blob.type
            });

            showNotification('Test MP4 loaded successfully', 'success');

        } else {
            throw new Error(`Test MP4 failed: ${response.status}`);
        }

    } catch (err) {
        debugLog('Test MP4 failed', { error: err.message });
        if (error) {
            error.textContent = 'Test MP4 Error: ' + err.message;
            error.style.display = 'block';
        }
        showNotification('Test MP4 failed', 'error');
    }
}