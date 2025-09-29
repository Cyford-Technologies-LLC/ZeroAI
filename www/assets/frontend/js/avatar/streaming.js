// Fixed streaming.js - This version doesn't accumulate chunks
async function handleStreamingResponse(response, video, videoInfo, payload, duration) {
    const reader = response.body.getReader();
    let buffer = new Uint8Array();
    let chunks = [];
    // REMOVED: let videoChunks = []; // DON'T COLLECT CHUNKS!

    // Create the stream processor
    const avatarStreamProcessor = new AvatarStreamProcessor();

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
                    const result = await processStreamFrame(frameData, avatarStreamProcessor);
                    if (result) {
                        chunks.push(result); // Keep metadata only
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
            chunks: chunks.length,
            processedChunks: avatarStreamProcessor.chunks.length
        });

        // Update video info
        if (videoInfo) {
            videoInfo.innerHTML = `
                <strong>Streaming Complete!</strong><br>
                Mode: Streaming (${payload.stream_mode})<br>
                Engine: ${payload.tts_engine}<br>
                Voice: ${payload.tts_voice}<br>
                Metadata Chunks: ${chunks.length}<br>
                Video Chunks Processed: ${avatarStreamProcessor.chunks.length}<br>
                Stream Time: ${(duration / 1000).toFixed(1)}s<br>
                Parameters: ${Object.keys(payload).length} options used
            `;
        }

    } catch (error) {
        debugLog('Streaming error', { error: error.message });
        throw error;
    }
}

// Process individual frames - sends to processor IMMEDIATELY
async function processStreamFrame(frameData, streamProcessor) {
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
                debugLog('Processing video chunk for immediate playback', {
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
            }

            return data; // Return metadata only

        } catch (error) {
            debugLog('JSON parse error', { error: error.message });
        }
    }

    return null;
}

// DEPRECATED - Remove or comment out this function
// It waits for all chunks which defeats streaming
/*
async function playVideoChunksSequentially(video, videoChunks) {
    console.warn('playVideoChunksSequentially is DEPRECATED - chunks should play immediately');
    // Function removed - chunks play as they arrive
}
*/

// Alternative if you need backwards compatibility
async function processFrame(frameData, chunks, currentChunk, videoFrames) {
    // This function should NOT accumulate videoChunks
    // Only process metadata and let AvatarStreamProcessor handle video

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

    debugLog('Frame processed', {
        contentType: contentType,
        contentLength: frameData.length - contentStart
    });

    if (contentType.includes('json')) {
        const jsonData = frameString.substring(contentStart).trim();
        const jsonEndIndex = jsonData.lastIndexOf('}');
        const cleanJsonData = jsonEndIndex > 0 ? jsonData.substring(0, jsonEndIndex + 1) : jsonData;

        try {
            const data = JSON.parse(cleanJsonData);
            debugLog('Chunk metadata received', data);

            // Store metadata only
            chunks.push(data);
            currentChunk = data;

            // Update progress
            if (data.chunk_id !== undefined && data.total_chunks) {
                updateProgress(
                    70 + (data.chunk_id / data.total_chunks) * 20,
                    `Processing chunk ${data.chunk_id + 1}/${data.total_chunks}: "${data.sentence || 'processing...'}"`
                );
            }

        } catch (parseError) {
            debugLog('JSON parse error', {
                error: parseError.message,
                data: cleanJsonData
            });
        }
    } else if (contentType.includes('video/mp4') || contentType.includes('image/jpeg')) {
        // For backwards compatibility with JPEG frames
        const frameDataSlice = frameData.slice(contentStart);
        if (frameDataSlice.length > 1000) {
            videoFrames.push(frameDataSlice);
            debugLog('Frame data stored for fallback', {
                type: contentType,
                size: frameDataSlice.length
            });
        }
    }

    return currentChunk;
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        handleStreamingResponse,
        processStreamFrame
    };
}

console.log('[streaming.js] Fixed version loaded - chunks play immediately');