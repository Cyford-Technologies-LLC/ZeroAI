<?php
function processFileCommands(&$message) {
    // @file command
    if (preg_match('/\@file\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @file: $filePath\n", FILE_APPEND);
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        if (file_exists($fullPath)) {
            $fileContent = file_get_contents($fullPath);
            $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
        } else {
            $message .= "\n\nFile not found: " . $filePath;
        }
    }

    // @read command (alias for @file)
    if (preg_match('/\@read\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @read: $filePath\n", FILE_APPEND);
        
        $paths = [
            $filePath,
            '/app/' . $filePath,
            '/app/' . ltrim($filePath, '/')
        ];
        
        $found = false;
        foreach ($paths as $tryPath) {
            if (file_exists($tryPath)) {
                $fileContent = file_get_contents($tryPath);
                $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $message .= "\n\nFile not found: " . $filePath;
        }
    }

    // @list command
    if (preg_match('/\@list\s+(.+)/', $message, $matches)) {
        $dirPath = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @list: $dirPath\n", FILE_APPEND);
        
        // Smart path handling - don't double-prepend /app/
        if (strpos($dirPath, '/app/') === 0) {
            $fullDirPath = $dirPath; // Already has /app/
        } else {
            $cleanPath = ltrim($dirPath, '/');
            $fullDirPath = '/app/' . $cleanPath;
        }
        
        if (is_dir($fullDirPath)) {
            $files = scandir($fullDirPath);
            $listing = "Directory listing for " . $dirPath . ":\n" . implode("\n", array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
            $message .= "\n\n" . $listing;
        } else {
            $message .= "\n\nDirectory not found: " . $dirPath . " (tried: $fullDirPath)";
        }
    }

    // @create command
    if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
        $filePath = trim($matches[1]);
        $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
        
        // Smart path handling - don't double-prepend /app/
        if (strpos($filePath, '/app/') === 0) {
            $fullPath = $filePath; // Already has /app/
        } else {
            $cleanPath = ltrim($filePath, '/');
            $fullPath = '/app/' . $cleanPath;
        }
        
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $result = file_put_contents($fullPath, $fileContent);
        if ($result !== false) {
            $message .= "\n\n✅ File created: " . $filePath . " (" . $result . " bytes)";
        } else {
            $message .= "\n\n❌ Failed to create: " . $filePath;
        }
    }

    // @edit command
    if (preg_match('/\@edit\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
        $filePath = trim($matches[1]);
        $newContent = isset($matches[2]) ? trim($matches[2]) : "";
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        if (file_exists($fullPath)) {
            $result = file_put_contents($fullPath, $newContent);
            if ($result !== false) {
                $message .= "\n\n✅ File updated: " . $filePath . " (" . $result . " bytes)";
            } else {
                $message .= "\n\n❌ Failed to update: " . $filePath;
            }
        } else {
            $message .= "\n\nFile not found: " . $filePath;
        }
    }
}
?>