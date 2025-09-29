async function handleStreamingResponse(response, video, videoInfo, payload, duration) {
    const reader = response.body.getReader();
    let buffer = new Uint8Array();
    let chunks = [];
    let videoChunks = [];

    // Create the new stream processor
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

                    // Process frame
                    const result = await processStreamFrame(frameData, avatarStreamProcessor);
                    if (result) {
                        chunks.push(result);
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
            videoChunks: avatarStreamProcessor.chunks.length
        });

        // Update video info
        if (videoInfo) {
            videoInfo.innerHTML = `
                <strong>Streaming Complete!</strong><br>
                Mode: Streaming (${payload.stream_mode})<br>
                Engine: ${payload.tts_engine}<br>
                Voice: ${payload.tts_voice}<br>
                Chunks: ${chunks.length}<br>
                Video Chunks: ${avatarStreamProcessor.chunks.length}<br>
                Stream Time: ${(duration / 1000).toFixed(1)}s<br>
                Parameters: ${Object.keys(payload).length} options used
            `;
        }

    } catch (error) {
        debugLog('Streaming error', { error: error.message });
        throw error;
    }
}

// NEW helper function to process individual frames
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

            // Process video chunk if available
            if (data.ready && data.video_data) {
                debugLog('Processing video chunk', {
                    chunkId: data.chunk_id,
                    dataLength: data.video_data.length,
                    duration: data.duration
                });

                // Process chunk immediately for streaming
                await streamProcessor.processChunk({
                    id: data.chunk_id,
                    data: data.video_data,
                    duration: data.duration
                });
            }

            return data;

        } catch (error) {
            debugLog('JSON parse error', { error: error.message });
        }
    }

    return null;
}


async function processFrame(frameData, chunks, currentChunk, videoFrames, videoChunks) {
    const frameString = new TextDecoder('utf-8').decode(frameData);

    // Parse headers
    const headerEnd = frameString.indexOf('\r\n\r\n');
    if (headerEnd === -1) {
        debugLog('No header end found in frame');
        return;
    }

    const headers = frameString.substring(0, headerEnd);
    const contentStart = headerEnd + 4;

    // Extract content type
    const contentTypeMatch = headers.match(/Content-Type:\s*([^\r\n]+)/i);
    const contentType = contentTypeMatch ? contentTypeMatch[1].trim() : 'unknown';

    debugLog('Frame processed', {
        headersLength: headers.length,
        contentLength: frameData.length - contentStart,
        contentType: contentType,
        headers: headers
    });

    if (contentType === 'application/json' || contentType.includes('json')) {
        // Parse JSON metadata
        const jsonData = frameString.substring(contentStart).trim();

        // Clean JSON data (remove any trailing data)
        const jsonEndIndex = jsonData.lastIndexOf('}');
        const cleanJsonData = jsonEndIndex > 0 ? jsonData.substring(0, jsonEndIndex + 1) : jsonData;

        try {
            const data = JSON.parse(cleanJsonData);
            debugLog('Chunk metadata received', data);

            // Handle video chunk URLs from chunked streaming
            if (data.video_url && data.ready) {
                // Convert relative URL to absolute URL
                const chunkUrl = data.video_url;
                const absoluteUrl = chunkUrl.startsWith('/')
                    ? window.location.origin + chunkUrl
                    : chunkUrl;

                videoChunks.push({
                    id: data.chunk_id || videoChunks.length,
                    url: absoluteUrl,
                    duration: data.duration || 2,
                    sentence: data.sentence || '',
                    mode: data.mode
                });

                debugLog('Video chunk URL received', {
                    chunkId: data.chunk_id,
                    url: absoluteUrl,
                    duration: data.duration
                });
            }

            chunks.push(data);
            currentChunk = data;

            // Update progress if we have chunk info
            if (data.chunk_id !== undefined && data.total_chunks) {
                updateProgress(
                    70 + (data.chunk_id / data.total_chunks) * 20,
                    `Processing chunk ${data.chunk_id + 1}/${data.total_chunks}: "${data.sentence || 'processing...'}"`
                );
            }

        } catch (parseError) {
            debugLog('JSON parse error', {
                error: parseError.message,
                data: cleanJsonData,
                originalData: jsonData
            });
        }
    } else if (contentType.includes('video/mp4') || contentType.includes('application/octet-stream')) {
        // Handle video binary data
        const videoData = frameData.slice(contentStart);
        debugLog('Video content received', {
            contentType: contentType,
            dataSize: videoData.length
        });

        if (videoData.length > 1000) { // Only substantial data
            videoFrames.push(videoData);
        }
    } else if (contentType.includes('image/jpeg') || contentType.includes('jpeg')) {
        // Handle JPEG frame data
        const frameDataSlice = frameData.slice(contentStart);
        if (frameDataSlice.length > 1000) { // Only substantial data
            videoFrames.push(frameDataSlice);

            debugLog('JPEG frame received', {
                chunkId: currentChunk ? currentChunk.id : 'unknown',
                dataSize: frameDataSlice.length
            });
        }
    }

    return currentChunk; // Return updated currentChunk
}


// Function to play video chunks sequentially
async function playVideoChunksSequentially(video, videoChunks) {
    if (!video || videoChunks.length === 0) return;

    // Sort chunks by ID to ensure correct order
    videoChunks.sort((a, b) => a.id - b.id);

    // Hide any existing canvas
    const existingCanvas = video.parentNode.querySelector('.jpeg-animation-canvas');
    if (existingCanvas) {
        existingCanvas.style.display = 'none';
    }

    video.style.display = 'block';

    let currentChunkIndex = 0;
    let totalDuration = 0;

    // Create a media source for streaming
    const mediaSource = new MediaSource();
    video.src = URL.createObjectURL(mediaSource);

    // Track chunk loading state
    const loadedChunks = new Set();

    mediaSource.addEventListener('sourceopen', async () => {
        const sourceBuffer = mediaSource.addSourceBuffer('video/mp4');

        const playNextChunk = async () => {
            if (currentChunkIndex >= videoChunks.length) {
                mediaSource.endOfStream();
                debugLog('All video chunks played');
                showNotification('Video playback complete!', 'success');
                return;
            }

            const chunk = videoChunks[currentChunkIndex];
            debugLog('Processing video chunk', {
                chunkId: chunk.id,
                source: chunk.isBase64 ? 'base64' : 'url',
                duration: chunk.duration
            });

            try {
                let chunkData;

                // Handle base64 data
                if (chunk.isBase64 && chunk.data) {
                    // Remove data URL prefix
                    const base64Data = chunk.data.split(',')[1];
                    chunkData = atob(base64Data);
                }
                // Handle URL-based chunks
                else if (chunk.url) {
                    const response = await fetch(chunk.url);
                    chunkData = await response.arrayBuffer();
                }

                if (chunkData) {
                    // Convert to Uint8Array if needed
                    const uint8Data = new Uint8Array(chunkData);

                    // Append chunk to media source
                    if (!sourceBuffer.updating) {
                        sourceBuffer.appendBuffer(uint8Data);
                        loadedChunks.add(chunk.id);

                        // Auto-play if not already playing
                        if (video.paused) {
                            video.play().catch(e => {
                                debugLog('Autoplay error', { error: e.message });
                            });
                        }
                    }
                }

                totalDuration += chunk.duration || 0;
                currentChunkIndex++;

            } catch (error) {
                debugLog('Chunk processing error', {
                    chunkId: chunk.id,
                    error: error.message
                });
                currentChunkIndex++;
            }
        };

        // Initial chunk loading
        await playNextChunk();

        // Set up event listeners for chunk progression
        sourceBuffer.addEventListener('updateend', async () => {
            await playNextChunk();
        });
    });

    // Debugging and error handling
    video.addEventListener('error', (e) => {
        debugLog('Video playback error', {
            error: video.error,
            currentChunk: currentChunkIndex
        });
    });

    // Optional: Progress tracking
    const progressTracker = setInterval(() => {
        debugLog('Chunk Playback Progress', {
            loadedChunks: Array.from(loadedChunks),
            currentChunk: currentChunkIndex,
            totalChunks: videoChunks.length
        });
    }, 5000);
}