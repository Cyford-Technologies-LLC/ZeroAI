async function handleStreamingResponse(response, video, videoInfo, payload, duration) {
    const reader = response.body.getReader();
    let buffer = new Uint8Array();
    let metadataChunks = []; // Only for metadata tracking

    // Create the stream processor
    const avatarStreamProcessor = new AvatarStreamProcessor();

    // Make it globally accessible for debugging
    window.avatarStreamProcessor = avatarStreamProcessor;

    debugLog('Starting enhanced streaming response handler');

    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            // Append to buffer
            const newBuffer = new Uint8Array(buffer.length + value.length);
            newBuffer.set(buffer);
            newBuffer.set(value, buffer.length);
            buffer = newBuffer;

            // Look for multipart boundaries
            const bufferString = new TextDecoder('latin1').decode(buffer);
            const boundaryPattern = /--frame\r?\n/g;
            let lastBoundaryEnd = 0;
            let match;

            while ((match = boundaryPattern.exec(bufferString)) !== null) {
                const frameStart = lastBoundaryEnd;
                const frameEnd = match.index;

                if (frameStart < frameEnd) {
                    const frameData = buffer.slice(frameStart, frameEnd);

                    // Process frame IMMEDIATELY
                    const metadata = await processStreamFrame(frameData, avatarStreamProcessor);
                    if (metadata) {
                        metadataChunks.push(metadata); // Keep metadata only
                    }
                }
                lastBoundaryEnd = boundaryPattern.lastIndex;
            }

            // Keep remaining data in buffer
            if (lastBoundaryEnd > 0) {
                buffer = buffer.slice(lastBoundaryEnd);
            }
        }

        // Signal completion
        avatarStreamProcessor.onStreamComplete();

        debugLog('Stream processing complete', {
            metadataChunks: metadataChunks.length,
            processedVideoChunks: avatarStreamProcessor.chunks ? avatarStreamProcessor.chunks.length : 0
        });

        // Update video info
        if (videoInfo) {
            videoInfo.innerHTML = `
            Streaming Complete!

                Mode: Streaming (${payload.stream_mode})

            Engine: ${payload.tts_engine}

            Voice: ${payload.tts_voice}

            Metadata Chunks: ${metadataChunks.length}

            Video Chunks Processed: ${avatarStreamProcessor.chunks ? avatarStreamProcessor.chunks.length : 0}

            Stream Time: ${(duration / 1000).toFixed(1)}s

            Parameters: ${Object.keys(payload).length} options used
            `;
        }

    } catch (error) {
        debugLog('Streaming error', { error: error.message, stack: error.stack });
        if (videoInfo) {
            videoInfo.innerHTML = `Streaming Error:
                ${error.message}`;
        }
        throw error;
    }
}

// Process individual frames - sends to processor IMMEDIATELY
async function processStreamFrame(frameData, streamProcessor) {
    try {
        const frameString = new TextDecoder('utf-8').decode(frameData);

        // Parse headers
        const headerEnd = frameString.indexOf('\r\n\r\n');
        if (headerEnd === -1) return null;

        const headers = frameString.substring(0, headerEnd);
        const contentStart = headerEnd + 4;

        // Get content type
        const contentTypeMatch = headers.match(/Content-Type:\s*([^\r\n]+)/i);
        const contentType = contentTypeMatch ? contentTypeMatch[1].trim() : 'unknown';

        debugLog('Processing frame', {
            contentType: contentType,
            contentLength: frameData.length - contentStart
        });

        if (contentType.includes('json')) {
            // Parse JSON metadata
            const jsonData = frameString.substring(contentStart).trim();
            const jsonEndIndex = jsonData.lastIndexOf('}');
            const cleanJson = jsonEndIndex > 0 ? jsonData.substring(0, jsonEndIndex + 1) : jsonData;

            try {
                const data = JSON.parse(cleanJson);

                // Process video chunk IMMEDIATELY if available
                if (data.ready && data.video_data) {
                    debugLog('🎬 Processing video chunk for immediate playback', {
                        chunkId: data.chunk_id,
                        dataLength: data.video_data.length,
                        duration: data.duration,
                        timestamp: new Date().toISOString()
                    });

                    // Send to processor IMMEDIATELY - don't accumulate!
                    await streamProcessor.processChunk({
                        id: data.chunk_id,
                        data: data.video_data,
                        duration: data.duration
                    });

                    // Update progress
                    if (data.chunk_id !== undefined && data.total_chunks) {
                        updateProgress(
                            70 + (data.chunk_id / data.total_chunks) * 20,
                            `Playing chunk ${data.chunk_id + 1}/${data.total_chunks}`
                    );
                    }
                }

                return data; // Return metadata only

            } catch (error) {
                debugLog('JSON parse error', { error: error.message, data: cleanJson.substring(0, 100) });
            }
        }

        return null;
    } catch (error) {
        debugLog('Frame processing error', { error: error.message });
        return null;
    }
}

async function processStreamFrame(frameData, streamProcessor) {
    try {
        const frameString = new TextDecoder('utf-8').decode(frameData);
        console.log('[DEBUG] Raw frame data preview:', frameString.substring(0, 200));

        // Parse headers
        const headerEnd = frameString.indexOf('\r\n\r\n');
        if (headerEnd === -1) {
            console.log('[DEBUG] No header end found');
            return null;
        }

        const headers = frameString.substring(0, headerEnd);
        const contentStart = headerEnd + 4;

        // Get content type
        const contentTypeMatch = headers.match(/Content-Type:\s*([^\r\n]+)/i);
        const contentType = contentTypeMatch ? contentTypeMatch[1].trim() : 'unknown';

        console.log('[DEBUG] Frame content type:', contentType);
        console.log('[DEBUG] Content preview:', frameString.substring(contentStart, contentStart + 100));

        if (contentType.includes('json')) {
            // Parse JSON metadata
            const jsonData = frameString.substring(contentStart).trim();
            const jsonEndIndex = jsonData.lastIndexOf('}');
            const cleanJson = jsonEndIndex > 0 ? jsonData.substring(0, jsonEndIndex + 1) : jsonData;

            console.log('[DEBUG] Attempting to parse JSON:', cleanJson);

            try {
                const data = JSON.parse(cleanJson);
                console.log('[DEBUG] Parsed JSON data:', data);

                // Process video chunk IMMEDIATELY if available
                if (data.ready && data.video_data) {
                    console.log('[DEBUG] 🎬 FOUND VIDEO DATA! Chunk:', data.chunk_id, 'Length:', data.video_data.length);

                    // Send to processor IMMEDIATELY
                    await streamProcessor.processChunk({
                        id: data.chunk_id,
                        data: data.video_data,
                        duration: data.duration
                    });
                } else {
                    console.log('[DEBUG] No video data in chunk. Ready:', data.ready, 'Has video_data:', !!data.video_data);
                }

                return data;

            } catch (error) {
                console.error('[DEBUG] JSON parse error:', error.message);
                console.log('[DEBUG] Failed JSON:', cleanJson);
            }
        }

        return null;
    } catch (error) {
        console.error('[DEBUG] Frame processing error:', error);
        return null;
    }
}


// Alternative processFrame for backwards compatibility
async function processFrame(frameData, chunks, currentChunk, videoFrames) {
    try {
        const frameString = new TextDecoder('utf-8').decode(frameData);
        const headerEnd = frameString.indexOf('\r\n\r\n');

        if (headerEnd === -1) {
            debugLog('No header end found in frame');
            return currentChunk;
        }

        const headers = frameString.substring(0, headerEnd);
        const contentStart = headerEnd + 4;
        const contentTypeMatch = headers.match(/Content-Type:\s*([^\r\n]+)/i);
        const contentType = contentTypeMatch ? contentTypeMatch[1].trim() : 'unknown';

        if (contentType.includes('json')) {
            const jsonData = frameString.substring(contentStart).trim();
            const jsonEndIndex = jsonData.lastIndexOf('}');
            const cleanJsonData = jsonEndIndex > 0 ? jsonData.substring(0, jsonEndIndex + 1) : jsonData;

            try {
                const data = JSON.parse(cleanJsonData);
                debugLog('Chunk metadata received', data);

                // Store metadata only
                if (chunks && Array.isArray(chunks)) {
                    chunks.push(data);
                }
                currentChunk = data;

            } catch (parseError) {
                debugLog('JSON parse error in processFrame', {
                    error: parseError.message
                });
            }
        }

        return currentChunk;
    } catch (error) {
        debugLog('processFrame error', { error: error.message });
        return currentChunk;
    }
}

// Debug helper
function debugLog(message, data = null) {
    if (window.DEBUG_AVATAR || localStorage.getItem('debug_avatar') === 'true') {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] [streaming.js] ${message}`, data || '');
    }
}

// Progress update helper
function updateProgress(percent, message) {
    const progressBar = document.querySelector('.avatar-progress-bar');
    const progressText = document.querySelector('.avatar-progress-text');

    if (progressBar) {
        progressBar.style.width = percent + '%';
    }
    if (progressText) {
        progressText.textContent = message;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        handleStreamingResponse,
        processStreamFrame,
        processFrame
    };
}

console.log('[streaming.js] Fixed version loaded - immediate chunk playback enabled');