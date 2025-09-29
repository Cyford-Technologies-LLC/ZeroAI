// Diagnostic script to identify streaming issues
// Add this to your avatar_debug3.html page temporarily

class StreamingDiagnostics {
    constructor() {
        this.results = {
            browserSupport: {},
            networkTiming: {},
            chunkAnalysis: [],
            errors: []
        };
    }

    // Run all diagnostics
    async runFullDiagnostics() {
        console.log('=== Starting Streaming Diagnostics ===');

        // Check browser capabilities
        this.checkBrowserSupport();

        // Check codec support
        this.checkCodecSupport();

        // Monitor network timing
        this.setupNetworkMonitoring();

        // Check current implementation issues
        this.analyzeCurre

        // Test MediaSource with sample data
        await this.testMediaSource();

        // Generate report
        this.generateReport();
    }

    checkBrowserSupport() {
        this.results.browserSupport = {
            mediaSource: typeof MediaSource !== 'undefined',
            sourceBuffer: typeof SourceBuffer !== 'undefined',
            mediaRecorder: typeof MediaRecorder !== 'undefined',
            fetch: typeof fetch !== 'undefined',
            readableStream: typeof ReadableStream !== 'undefined',
            textDecoder: typeof TextDecoder !== 'undefined',
            blob: typeof Blob !== 'undefined',
            url: typeof URL !== 'undefined' && typeof URL.createObjectURL !== 'undefined'
        };

        console.log('Browser Support:', this.results.browserSupport);
    }

    checkCodecSupport() {
        if (!window.MediaSource) {
            this.results.codecSupport = { error: 'MediaSource not supported' };
            return;
        }

        const codecs = [
            'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
            'video/mp4; codecs="avc1.640028, mp4a.40.2"',
            'video/mp4; codecs="avc1.4D401E, mp4a.40.2"',
            'video/mp4; codecs="avc1.42E01E"',
            'video/mp4; codecs="avc1.640028"',
            'video/webm; codecs="vp8, vorbis"',
            'video/webm; codecs="vp9, opus"',
            'video/webm; codecs="vp9"'
        ];

        this.results.codecSupport = {};
        codecs.forEach(codec => {
            this.results.codecSupport[codec] = MediaSource.isTypeSupported(codec);
        });

        console.log('Codec Support:', this.results.codecSupport);
    }

    setupNetworkMonitoring() {
        // Override fetch to monitor timing
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            const startTime = performance.now();
            const response = await originalFetch.apply(window, args);

            if (args[0].includes('/avatar')) {
                this.results.networkTiming.lastRequestDuration = performance.now() - startTime;
                console.log(`Network request took ${this.results.networkTiming.lastRequestDuration}ms`);
            }

            return response;
        };
    }

    analyzeCurrentImplementation() {
        console.log('Analyzing current implementation...');

        // Check if AvatarStreamProcessor exists
        const hasStreamProcessor = typeof AvatarStreamProcessor !== 'undefined';

        // Check if video element exists
        const videoElement = document.getElementById('avatarVideo');
        const hasVideoElement = videoElement !== null;

        // Check current processor instance
        let processorIssues = [];

        if (typeof avatarStreamProcessor !== 'undefined' && avatarStreamProcessor) {
            // Check if it's waiting for complete stream
            if (avatarStreamProcessor.videoChunks && avatarStreamProcessor.videoChunks.length > 0) {
                processorIssues.push('Chunks are being collected but not played immediately');
            }

            // Check if updateVideoPlayback is combining all chunks
            if (avatarStreamProcessor.updateVideoPlayback) {
                const funcString = avatarStreamProcessor.updateVideoPlayback.toString();
                if (funcString.includes('combinedBlobs') || funcString.includes('map(chunk')) {
                    processorIssues.push('updateVideoPlayback is combining ALL chunks before playing - THIS IS THE PROBLEM');
                }
            }
        }

        this.results.currentImplementation = {
            hasStreamProcessor,
            hasVideoElement,
            processorIssues,
            recommendation: processorIssues.length > 0
                ? 'Your current implementation waits for all chunks. Use the fixed version above.'
                : 'Implementation looks okay, check network/codec issues'
        };

        console.log('Implementation Analysis:', this.results.currentImplementation);
    }

    async testMediaSource() {
        console.log('Testing MediaSource with sample data...');

        try {
            const video = document.createElement('video');
            const mediaSource = new MediaSource();
            video.src = URL.createObjectURL(mediaSource);

            await new Promise((resolve, reject) => {
                mediaSource.addEventListener('sourceopen', resolve, { once: true });
                setTimeout(() => reject(new Error('MediaSource timeout')), 2000);
            });

            // Find a supported codec
            let mimeType = null;
            for (const [codec, supported] of Object.entries(this.results.codecSupport)) {
                if (supported) {
                    mimeType = codec;
                    break;
                }
            }

            if (mimeType) {
                const sourceBuffer = mediaSource.addSourceBuffer(mimeType);
                this.results.mediaSourceTest = {
                    success: true,
                    codec: mimeType,
                    message: 'MediaSource is working'
                };
            } else {
                this.results.mediaSourceTest = {
                    success: false,
                    message: 'No supported codec found'
                };
            }

        } catch (error) {
            this.results.mediaSourceTest = {
                success: false,
                error: error.message
            };
        }

        console.log('MediaSource Test:', this.results.mediaSourceTest);
    }

    monitorChunkReception(chunkData) {
        // Call this when each chunk is received
        const analysis = {
            timestamp: Date.now(),
            chunkId: chunkData.chunk_id || chunkData.id,
            hasVideoData: !!chunkData.video_data,
            videoDataLength: chunkData.video_data ? chunkData.video_data.length : 0,
            duration: chunkData.duration,
            isBase64: chunkData.video_data && chunkData.video_data.startsWith('data:')
        };

        this.results.chunkAnalysis.push(analysis);

        // Check timing
        if (this.results.chunkAnalysis.length > 1) {
            const lastChunk = this.results.chunkAnalysis[this.results.chunkAnalysis.length - 2];
            analysis.timeSinceLastChunk = analysis.timestamp - lastChunk.timestamp;
        }

        console.log('Chunk received:', analysis);

        // Identify issues
        if (analysis.timeSinceLastChunk > 5000) {
            console.warn('⚠️ Large gap between chunks:', analysis.timeSinceLastChunk, 'ms');
        }

        if (!analysis.hasVideoData) {
            console.warn('⚠️ Chunk has no video data');
        }

        return analysis;
    }

    generateReport() {
        console.log('\n=== DIAGNOSTIC REPORT ===\n');

        // Summary
        const issues = [];
        const recommendations = [];

        // Check browser support
        if (!this.results.browserSupport.mediaSource) {
            issues.push('❌ MediaSource API not supported');
            recommendations.push('Use fallback blob mode');
        }

        // Check codec support
        const supportedCodecs = Object.entries(this.results.codecSupport || {})
            .filter(([codec, supported]) => supported)
            .map(([codec]) => codec);

        if (supportedCodecs.length === 0) {
            issues.push('❌ No video codecs supported');
            recommendations.push('Check browser compatibility');
        } else {
            console.log('✅ Supported codecs:', supportedCodecs);
        }

        // Check implementation
        if (this.results.currentImplementation?.processorIssues?.length > 0) {
            issues.push('❌ Implementation waits for all chunks before playing');
            recommendations.push('Replace AvatarStreamProcessor with the fixed version');
        }

        // Print summary
        console.log('ISSUES FOUND:');
        issues.forEach(issue => console.log('  ' + issue));

        console.log('\nRECOMMENDATIONS:');
        recommendations.forEach(rec => console.log('  • ' + rec));

        console.log('\nFULL RESULTS:', this.results);

        // Create visual report
        this.createVisualReport();
    }

    createVisualReport() {
        const reportDiv = document.createElement('div');
        reportDiv.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            width: 400px;
            background: #1a1a1a;
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            z-index: 10000;
            font-family: monospace;
            font-size: 12px;
            max-height: 80vh;
            overflow-y: auto;
        `;

        let html = '<h3 style="margin-top:0">Streaming Diagnostics</h3>';

        // Browser support
        html += '<h4>Browser Support</h4><ul style="padding-left:20px">';
        for (const [feature, supported] of Object.entries(this.results.browserSupport)) {
            const icon = supported ? '✅' : '❌';
            html += `<li>${icon} ${feature}</li>`;
        }
        html += '</ul>';

        // Codec support
        html += '<h4>Codec Support</h4><ul style="padding-left:20px">';
        for (const [codec, supported] of Object.entries(this.results.codecSupport || {})) {
            if (supported) {
                html += `<li>✅ ${codec}</li>`;
            }
        }
        html += '</ul>';

        // Issues
        if (this.results.currentImplementation?.processorIssues?.length > 0) {
            html += '<h4 style="color:#ff6b6b">Issues Found</h4><ul style="padding-left:20px">';
            this.results.currentImplementation.processorIssues.forEach(issue => {
                html += `<li style="color:#ff6b6b">${issue}</li>`;
            });
            html += '</ul>';
        }

        // Recommendation
        html += '<h4>Recommendation</h4>';
        html += `<p style="color:#6bff6b">${this.results.currentImplementation?.recommendation || 'Run full test'}</p>`;

        // Close button
        html += '<button onclick="this.parentElement.remove()" style="background:#ff6b6b;border:none;color:white;padding:5px 10px;border-radius:3px;cursor:pointer;margin-top:10px">Close</button>';

        reportDiv.innerHTML = html;
        document.body.appendChild(reportDiv);
    }
}

// Auto-run diagnostics
const diagnostics = new StreamingDiagnostics();

// Run diagnostics when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        diagnostics.runFullDiagnostics();
    });
} else {
    diagnostics.runFullDiagnostics();
}

// Expose globally for manual testing
window.streamingDiagnostics = diagnostics;

console.log('Diagnostics loaded. Results will appear in console and on-screen.');
console.log('To run again: streamingDiagnostics.runFullDiagnostics()');
console.log('To monitor chunks: streamingDiagnostics.monitorChunkReception(chunkData)');