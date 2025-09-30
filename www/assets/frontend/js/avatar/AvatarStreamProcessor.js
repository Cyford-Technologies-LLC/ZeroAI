

// REPLACE your existing AvatarStreamProcessor class with this one
// This version plays chunks IMMEDIATELY as they arrive

class AvatarStreamProcessor {
    constructor() {
        this.videoElement = null;
        this.mediaSource = null;
        this.sourceBuffer = null;
        this.pendingChunks = [];
        this.processedChunks = new Set();
        this.isProcessing = false;
        this.streamStarted = false;
        this.chunkCount = 0;

        console.log('[StreamProcessor] Initialized for IMMEDIATE playback');
    }

    // Main entry point - called for each chunk
    async processChunk(chunkData) {
        console.log(`[StreamProcessor] Chunk ${chunkData.id} received` );

        // PLAY IMMEDIATELY - Don't wait!
        if (chunkData.id === 0 || !this.videoElement) {
            this.videoElement = document.getElementById('avatarVideo');
            document.getElementById('result').style.display = 'block';
        }

        // For first chunk or when switching chunks
        if (this.chunkCount === 0 || this.shouldSwitchChunk()) {
            console.log( "🎬 Playing chunk ${chunkData.id} NOW!" );
            this.videoElement.src = chunkData.data;
            this.videoElement.play().catch(e => {
                console.log('Click to play');
            });
        }

        // Track this chunk
        this.processedChunks.add(chunkData.id);
        this.chunkCount++;

        // Store for later combining if needed
        this.pendingChunks.push(chunkData);
    }

    shouldSwitchChunk() {
        // Switch to next chunk when current is almost done
        if (this.videoElement && this.videoElement.duration) {
            const timeLeft = this.videoElement.duration - this.videoElement.currentTime;
            return timeLeft < 0.5; // Switch 0.5 seconds before end
        }
        return true;
    }

    async startStreaming() {
        console.log('[StreamProcessor] Starting stream with first chunk');
        this.streamStarted = true;

        // Try MediaSource first
        if (window.MediaSource && MediaSource.isTypeSupported('video/mp4; codecs="avc1.42E01E"')) {
            await this.startMediaSourceStream();
        } else {
            // Fallback to blob URL approach
            console.log('[StreamProcessor] Using fallback blob streaming');
            this.startBlobStream();
        }
    }

    async startMediaSourceStream() {
        try {
            this.mediaSource = new MediaSource();
            this.videoElement.src = URL.createObjectURL(this.mediaSource);

            await new Promise((resolve, reject) => {
                this.mediaSource.addEventListener('sourceopen', resolve, { once: true });
                setTimeout(() => reject(new Error('MediaSource timeout')), 3000);
            });

            // Try different codecs
            const codecs = [
                'video/mp4; codecs="avc1.42E01E"',
                'video/mp4; codecs="avc1.640028"',
                'video/mp4; codecs="avc1.4D401E"'
            ];

            let codecFound = false;
            for (const codec of codecs) {
                if (MediaSource.isTypeSupported(codec)) {
                    this.sourceBuffer = this.mediaSource.addSourceBuffer(codec);
                    codecFound = true;
                    console.log(`[StreamProcessor] Using codec: ${codec}`);
                    break;
                }
            }

            if (!codecFound) {
                throw new Error('No supported codec found');
            }

            // Set up event handlers
            this.sourceBuffer.addEventListener('updateend', () => {
                this.isProcessing = false;
                this.processNextChunk();
            });

            // Start processing
            this.processNextChunk();

        } catch (error) {
            console.error('[StreamProcessor] MediaSource failed:', error);
            this.startBlobStream();
        }
    }

    startBlobStream() {
        // Fallback: Update video source with accumulated chunks
        this.updateBlobVideo();
    }

    async processNextChunk() {
        if (this.isProcessing || this.pendingChunks.length === 0) return;

        const chunk = this.pendingChunks.shift();
        this.isProcessing = true;

        console.log(`[StreamProcessor] Processing chunk ${chunk.id} for playback`);

        if (this.sourceBuffer && !this.sourceBuffer.updating) {
            // MediaSource mode
            try {
                const arrayBuffer = await this.base64ToArrayBuffer(chunk.data);
                this.sourceBuffer.appendBuffer(arrayBuffer);

                // Start playback after first chunk
                if (this.chunkCount === 1 && this.videoElement.paused) {
                    this.videoElement.play().catch(e => {
                        console.log('[StreamProcessor] Autoplay blocked, click video to play');
                    });
                }
            } catch (error) {
                console.error('[StreamProcessor] Buffer append failed:', error);
                this.isProcessing = false;
            }
        } else {
            // Blob mode
            this.isProcessing = false;
            this.updateBlobVideo();
        }
    }

    async updateBlobVideo() {
        if (this.processedChunks.size === 0) return;

        console.log(`[StreamProcessor] Updating video with ${this.processedChunks.size} chunks`);

        try {
            // Get all processed chunks in order
            const allChunks = Array.from(this.processedChunks)
                .sort((a, b) => a - b)
                .map(id => this.pendingChunks.find(c => c.id === id) ||
                    { id, data: this.getStoredChunkData(id) })
                .filter(c => c && c.data);

            if (allChunks.length === 0) return;

            // Convert all chunks to blobs
            const blobs = await Promise.all(
                allChunks.map(async chunk => {
                    const arrayBuffer = await this.base64ToArrayBuffer(chunk.data);
                    return new Blob([arrayBuffer], { type: 'video/mp4' });
                })
            );

            // Combine and set as source
            const combinedBlob = new Blob(blobs, { type: 'video/mp4' });
            const currentTime = this.videoElement.currentTime;

            this.videoElement.src = URL.createObjectURL(combinedBlob);

            // Restore playback position and play
            if (currentTime > 0) {
                this.videoElement.currentTime = currentTime;
            }

            if (this.videoElement.paused) {
                this.videoElement.play().catch(e => {
                    console.log('[StreamProcessor] Click video to play');
                });
            }

        } catch (error) {
            console.error('[StreamProcessor] Blob update failed:', error);
        }
    }

    async base64ToArrayBuffer(base64Data) {
        try {
            const base64String = base64Data.includes(',')
                ? base64Data.split(',')[1]
                : base64Data;

            const binaryString = atob(base64String);
            const bytes = new Uint8Array(binaryString.length);

            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            return bytes.buffer;
        } catch (error) {
            console.error('[StreamProcessor] Base64 decode error:', error);
            return null;
        }
    }

    getStoredChunkData(id) {
        // This is a placeholder - implement based on how you store chunks
        return null;
    }

    onStreamComplete() {
        console.log(`[StreamProcessor] Stream complete with ${this.chunkCount} chunks`);

        if (this.mediaSource && this.mediaSource.readyState === 'open') {
            // Wait for any pending chunks to finish
            const finishStream = () => {
                if (this.pendingChunks.length === 0 && !this.isProcessing) {
                    try {
                        this.mediaSource.endOfStream();
                    } catch (e) {}
                } else {
                    setTimeout(finishStream, 100);
                }
            };
            finishStream();
        }
    }

    // DO NOT implement updateVideoPlayback that combines all chunks!
    // Each chunk should be played as soon as it arrives
}

// REPLACE the chunk processing section in your handleStreamingResponse function:
// Look for where you have this pattern and replace it:
/*
OLD CODE (WRONG - waits for all chunks):
if (data.ready && data.video_data) {
    videoChunks.push(chunkData);  // DON'T accumulate
    ...
}
// Then later after stream completes:
playVideoChunksSequentially(video, videoChunks);  // DON'T wait
*/

// NEW CODE (CORRECT - plays immediately):
function handleChunkData(data, avatarStreamProcessor) {
    if (data.ready && data.video_data) {
        // Process chunk IMMEDIATELY for streaming
        avatarStreamProcessor.processChunk({
            id: data.chunk_id || 0,
            data: data.video_data,
            duration: data.duration || 2
        });

        debugLog('Video chunk processed for immediate playback', {
            chunkId: data.chunk_id,
            timestamp: new Date().toISOString()
        });
    }
}

// ALSO IMPORTANT: Remove or comment out these problem areas:
// 1. Remove the setTimeout that waits 2000ms before playing
// 2. Remove playVideoChunksSequentially function call
// 3. Remove any code that waits for videoChunks array to be full

console.log('=== STREAMING FIX LOADED ===');
console.log('This fix makes chunks play IMMEDIATELY as they arrive');
console.log('No more waiting for all chunks to complete!');


// class AvatarStreamProcessor {
//     constructor() {
//         this.video = null;
//         this.mediaSource = null;
//         this.sourceBuffer = null;
//         this.chunks = [];
//         this.pendingChunks = [];
//         this.isInitialized = false;
//         this.isAppending = false;
//         this.streamComplete = false;
//
//         // Try different codec configurations
//         this.codecs = [
//             'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
//             'video/mp4; codecs="avc1.640028"',
//             'video/mp4; codecs="avc1.42E01E"'
//         ];
//         this.mimeType = null;
//
//         console.log('[AvatarStreamProcessor] Initialized');
//     }
//
//     async initialize(videoElement) {
//         if (this.isInitialized) return;
//
//         this.video = videoElement;
//
//         try {
//             // Check MediaSource support
//             if (!window.MediaSource) {
//                 throw new Error('MediaSource API not supported');
//             }
//
//             // Find supported codec
//             for (const codec of this.codecs) {
//                 if (MediaSource.isTypeSupported(codec)) {
//                     this.mimeType = codec;
//                     console.log('[AvatarStreamProcessor] Using codec:', codec);
//                     break;
//                 }
//             }
//
//             if (!this.mimeType) {
//                 throw new Error('No supported codec found');
//             }
//
//             // Create MediaSource
//             this.mediaSource = new MediaSource();
//             this.video.src = URL.createObjectURL(this.mediaSource);
//
//             // Wait for source open
//             await new Promise((resolve, reject) => {
//                 this.mediaSource.addEventListener('sourceopen', resolve, { once: true });
//                 this.mediaSource.addEventListener('error', reject, { once: true });
//                 setTimeout(() => reject(new Error('MediaSource timeout')), 5000);
//             });
//
//             // Create source buffer
//             this.sourceBuffer = this.mediaSource.addSourceBuffer(this.mimeType);
//
//             // Handle source buffer events
//             this.sourceBuffer.addEventListener('updateend', () => {
//                 this.isAppending = false;
//                 this.processPendingChunks();
//             });
//
//             this.sourceBuffer.addEventListener('error', (e) => {
//                 console.error('[AvatarStreamProcessor] SourceBuffer error:', e);
//             });
//
//             this.isInitialized = true;
//             console.log('[AvatarStreamProcessor] Initialization complete');
//
//         } catch (error) {
//             console.error('[AvatarStreamProcessor] Init failed:', error);
//             // Fall back to blob mode
//             this.useFallbackMode = true;
//         }
//     }
//
//     async processChunk(chunkData) {
//         console.log('[AvatarStreamProcessor] Processing chunk:', {
//             id: chunkData.id,
//             hasData: !!chunkData.data,
//             duration: chunkData.duration
//         });
//
//         // Store chunk info
//         this.chunks.push({
//             id: chunkData.id || this.chunks.length,
//             data: chunkData.data,
//             duration: chunkData.duration || 2,
//             timestamp: Date.now()
//         });
//
//         // Get video element if not initialized
//         if (!this.video) {
//             this.video = document.getElementById('avatarVideo');
//             if (!this.video) {
//                 console.error('[AvatarStreamProcessor] Video element not found');
//                 return;
//             }
//         }
//
//         // Initialize on first chunk
//         if (!this.isInitialized && !this.useFallbackMode) {
//             await this.initialize(this.video);
//         }
//
//         // Use fallback mode if needed
//         if (this.useFallbackMode) {
//             this.processFallbackChunk(chunkData);
//             return;
//         }
//
//         // Convert base64 to ArrayBuffer
//         const arrayBuffer = await this.base64ToArrayBuffer(chunkData.data);
//         if (!arrayBuffer) {
//             console.error('[AvatarStreamProcessor] Failed to decode chunk');
//             return;
//         }
//
//         // Queue for appending
//         this.pendingChunks.push({
//             id: chunkData.id,
//             buffer: arrayBuffer
//         });
//
//         // Process queue
//         if (!this.isAppending) {
//             this.processPendingChunks();
//         }
//
//         // Start playback after first chunk is ready
//         if (this.chunks.length === 1 && this.video.paused) {
//             setTimeout(() => this.startPlayback(), 100);
//         }
//     }
//
//     async processPendingChunks() {
//         if (this.isAppending || this.pendingChunks.length === 0) return;
//         if (!this.sourceBuffer || this.sourceBuffer.updating) return;
//
//         const chunk = this.pendingChunks.shift();
//         this.isAppending = true;
//
//         try {
//             this.sourceBuffer.appendBuffer(chunk.buffer);
//             console.log('[AvatarStreamProcessor] Appended chunk', chunk.id);
//
//         } catch (error) {
//             console.error('[AvatarStreamProcessor] Append error:', error);
//             this.isAppending = false;
//
//             if (error.name === 'QuotaExceededError') {
//                 this.removeOldBuffer();
//             }
//         }
//     }
//
//     removeOldBuffer() {
//         if (!this.sourceBuffer || this.sourceBuffer.updating) return;
//
//         try {
//             const buffered = this.sourceBuffer.buffered;
//             if (buffered.length > 0) {
//                 const currentTime = this.video.currentTime;
//                 const removeEnd = Math.max(buffered.start(0), currentTime - 5);
//
//                 if (removeEnd > buffered.start(0)) {
//                     this.sourceBuffer.remove(buffered.start(0), removeEnd);
//                     console.log('[AvatarStreamProcessor] Removed old buffer');
//                 }
//             }
//         } catch (error) {
//             console.error('[AvatarStreamProcessor] Buffer removal error:', error);
//         }
//     }
//
//     async base64ToArrayBuffer(base64Data) {
//         try {
//             // Handle data URL format
//             const base64String = base64Data.includes(',')
//                 ? base64Data.split(',')[1]
//                 : base64Data;
//
//             // Decode base64
//             const binaryString = atob(base64String);
//             const bytes = new Uint8Array(binaryString.length);
//
//             for (let i = 0; i < binaryString.length; i++) {
//                 bytes[i] = binaryString.charCodeAt(i);
//             }
//
//             return bytes.buffer;
//
//         } catch (error) {
//             console.error('[AvatarStreamProcessor] Base64 decode error:', error);
//             return null;
//         }
//     }
//
//     startPlayback() {
//         if (!this.video || !this.video.paused) return;
//
//         this.video.play()
//             .then(() => {
//                 console.log('[AvatarStreamProcessor] Playback started');
//             })
//             .catch(error => {
//                 console.log('[AvatarStreamProcessor] Playback blocked:', error.message);
//                 // Add click handler for user interaction
//                 this.video.addEventListener('click', () => {
//                     this.video.play();
//                 }, { once: true });
//             });
//     }
//
//     // Fallback mode for browsers without MediaSource
//     processFallbackChunk(chunkData) {
//         console.log('[AvatarStreamProcessor] Using fallback mode');
//
//         // Store chunk
//         if (!this.fallbackChunks) {
//             this.fallbackChunks = [];
//         }
//         this.fallbackChunks.push(chunkData);
//
//         // Update video every few chunks or when complete
//         if (this.fallbackChunks.length % 3 === 0 || this.streamComplete) {
//             this.updateFallbackVideo();
//         }
//     }
//
//     async updateFallbackVideo() {
//         if (!this.video || !this.fallbackChunks || this.fallbackChunks.length === 0) return;
//
//         try {
//             // Convert all chunks to blobs
//             const buffers = await Promise.all(
//                 this.fallbackChunks.map(chunk => this.base64ToArrayBuffer(chunk.data))
//             );
//
//             const validBuffers = buffers.filter(b => b !== null);
//             const blob = new Blob(validBuffers, { type: 'video/mp4' });
//
//             // Update video source
//             const currentTime = this.video.currentTime;
//             this.video.src = URL.createObjectURL(blob);
//
//             // Try to maintain playback position
//             this.video.currentTime = currentTime;
//
//             if (this.video.paused) {
//                 this.video.play().catch(e => console.log('[AvatarStreamProcessor] Play prevented'));
//             }
//
//         } catch (error) {
//             console.error('[AvatarStreamProcessor] Fallback update error:', error);
//         }
//     }
//
//     onStreamComplete() {
//         this.streamComplete = true;
//         console.log('[AvatarStreamProcessor] Stream complete');
//
//         if (this.mediaSource && this.mediaSource.readyState === 'open') {
//             // Wait for pending chunks
//             const checkComplete = () => {
//                 if (this.pendingChunks.length === 0 && !this.isAppending) {
//                     try {
//                         this.mediaSource.endOfStream();
//                         console.log('[AvatarStreamProcessor] Stream ended');
//                     } catch (error) {
//                         console.error('[AvatarStreamProcessor] End stream error:', error);
//                     }
//                 } else {
//                     setTimeout(checkComplete, 100);
//                 }
//             };
//             checkComplete();
//         } else if (this.useFallbackMode) {
//             this.updateFallbackVideo();
//         }
//     }
//
//     // Clean up resources
//     clear() {
//         if (this.mediaSource) {
//             try {
//                 if (this.sourceBuffer) {
//                     this.mediaSource.removeSourceBuffer(this.sourceBuffer);
//                 }
//                 if (this.mediaSource.readyState === 'open') {
//                     this.mediaSource.endOfStream();
//                 }
//             } catch (e) {}
//         }
//
//         this.chunks = [];
//         this.pendingChunks = [];
//         this.fallbackChunks = [];
//         this.isInitialized = false;
//         this.isAppending = false;
//         this.streamComplete = false;
//         this.mediaSource = null;
//         this.sourceBuffer = null;
//
//         if (this.video) {
//             this.video.src = '';
//         }
//     }
// }