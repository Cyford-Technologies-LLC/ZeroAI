<?php
// Enhanced API section for avatar_dual.php - ADD this to the 'generate' case

// Image processing - ENHANCED: Complete image upload handling
if (isset($input['image']) && !empty($input['image'])) {
    $imageData = $input['image'];

    // Handle different image input types
    if (filter_var($imageData, FILTER_VALIDATE_URL)) {
        // It's a URL - validate and potentially download
        error_log("Processing image URL: " . $imageData);
        $options['image'] = $imageData;
        $options['image_type'] = 'url';

    } elseif (strpos($imageData, 'data:image/') === 0) {
        // It's base64 data URI from file upload
        error_log("Processing base64 image upload");

        // Extract MIME type and validate
        preg_match('/data:image\/([a-zA-Z0-9]+);base64,(.+)/', $imageData, $matches);
        if (count($matches) !== 3) {
            throw new Exception('Invalid base64 image format');
        }

        $imageType = $matches[1];
        $base64Data = $matches[2];

        // Validate image type
        $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($imageType), $allowedTypes)) {
            throw new Exception('Unsupported image type: ' . $imageType);
        }

        // Decode and validate
        $decodedImage = base64_decode($base64Data);
        if ($decodedImage === false) {
            throw new Exception('Failed to decode base64 image');
        }

        // Check file size (limit to 10MB)
        if (strlen($decodedImage) > 10 * 1024 * 1024) {
            throw new Exception('Image file too large (max 10MB)');
        }

        // Save to temporary file for processing
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/avatar_upload_' . uniqid() . '.' . $imageType;

        if (file_put_contents($tempFile, $decodedImage) === false) {
            throw new Exception('Failed to save uploaded image');
        }

        // Validate it's actually an image
        $imageInfo = getimagesize($tempFile);
        if ($imageInfo === false) {
            unlink($tempFile);
            throw new Exception('Invalid image file');
        }

        error_log("Image validated: {$imageInfo[0]}x{$imageInfo[1]}, type: {$imageInfo['mime']}");

        $options['image'] = $tempFile;
        $options['image_type'] = 'file';
        $options['temp_file'] = $tempFile; // Track for cleanup
        $options['image_info'] = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime' => $imageInfo['mime'],
            'size' => strlen($decodedImage)
        ];

    } elseif (file_exists($imageData)) {
        // It's a file path
        error_log("Processing image file path: " . $imageData);
        $options['image'] = $imageData;
        $options['image_type'] = 'file';

    } else {
        throw new Exception('Invalid image data provided');
    }
}

// After generation, cleanup temp files
if (isset($options['temp_file']) && file_exists($options['temp_file'])) {
    unlink($options['temp_file']);
    error_log("Cleaned up temporary image file: " . $options['temp_file']);
}

// ============================================================================
// Enhanced AvatarManager methods - ADD these to your AvatarManager class
// ============================================================================

/**
 * Process and validate image for avatar generation
 */
private function processImage($imageData, $options = [])
{
    if (empty($imageData)) {
        return null;
    }

    $this->logger->info('Processing image for avatar generation', [
        'image_type' => $options['image_type'] ?? 'unknown',
        'has_image_info' => isset($options['image_info'])
    ]);

    // Handle different image types
    switch ($options['image_type'] ?? 'unknown') {
        case 'url':
            return $this->processImageUrl($imageData, $options);

        case 'file':
            return $this->processImageFile($imageData, $options);

        default:
            throw new \Exception('Unknown image type for processing');
    }
}

/**
 * Process image URL
 */
private function processImageUrl($url, $options = [])
{
    $this->logger->info('Processing image URL', ['url' => $url]);

    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new \Exception('Invalid image URL format');
    }

    // Check if URL is accessible (optional - might want to skip for performance)
    if (isset($options['validate_url']) && $options['validate_url']) {
        $headers = @get_headers($url);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw new \Exception('Image URL is not accessible');
        }

        // Check content type
        foreach ($headers as $header) {
            if (stripos($header, 'content-type:') === 0) {
                if (stripos($header, 'image/') === false) {
                    throw new \Exception('URL does not point to an image');
                }
                break;
            }
        }
    }

    return $url;
}

/**
 * Process image file
 */
private function processImageFile($filePath, $options = [])
{
    $this->logger->info('Processing image file', [
        'file' => $filePath,
        'exists' => file_exists($filePath)
    ]);

    if (!file_exists($filePath)) {
        throw new \Exception('Image file does not exist: ' . $filePath);
    }

    // Get image information
    $imageInfo = getimagesize($filePath);
    if ($imageInfo === false) {
        throw new \Exception('Invalid image file');
    }

    // Check image dimensions
    $maxWidth = $options['max_width'] ?? 2048;
    $maxHeight = $options['max_height'] ?? 2048;
    $minWidth = $options['min_width'] ?? 64;
    $minHeight = $options['min_height'] ?? 64;

    if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
        $this->logger->warning('Image too large', [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'max_width' => $maxWidth,
            'max_height' => $maxHeight
        ]);

        // Optionally resize (requires GD extension)
        if (isset($options['auto_resize']) && $options['auto_resize'] && extension_loaded('gd')) {
            return $this->resizeImage($filePath, $maxWidth, $maxHeight, $options);
        } else {
            throw new \Exception("Image too large: {$imageInfo[0]}x{$imageInfo[1]} (max: {$maxWidth}x{$maxHeight})");
        }
    }

    if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
        throw new \Exception("Image too small: {$imageInfo[0]}x{$imageInfo[1]} (min: {$minWidth}x{$minHeight})");
    }

    // Check file size
    $fileSize = filesize($filePath);
    $maxSize = $options['max_file_size'] ?? (10 * 1024 * 1024); // 10MB default

    if ($fileSize > $maxSize) {
        throw new \Exception('Image file too large: ' . round($fileSize / 1024 / 1024, 2) . 'MB (max: ' . round($maxSize / 1024 / 1024, 2) . 'MB)');
    }

    $this->logger->info('Image file validated', [
        'width' => $imageInfo[0],
        'height' => $imageInfo[1],
        'mime' => $imageInfo['mime'],
        'size_mb' => round($fileSize / 1024 / 1024, 2)
    ]);

    return $filePath;
}

/**
 * Resize image if needed (requires GD extension)
 */
private function resizeImage($filePath, $maxWidth, $maxHeight, $options = [])
{
    if (!extension_loaded('gd')) {
        throw new \Exception('GD extension required for image resizing');
    }

    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];

    // Calculate new dimensions maintaining aspect ratio
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = round($sourceWidth * $ratio);
    $newHeight = round($sourceHeight * $ratio);

    $this->logger->info('Resizing image', [
        'original' => "{$sourceWidth}x{$sourceHeight}",
        'new' => "{$newWidth}x{$newHeight}",
        'ratio' => $ratio
    ]);

    // Create image resource based on type
    switch ($imageInfo['mime']) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($filePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($filePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($filePath);
            break;
        default:
            throw new \Exception('Unsupported image type for resizing: ' . $imageInfo['mime']);
    }

    if (!$source) {
        throw new \Exception('Failed to create image resource for resizing');
    }

    // Create new image
    $destination = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG and GIF
    if ($imageInfo['mime'] === 'image/png' || $imageInfo['mime'] === 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefill($destination, 0, 0, $transparent);
    }

    // Resize
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

    // Save resized image
    $resizedPath = $filePath . '_resized';
    $quality = $options['resize_quality'] ?? 85;

    switch ($imageInfo['mime']) {
        case 'image/jpeg':
            imagejpeg($destination, $resizedPath, $quality);
            break;
        case 'image/png':
            imagepng($destination, $resizedPath, 9);
            break;
        case 'image/gif':
            imagegif($destination, $resizedPath);
            break;
        case 'image/webp':
            imagewebp($destination, $resizedPath, $quality);
            break;
    }

    // Clean up
    imagedestroy($source);
    imagedestroy($destination);

    if (!file_exists($resizedPath)) {
        throw new \Exception('Failed to save resized image');
    }

    return $resizedPath;
}

/**
 * Enhanced callAvatarService with better image handling
 */
private function callAvatarService($prompt, $mode, $options = [])
{
    // Process image if provided
    if (isset($options['image'])) {
        try {
            $processedImage = $this->processImage($options['image'], $options);
            $options['image'] = $processedImage;

            $this->logger->info('Image processed successfully for avatar service', [
                'processed_image' => $processedImage,
                'original' => substr($options['image'] ?? '', 0, 100)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Image processing failed', [
                'error' => $e->getMessage(),
                'image_data' => substr($options['image'] ?? '', 0, 100)
            ]);
            throw new \Exception('Image processing failed: ' . $e->getMessage());
        }
    }

    // Continue with existing callAvatarService implementation...
    $codec = $options['codec'] ?? 'h264_fast';
    $quality = $options['quality'] ?? 'high';

    $url = $this->avatarServiceUrl . '/generate?mode=' . $mode . '&codec=' . $codec . '&quality=' . $quality;

    // Build comprehensive payload with image support
    $payload = [
        'prompt' => $prompt,
        'tts_engine' => $options['tts_engine'] ?? 'espeak'
    ];

    // Add image to payload if processed
    if (isset($options['image'])) {
        $payload['image'] = $options['image'];

        // Add image metadata if available
        if (isset($options['image_info'])) {
            $payload['image_info'] = $options['image_info'];
        }
        if (isset($options['image_type'])) {
            $payload['image_type'] = $options['image_type'];
        }
    }

    // Add all other TTS and generation options...
    foreach ($options as $key => $value) {
        if (!in_array($key, ['image', 'image_info', 'image_type', 'temp_file'])) {
            $payload[$key] = $value;
        }
    }

    $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $this->logger->debug('Calling avatar service with image support', [
        'url' => $url,
        'payload_keys' => array_keys($payload),
        'has_image' => isset($payload['image']),
        'image_type' => $options['image_type'] ?? 'none',
        'data_length' => strlen($data)
    ]);

    // Rest of the curl implementation remains the same...
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new \Exception('Curl error: ' . $error);
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($result, true);
        $errorMessage = $errorData['error'] ?? 'HTTP error: ' . $httpCode;
        throw new \Exception($errorMessage);
    }

    if (!$result) {
        throw new \Exception('Empty response from avatar service');
    }

    return [
        'data' => $result,
        'content_type' => $contentType,
        'size' => strlen($result)
    ];
}