// ============================================
// FILE: js/config.js (Load FIRST)
// ============================================
const CONFIG = {
    API_BASE_URL: '/web/api/',
    DEFAULT_TTS_ENGINE: 'espeak',
    DEFAULT_VOICE: 'en',
    CHUNK_TIMEOUT: 30000
};

const DEBUG_LEVELS = {
    ERROR: 0,
    WARN: 1,
    INFO: 2,
    DEBUG: 3
};

// ============================================
// FILE: js/utils.js (Load SECOND)
// ============================================
function debugLog(message, data = null) {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${message}`, data || '');
}

function showNotification(message, type = 'info') {
    console.log(`[${type.toUpperCase()}] ${message}`);
    // Add your notification UI logic here
}

function updateProgress(percentage, text) {
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    if (progressFill) progressFill.style.width = `${percentage}%`;
    if (progressText) progressText.textContent = text;
}

// ============================================
// FILE: js/streamingPlayer.js (Load THIRD)
// ============================================
class StreamingVideoPlayer {
    constructor(videoElement) {
        this.video = videoElement;
        this.mediaSource = null;
        this.sourceBuffer = null;
        this.chunkQueue = [];
        this.isInitialized = false;
        this.isProcessing = false;

        debugLog('StreamingVideoPlayer created');
    }

    initialize() {
        if (this.isInitialized) return;

        this.mediaSource = new MediaSource();
        this.video.src = URL.createObjectURL(this.mediaSource);

        this.mediaSource.addEventListener('sourceopen', () => {
            try {
                this.sourceBuffer = this.mediaSource.addSourceBuffer('video/mp4; codecs="avc1.42E01E"');
                this.sourceBuffer.addEventListener('updateend', () => {
                    this.processNextChunk();
                });
                this.isInitialized = true;
                debugLog('MediaSource initialized');
                this.processNextChunk();
            } catch (error) {
                debugLog('MediaSource setup error', error);
            }
        });
    }

    addChunk(chunkData) {
        debugLog('Adding chunk', { id: chunkData.id });
        this.chunkQueue.push(chunkData);

        if (!this.isInitialized) {
            this.initialize();
        } else {
            this.processNextChunk();
        }
    }

    async processNextChunk() {
        if (this.isProcessing || this.chunkQueue.length === 0 ||
            !this.sourceBuffer || this.sourceBuffer.updating) {
            return;
        }

        this.isProcessing = true;
        const chunk = this.chunkQueue.shift();

        try {
            let arrayBuffer;

            if (chunk.isBase64 && chunk.data) {
                const base64Data = chunk.data.includes(',') ?
                    chunk.data.split(',')[1] : chunk.data;
                const binaryString = atob(base64Data);
                arrayBuffer = new ArrayBuffer(binaryString.length);
                const uint8Array = new Uint8Array(arrayBuffer);
                for (let i = 0; i < binaryString.length; i++) {
                    uint8Array[i] = binaryString.charCodeAt(i);
                }
            } else if (chunk.url) {
                const response = await fetch(chunk.url);
                arrayBuffer = await response.arrayBuffer();
            }

            if (arrayBuffer && arrayBuffer.byteLength > 0) {
                debugLog(`Appending chunk ${chunk.id}`, { size: arrayBuffer.byteLength });
                this.sourceBuffer.appendBuffer(arrayBuffer);

                if (this.video.paused && this.video.readyState >= 2) {
                    this.video.play().catch(e =>
                        debugLog('Autoplay prevented', e.message)
                    );
                }
            }
        } catch (error) {
            debugLog(`Chunk processing error`, { chunkId: chunk.id, error });
        } finally {
            this.isProcessing = false;
        }
    }

    finalize() {
        if (this.mediaSource && this.mediaSource.readyState === 'open') {
            this.mediaSource.endOfStream();
            debugLog('Stream finalized');
        }
    }

    reset() {
        if (this.mediaSource) {
            URL.revokeObjectURL(this.video.src);
        }
        this.mediaSource = null;
        this.sourceBuffer = null;
        this.chunkQueue = [];
        this.isInitialized = false;
        this.isProcessing = false;
    }
}

// ============================================
// FILE: js/avatarManager.js (Load FOURTH)
// ============================================
class AvatarManager {
    constructor() {
        this.streamingPlayer = null;
        this.currentStream = null;
        debugLog('AvatarManager initialized');
    }

    async generateAvatar(options) {
        debugLog('Starting avatar generation', options);

        const video = document.getElementById('avatarVideo');
        if (!video) {
            throw new Error('Video element not found');
        }

        // Reset previous stream
        if (this.streamingPlayer) {
            this.streamingPlayer.reset();
        }

        this.streamingPlayer = new StreamingVideoPlayer(video);

        try {
            const response = await fetch(CONFIG.API_BASE_URL + 'avatar_dual.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(options)
            });

            if (!response.body) {
                throw new Error('Streaming not supported');
            }

            await this.processStream(response.body.getReader());

        } catch (error) {
            debugLog('Avatar generation error', error);
            throw error;
        }
    }

    async processStream(reader) {
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();

            if (done) {
                debugLog('Stream complete');
                this.streamingPlayer.finalize();
                break;
            }

            buffer += new TextDecoder().decode(value);
            const lines = buffer.split('\n');
            buffer = lines.pop() || ''; // Keep incomplete line in buffer

            for (const line of lines) {
                if (line.trim().startsWith('{')) {
                    try {
                        const data = JSON.parse(line.trim());
                        this.handleChunkData(data);
                    } catch (parseError) {
                        debugLog('JSON parse error', { line, error: parseError });
                    }
                }
            }
        }
    }

    handleChunkData(data) {
        debugLog('Chunk data received', data);

        if (data.ready && (data.video_url || data.video_data)) {
            const chunkData = {
                id: data.chunk_id || Date.now(),
                duration: data.duration || 2,
                sentence: data.sentence || '',
                mode: data.mode
            };

            if (data.video_data) {
                chunkData.data = data.video_data;
                chunkData.isBase64 = true;
            } else if (data.video_url) {
                chunkData.url = data.video_url.startsWith('/') ?
                    window.location.origin + data.video_url : data.video_url;
            }

            // Process chunk immediately
            this.streamingPlayer.addChunk(chunkData);
        }

        if (data.chunk_id !== undefined && data.total_chunks) {
            const progress = 70 + (data.chunk_id / data.total_chunks) * 20;
            updateProgress(progress,
                `Processing chunk ${data.chunk_id + 1}/${data.total_chunks}: "${data.sentence || 'processing...'}"`
            );
        }
    }
}

// ============================================
// FILE: js/main.js (Load LAST)
// ============================================
let avatarManager = null;

// Initialize everything when DOM is ready
function initializeDebugConsole() {
    debugLog('Complete Avatar Debug Console initializing');

    // Initialize the avatar manager
    avatarManager = new AvatarManager();

    // Set up UI event listeners
    setupEventListeners();

    // Load initial data
    getServerInfo();
    getStatus();

    debugLog('Initialization complete');
    showNotification('Avatar Debug Console initialized', 'success');
}

function setupEventListeners() {
    // Add your event listeners here
    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', handleGenerate);
    }
}

async function handleGenerate() {
    if (!avatarManager) {
        showNotification('Avatar manager not initialized', 'error');
        return;
    }

    try {
        const options = gatherFormOptions(); // Your existing function
        await avatarManager.generateAvatar(options);
    } catch (error) {
        debugLog('Generation failed', error);
        showNotification('Avatar generation failed: ' + error.message, 'error');
    }
}

// Safe initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDebugConsole);
} else {
    // DOM already loaded, check if function exists
    if (typeof initializeDebugConsole === 'function') {
        initializeDebugConsole();
    } else {
        setTimeout(initializeDebugConsole, 100);
    }
}