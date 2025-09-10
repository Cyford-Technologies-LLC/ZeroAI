<?php
function processFileCommands(&$message) {
    // @file command
    if (preg_match('/\@file\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
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

    // @list command
    if (preg_match('/\@list\s+(.+)/', $message, $matches)) {
        $dirPath = trim($matches[1]);
        $cleanPath = ltrim($dirPath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullDirPath = '/app/' . $cleanPath;
        if (is_dir($fullDirPath)) {
            $files = scandir($fullDirPath);
            $listing = "Directory listing for " . $dirPath . ":\n" . implode("\n", array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
            $message .= "\n\n" . $listing;
        } else {
            $message .= "\n\nDirectory not found: " . $dirPath;
        }
    }

    // @create command
    if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
        $filePath = trim($matches[1]);
        $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
        $cleanPath = ltrim($filePath, '/');
        if (strpos($cleanPath, 'app/') === 0) $cleanPath = substr($cleanPath, 4);
        $fullPath = '/app/' . $cleanPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $result = file_put_contents($fullPath, $fileContent);
        if ($result !== false) {
            $message .= "\n\n✅ File created: " . $cleanPath . " (" . $result . " bytes)";
        } else {
            $message .= "\n\n❌ Failed to create: " . $cleanPath;
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