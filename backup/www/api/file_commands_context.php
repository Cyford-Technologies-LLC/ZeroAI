<?php
function processFileCommandsToContext(&$message, &$context) {
    // @file command - Add to context, not message
    if (preg_match('/\@file\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        
        $paths = [
            $filePath,
            '/app/' . ltrim($filePath, '/'),
            '/app/' . $filePath
        ];
        
        $found = false;
        foreach ($paths as $tryPath) {
            if (file_exists($tryPath) && is_readable($tryPath)) {
                $fileContent = file_get_contents($tryPath);
                $context .= "\n\nFILE: " . $filePath . "\n" . $fileContent;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $context .= "\n\nFILE NOT FOUND: " . $filePath;
        }
    }

    // @read command (alias for @file)
    if (preg_match('/\@read\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        
        $paths = [
            $filePath,
            '/app/' . $filePath,
            '/app/' . ltrim($filePath, '/')
        ];
        
        $found = false;
        foreach ($paths as $tryPath) {
            if (file_exists($tryPath)) {
                $fileContent = file_get_contents($tryPath);
                $context .= "\n\nFILE: " . $filePath . "\n" . $fileContent;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $context .= "\n\nFILE NOT FOUND: " . $filePath;
        }
    }

    // @list command
    if (preg_match('/\@list\s+(.+)/', $message, $matches)) {
        $dirPath = trim($matches[1]);
        
        if (strpos($dirPath, '/app/') === 0) {
            $fullDirPath = $dirPath;
        } else {
            $cleanPath = ltrim($dirPath, '/');
            $fullDirPath = '/app/' . $cleanPath;
        }
        
        if (is_dir($fullDirPath)) {
            $files = scandir($fullDirPath);
            $listing = "DIRECTORY: " . $dirPath . "\n" . implode("\n", array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
            $context .= "\n\n" . $listing;
        } else {
            $context .= "\n\nDIRECTORY NOT FOUND: " . $dirPath;
        }
    }

    // @search command
    if (preg_match('/\@search\s+(.+)/', $message, $matches)) {
        $pattern = trim($matches[1]);
        $output = shell_exec("find /app -name '*" . escapeshellarg($pattern) . "*' 2>/dev/null | head -20");
        $context .= "\n\nSEARCH RESULTS for '" . $pattern . "':\n" . ($output ?: "No files found");
    }
}
?>