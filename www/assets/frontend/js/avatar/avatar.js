async function generateAvatar() {
    if (generationInProgress) {
        showNotification('Generation already in progress', 'warning');
        return;
    }

    const loading = document.getElementById('loading');
    const result = document.getElementById('result');
    const error = document.getElementById('error');
    const video = document.getElementById('avatarVideo');
    const videoInfo = document.getElementById('videoInfo');

    // Validate image selection
    const imageValidation = validateImageForGeneration();
    if (!imageValidation.valid) {
        if (error) {
            error.textContent = imageValidation.error;
            error.style.display = 'block';
        }
        return;
    }

    // Get form values safely
    const getElementValue = (id, defaultValue = '') => {
        const element = document.getElementById(id);
        return element ? element.value : defaultValue;
    };

    const getElementChecked = (id, defaultValue = false) => {
        const element = document.getElementById(id);
        return element ? element.checked : defaultValue;
    };

    const getRadioValue = (name, defaultValue = '') => {
        const radio = document.querySelector(`input[name="${name}"]:checked`);
        return radio ? radio.value : defaultValue;
    };

    // Collect ALL options from the form
    const payload = {
        // Core settings
        prompt: getElementValue('prompt'),
        mode: getRadioValue('mode', 'simple'),
        stream_mode: getElementValue('streamMode'),

        // TTS options
        tts_engine: getElementValue('ttsEngine'),
        tts_voice: getElementValue('ttsVoice'),
        tts_speed: parseInt(getElementValue('ttsSpeed', '160')),
        tts_pitch: parseInt(getElementValue('ttsPitch', '0')),
        tts_language: getElementValue('ttsLanguage'),
        tts_emotion: getElementValue('ttsEmotion'),

        // Audio format
        sample_rate: parseInt(getElementValue('sampleRate', '22050')),
        format: getElementValue('audioFormat'),

        // Image options
        image: imageValidation.data,
        still: getElementChecked('still'),
        preprocess: getElementValue('preprocess'),
        resolution: getElementValue('resolution'),
        face_detection: getElementChecked('faceDetection'),
        face_confidence: parseFloat(getElementValue('faceConfidence', '0.5')),
        auto_resize: getElementChecked('autoResize'),

        // Video options
        codec: getElementValue('codec'),
        quality: getElementValue('quality'),
        fps: parseInt(getElementValue('fps', '25')),
        bitrate: parseInt(getElementValue('bitrate', '2000')),
        keyframe_interval: parseInt(getElementValue('keyframeInterval', '2')),
        hardware_accel: getElementChecked('hardwareAccel'),

        // Streaming options
        chunk_duration: parseFloat(getElementValue('chunkDuration', '3')),
        delivery_mode: getElementValue('deliveryMode', 'base64'),
        buffer_size: parseInt(getElementValue('bufferSize', '5')),
        low_latency: getElementChecked('lowLatency'),
        adaptive_quality: getElementChecked('adaptiveQuality'),

        // SadTalker options
        timeout: parseInt(getElementValue('timeout', '1200')),
        enhancer: getElementValue('enhancer') || null,
        split_chunks: getElementChecked('splitChunks'),
        chunk_length: parseInt(getElementValue('chunkLength', '10')),
        overlap_duration: parseFloat(getElementValue('overlapDuration', '1')),
        expression_scale: parseFloat(getElementValue('expressionScale', '1.0')),
        use_3d_warping: getElementChecked('use3dWarping'),
        use_eye_blink: getElementChecked('useEyeBlink'),
        use_head_pose: getElementChecked('useHeadPose'),

        // Advanced options
        max_duration: parseInt(getElementValue('maxDuration', '300')),
        max_concurrent: parseInt(getElementValue('maxConcurrent', '3')),
        memory_limit: parseInt(getElementValue('memoryLimit', '8')),
        enable_websocket: getElementChecked('enableWebsocket'),
        verbose_logging: getElementChecked('verboseLogging'),
        save_intermediates: getElementChecked('saveIntermediates'),
        profile_performance: getElementChecked('profilePerformance'),
        beta_features: getElementChecked('betaFeatures'),
        ml_acceleration: getElementChecked('mlAcceleration'),
        worker_threads: parseInt(getElementValue('workerThreads', '4')),

        // Peer selection
        peer: selectedPeer
    };

    debugLog('Starting avatar generation with complete options', payload);
    generationInProgress = true;

    if (loading) {
        loading.style.display = 'block';
        loading.classList.add('show');
    }
    if (result) result.style.display = 'none';
    if (error) error.style.display = 'none';

    try {
        updateProgress(10, 'Initializing generation...');
        const startTime = Date.now();

        const response = await fetch(`/web/api/avatar_dual.php?action=generate&mode=${payload.mode}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        updateProgress(50, 'Processing avatar...');
        const duration = Date.now() - startTime;

        debugLog('Avatar API response received', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            contentType: response.headers.get('content-type'),
            duration: duration + 'ms'
        });

        if (response.ok) {
            const contentType = response.headers.get('content-type');
            updateProgress(80, 'Processing response...');

            // Handle streaming responses
            if (contentType && contentType.includes('multipart/x-mixed-replace')) {
                debugLog('Streaming response detected', { contentType });
                updateProgress(90, 'Processing stream...');

                try {
                    await handleStreamingResponse(response, video, videoInfo, payload, duration);
                    if (result) result.style.display = 'block';
                    updateProgress(100, 'Streaming complete!');
                    showNotification('Avatar streaming completed successfully!', 'success');
                } catch (streamError) {
                    debugLog('Streaming processing failed', { error: streamError.message });
                    throw new Error('Streaming processing failed: ' + streamError.message);
                }

                // Handle regular video responses
            } else if (contentType && contentType.includes('video')) {
                debugLog('Regular video response detected', { contentType });
                updateProgress(90, 'Finalizing video...');

                const blob = await response.blob();
                const videoUrl = URL.createObjectURL(blob);

                if (video) video.src = videoUrl;
                if (result) result.style.display = 'block';

                if (videoInfo) {
                    videoInfo.innerHTML = `
                                <strong>Generation Complete!</strong><br>
                                Mode: ${response.headers.get('x-avatar-mode') || payload.mode}<br>
                                Engine: ${response.headers.get('x-avatar-engine') || payload.tts_engine}<br>
                                Voice: ${response.headers.get('x-avatar-voice') || payload.tts_voice}<br>
                                Size: ${(blob.size / 1024 / 1024).toFixed(2)} MB<br>
                                Type: ${contentType}<br>
                                Generation Time: ${(duration / 1000).toFixed(1)}s<br>
                                Parameters: ${Object.keys(payload).length} options used
                            `;
                }

                updateProgress(100, 'Complete!');

                if (video) {
                    video.onloadeddata = () => {
                        debugLog('Video loaded successfully', {
                            duration: video.duration,
                            videoWidth: video.videoWidth,
                            videoHeight: video.videoHeight
                        });
                        showNotification('Avatar generated successfully!', 'success');
                    };

                    video.onerror = (e) => {
                        debugLog('Video error', { error: video.error });
                        showNotification('Video playback error', 'error');
                    };
                }

                // Handle JSON responses (like WebSocket info)
            } else if (contentType && contentType.includes('json')) {
                debugLog('JSON response detected', { contentType });
                const jsonData = await response.json();

                if (jsonData.type === 'websocket_info') {
                    debugLog('WebSocket info received', jsonData);
                    showNotification('WebSocket streaming info received - check console', 'info');
                    if (error) {
                        error.innerHTML = `<strong>WebSocket Streaming:</strong><br>
                                    Connect to: ${jsonData.websocket_url}<br>
                                    <small>WebSocket streaming requires additional frontend implementation</small>`;
                        error.style.display = 'block';
                    }
                } else {
                    debugLog('Unknown JSON response', jsonData);
                    throw new Error('Unexpected JSON response: ' + JSON.stringify(jsonData));
                }

                // Handle unexpected responses
            } else {
                const text = await response.text();
                debugLog('Unexpected response received', {
                    contentType,
                    response: text.substring(0, 500)
                });
                throw new Error('Unexpected response type: ' + contentType);
            }

        } else {
            const errorText = await response.text();
            debugLog('Avatar generation failed', { status: response.status, error: errorText });
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

    } catch (err) {
        debugLog('Avatar generation error', { error: err.message });
        if (error) {
            error.textContent = 'Generation Error: ' + err.message;
            error.style.display = 'block';
        }
        showNotification('Avatar generation failed: ' + err.message, 'error');
    } finally {
        generationInProgress = false;
        if (loading) {
            loading.style.display = 'none';
            loading.classList.remove('show');
        }
    }
}

function createJpegAnimation(video, videoFrames) {
    if (!video) return;

    // Hide the video element and create a canvas animation
    video.style.display = 'none';

    // Create canvas for JPEG frame animation
    let canvas = video.parentNode.querySelector('.jpeg-animation-canvas');
    if (!canvas) {
        canvas = document.createElement('canvas');
        canvas.className = 'jpeg-animation-canvas';
        canvas.style.maxWidth = '100%';
        canvas.style.height = 'auto';
        canvas.style.border = '2px solid #555';
        canvas.style.borderRadius = '8px';
        canvas.style.cursor = 'pointer';
        video.parentNode.insertBefore(canvas, video);
    }

    canvas.style.display = 'block';

    // Set up canvas animation
    const ctx = canvas.getContext('2d');
    let currentFrameIndex = 0;
    let animationPlaying = false;
    let animationInterval;

    // Load first frame to set canvas size
    const firstImg = new Image();
    firstImg.onload = function() {
        canvas.width = firstImg.width;
        canvas.height = firstImg.height;
        ctx.drawImage(firstImg, 0, 0);

        // Add play button overlay
        drawPlayButton(ctx, canvas.width, canvas.height);

        debugLog('Canvas initialized', {
            width: canvas.width,
            height: canvas.height,
            totalFrames: videoFrames.length
        });
    };

    // Function to draw play button
    function drawPlayButton(context, width, height) {
        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.min(width, height) * 0.08;

        // Draw semi-transparent background
        context.fillStyle = 'rgba(0, 0, 0, 0.6)';
        context.beginPath();
        context.arc(centerX, centerY, radius, 0, 2 * Math.PI);
        context.fill();

        // Draw play triangle
        context.fillStyle = 'white';
        context.beginPath();
        context.moveTo(centerX - radius * 0.3, centerY - radius * 0.4);
        context.lineTo(centerX + radius * 0.4, centerY);
        context.lineTo(centerX - radius * 0.3, centerY + radius * 0.4);
        context.closePath();
        context.fill();
    }

    // Set up click handler for play/pause
    canvas.onclick = function(event) {
        debugLog('Canvas clicked', {
            animationPlaying: animationPlaying,
            frameCount: videoFrames.length
        });

        if (animationPlaying) {
            // Pause animation
            clearInterval(animationInterval);
            animationPlaying = false;

            // Show play button again
            drawPlayButton(ctx, canvas.width, canvas.height);
            debugLog('Animation paused');
            showNotification('Animation paused', 'info');
        } else {
            // Start animation
            if (videoFrames.length === 0) {
                showNotification('No frames to animate', 'error');
                return;
            }

            animationPlaying = true;
            currentFrameIndex = 0;
            const fps = 15; // Animation FPS
            const frameDuration = 1000 / fps;

            debugLog('Starting animation', {
                fps: fps,
                totalFrames: videoFrames.length,
                frameDuration: frameDuration
            });

            showNotification('Animation started - Click to pause', 'success');

            animationInterval = setInterval(() => {
                try {
                    if (currentFrameIndex >= videoFrames.length) {
                        currentFrameIndex = 0; // Loop back to start
                    }

                    const frameData = videoFrames[currentFrameIndex];
                    const frameBlob = new Blob([frameData], { type: 'image/jpeg' });
                    const frameUrl = URL.createObjectURL(frameBlob);

                    const frameImg = new Image();
                    frameImg.onload = function() {
                        if (animationPlaying) { // Check if still playing
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(frameImg, 0, 0);
                        }
                        URL.revokeObjectURL(frameUrl);
                    };
                    frameImg.onerror = function() {
                        debugLog('Frame load error', { frameIndex: currentFrameIndex });
                    };
                    frameImg.src = frameUrl;

                    currentFrameIndex++;
                } catch (error) {
                    debugLog('Animation frame error', { error: error.message });
                }
            }, frameDuration);
        }
    };

    // Add hover effect
    canvas.onmouseover = function() {
        canvas.style.opacity = '0.9';
        canvas.style.cursor = 'pointer';
    };
    canvas.onmouseout = function() {
        canvas.style.opacity = '1.0';
    };

    // Create first frame blob URL
    const firstFrameBlob = new Blob([videoFrames[0]], { type: 'image/jpeg' });
    const firstFrameUrl = URL.createObjectURL(firstFrameBlob);
    firstImg.src = firstFrameUrl;

    debugLog('JPEG animation canvas created', {
        frameCount: videoFrames.length,
        canvasSize: `${canvas.width}x${canvas.height}`
    });
}

