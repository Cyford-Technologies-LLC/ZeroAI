// Tab Management
function switchTab(tabName) {
    // Remove active class from all tabs and content
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Add active class to selected tab and content
    const tabContent = document.getElementById(tabName + '-tab');
    if (tabContent) {
        tabContent.classList.add('active');
    }

    // Find and activate the clicked tab
    const clickedTab = event.target;
    if (clickedTab) {
        clickedTab.classList.add('active');
    }

    debugLog('Switched to tab', { tab: tabName });
}

// Range Display Updates
function updateRangeDisplay(type, value) {
    const display = document.getElementById(type + 'Display');
    if (display) {
        display.textContent = value;
    }
}

// TTS Engine Management
function updateTTSOptions() {
    const engine = document.getElementById('ttsEngine').value;
    const voiceSelect = document.getElementById('ttsVoice');
    const speedRange = document.getElementById('ttsSpeed');
    const pitchRange = document.getElementById('ttsPitch');

    if (TTS_ENGINES[engine] && voiceSelect && speedRange && pitchRange) {
        const config = TTS_ENGINES[engine];

        voiceSelect.innerHTML = config.voices
            .map(v => `<option value="${v.value}">${v.label}</option>`)
            .join('');

        speedRange.min = config.speedRange[0];
        speedRange.max = config.speedRange[1];
        speedRange.value = Math.floor((config.speedRange[0] + config.speedRange[1]) / 2);
        updateRangeDisplay('speed', speedRange.value);

        pitchRange.min = config.pitchRange[0];
        pitchRange.max = config.pitchRange[1];
        pitchRange.value = 0;
        updateRangeDisplay('pitch', pitchRange.value);

        debugLog('TTS engine updated', { engine, voiceCount: config.voices.length });
    }
}

// Image Upload Management
function handleImageUpload(event) {
    const file = event.target.files[0];
    if (!file) {
        currentImageData = null;
        const preview = document.getElementById('imagePreview');
        if (preview) preview.innerHTML = '';
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showNotification('Unsupported file type. Please use JPEG, PNG, GIF, or WebP images.', 'error');
        event.target.value = '';
        return;
    }

    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        showNotification(`File too large. Maximum size is ${Math.round(maxSize / 1024 / 1024)}MB. Your file is ${Math.round(file.size / 1024 / 1024)}MB.`, 'error');
        event.target.value = '';
        return;
    }

    const preview = document.getElementById('imagePreview');
    if (preview) {
        preview.innerHTML = '<div class="loading">Processing image...</div>';
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const imageData = e.target.result;

        if (!imageData.startsWith('data:image/')) {
            showNotification('Invalid image file', 'error');
            event.target.value = '';
            if (preview) preview.innerHTML = '';
            return;
        }

        const img = new Image();
        img.onload = function() {
            const minWidth = 64, minHeight = 64;
            const maxWidth = 2048, maxHeight = 2048;

            if (img.width < minWidth || img.height < minHeight) {
                showNotification(`Image too small. Minimum size is ${minWidth}x${minHeight}px. Your image is ${img.width}x${img.height}px.`, 'error');
                event.target.value = '';
                if (preview) preview.innerHTML = '';
                return;
            }

            if (img.width > maxWidth || img.height > maxHeight) {
                const autoResize = document.getElementById('autoResize');
                if (!autoResize || (!autoResize.checked && !confirm(`Image is large (${img.width}x${img.height}px). Maximum recommended size is ${maxWidth}x${maxHeight}px. Continue anyway?`))) {
                    event.target.value = '';
                    if (preview) preview.innerHTML = '';
                    return;
                }
            }

            currentImageData = imageData;
            displayImagePreview(imageData, {
                width: img.width,
                height: img.height,
                size: file.size,
                type: file.type,
                name: file.name
            });

            showNotification('Image uploaded successfully', 'success');
            debugLog('Image uploaded successfully', {
                filename: file.name,
                size: file.size,
                type: file.type,
                dimensions: `${img.width}x${img.height}`
            });
        };

        img.onerror = function() {
            showNotification('Failed to load image. Please try a different file.', 'error');
            event.target.value = '';
            if (preview) preview.innerHTML = '';
        };

        img.src = imageData;
    };

    reader.onerror = function() {
        showNotification('Failed to read file. Please try again.', 'error');
        event.target.value = '';
        const preview = document.getElementById('imagePreview');
        if (preview) preview.innerHTML = '';
    };

    reader.readAsDataURL(file);
}

function previewImageUrl() {
    const urlInput = document.getElementById('imageUrlInput');
    const url = urlInput ? urlInput.value.trim() : '';

    if (!url) {
        showNotification('Please enter an image URL', 'error');
        return;
    }

    try {
        new URL(url);
    } catch (e) {
        showNotification('Invalid URL format', 'error');
        return;
    }

    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp'];
    const hasImageExtension = imageExtensions.some(ext =>
        url.toLowerCase().includes(ext)
    );

    if (!hasImageExtension && !url.includes('image') && !url.includes('photo')) {
        if (!confirm('URL doesn\'t appear to be an image. Continue anyway?')) {
            return;
        }
    }

    const preview = document.getElementById('imagePreview');
    if (preview) {
        preview.innerHTML = '<div class="loading">Loading image from URL...</div>';
    }

    const img = new Image();
    img.onload = function() {
        currentImageData = url;
        displayImagePreview(url, {
            width: img.width,
            height: img.height,
            type: 'url',
            url: url
        });

        showNotification('Image URL loaded successfully', 'success');
        debugLog('Image URL loaded successfully', {
            url: url,
            dimensions: `${img.width}x${img.height}`
        });
    };

    img.onerror = function() {
        showNotification('Failed to load image from URL. Please check the URL and try again.', 'error');
        if (preview) preview.innerHTML = '';
        currentImageData = null;
    };

    img.crossOrigin = 'anonymous';
    img.src = url;
}

function displayImagePreview(src, metadata = {}) {
    const preview = document.getElementById('imagePreview');
    if (!preview) return;

    let metaInfo = '';
    if (metadata.width && metadata.height) {
        metaInfo += `<div><strong>Dimensions:</strong> ${metadata.width}x${metadata.height}px</div>`;
    }
    if (metadata.size) {
        metaInfo += `<div><strong>Size:</strong> ${Math.round(metadata.size / 1024)}KB</div>`;
    }
    if (metadata.type && metadata.type !== 'url') {
        metaInfo += `<div><strong>Type:</strong> ${metadata.type}</div>`;
    }
    if (metadata.name) {
        metaInfo += `<div><strong>File:</strong> ${metadata.name}</div>`;
    }
    if (metadata.url) {
        metaInfo += `<div><strong>URL:</strong> <a href="${metadata.url}" target="_blank" style="color: #4CAF50;">View Original</a></div>`;
    }

    preview.innerHTML = `
                <img src="${src}" class="image-preview" alt="Preview">
                <div style="font-size: 12px; color: #ccc; margin-top: 5px;">
                    ${metaInfo}
                    <button onclick="clearImagePreview()" style="margin-top: 5px; padding: 3px 8px; font-size: 11px;" class="danger">Clear Image</button>
                </div>
            `;
}

function clearImagePreview() {
    currentImageData = null;
    const preview = document.getElementById('imagePreview');
    const imageFile = document.getElementById('imageFile');
    const imageUrlInput = document.getElementById('imageUrlInput');
    const imageDefault = document.getElementById('imageDefault');

    if (preview) preview.innerHTML = '';
    if (imageFile) imageFile.value = '';
    if (imageUrlInput) imageUrlInput.value = '';
    if (imageDefault) imageDefault.checked = true;

    toggleImageSource();

    showNotification('Image selection cleared', 'info');
    debugLog('Image selection cleared');
}

function toggleImageSource() {
    const uploadSection = document.getElementById('uploadSection');
    const urlSection = document.getElementById('urlSection');
    const previewSection = document.getElementById('imagePreview');
    const sourceRadios = document.querySelectorAll('input[name="imageSource"]');

    let source = 'default';
    for (let radio of sourceRadios) {
        if (radio.checked) {
            source = radio.value;
            break;
        }
    }

    if (uploadSection) uploadSection.style.display = 'none';
    if (urlSection) urlSection.style.display = 'none';

    const imageFile = document.getElementById('imageFile');
    const imageUrlInput = document.getElementById('imageUrlInput');

    if (source !== 'upload' && imageFile) {
        imageFile.value = '';
    }
    if (source !== 'url' && imageUrlInput) {
        imageUrlInput.value = '';
    }

    switch (source) {
        case 'upload':
            if (uploadSection) uploadSection.style.display = 'block';
            break;
        case 'url':
            if (urlSection) urlSection.style.display = 'block';
            break;
        case 'default':
            currentImageData = null;
            if (previewSection) {
                previewSection.innerHTML = '<div style="color: #4CAF50; font-size: 12px;">Using default avatar face</div>';
            }
            break;
    }

    if (source === 'default') {
        if (previewSection) {
            previewSection.innerHTML = '<div style="color: #4CAF50; font-size: 12px;">Using default avatar face</div>';
        }
    } else if (currentImageData && source !== getCurrentImageSource()) {
        clearImagePreview();
    }
}

function getCurrentImageSource() {
    if (!currentImageData) return 'default';
    if (currentImageData.startsWith('data:image/')) return 'upload';
    if (currentImageData.startsWith('http')) return 'url';
    return 'file';
}

function validateImageForGeneration() {
    const sourceRadios = document.querySelectorAll('input[name="imageSource"]');
    let source = 'default';
    for (let radio of sourceRadios) {
        if (radio.checked) {
            source = radio.value;
            break;
        }
    }

    if (source === 'default') {
        return { valid: true, data: null };
    }

    if (!currentImageData) {
        return {
            valid: false,
            error: `No image selected for ${source} mode. Please select an image or switch to default mode.`
        };
    }

    const modeRadios = document.querySelectorAll('input[name="mode"]');
    let mode = 'simple';
    for (let radio of modeRadios) {
        if (radio.checked) {
            mode = radio.value;
            break;
        }
    }

    if (mode === 'sadtalker' && source === 'default') {
        return {
            valid: false,
            error: 'SadTalker mode requires a custom image. Please upload an image or provide a URL.'
        };
    }

    return { valid: true, data: currentImageData };
}

// Mode change handler
document.addEventListener('DOMContentLoaded', function() {
    const modeRadios = document.querySelectorAll('input[name="mode"]');
    modeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            currentMode = this.value;
            debugLog('Mode changed', { mode: currentMode });

            // Update UI based on mode
            if (currentMode === 'sadtalker') {
                showNotification('SadTalker mode requires a custom image for best results', 'info');
            }
        });
    });
});

// Peer Management